<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Mobile-app devices (B2). Lists the current workspace's connected WhatsApp
 * numbers, reports a single device's connection status, and returns the
 * contacts synced for a device.
 *
 * Response shapes are kept byte-compatible with the existing app:
 *   getDevices         → {devices: [{phone_number, device_name, active, ...}]}
 *   getConnectionStatus→ {success, status, progress, phone_number}
 *   getDeviceContacts  → {phoneNumber, contacts: [{name, number, pushname, isMyContact, avatar}]}
 *
 * Implementation runs against OUR current models (App\Models\Device,
 * App\Models\Contact) — not the old `device_user` schema. Every query is
 * scoped to the authed user's workspace via Device::forCurrentWorkspace()
 * / Contact::forCurrentWorkspace() so the app only ever sees its own data.
 */
class DeviceController extends Controller
{
    /**
     * GET /get-devices — list the workspace's WhatsApp devices.
     *
     * Old contract (WhatsAppMessageApiController::getDevices) returned
     * {devices: [{phone_number, device_name, active, ...raw row}]}. We map
     * our encrypted-at-rest Device rows into that same shape and add the
     * extra columns the new app screens read (country_code, region, status,
     * last_seen_at, assigned_user_id).
     */
    public function getDevices(Request $request): JsonResponse
    {
        try {
            $devices = Device::query()
                ->forCurrentWorkspace()
                ->orderByDesc('active')
                ->orderByDesc('id')
                ->get()
                ->map(fn (Device $d) => $this->devicePayload($d))
                ->values();

            return response()->json(['devices' => $devices], 200);
        } catch (\Throwable $e) {
            Log::error('WaDesk app getDevices failed: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
            ]);

            return response()->json(['error' => 'Something went wrong.'], 500);
        }
    }

    /**
     * GET /device-status/{deviceId} — one device's connection status.
     *
     * Old contract (WhatsAppController::getConnectionStatus) returned
     * {success, status, progress, phone_number}. {deviceId} in the old
     * project was the bare phone number; the new app addresses devices by
     * their row id, so we resolve by id first and fall back to a phone-
     * number match for backward compatibility. Per the batch spec we also
     * surface last_seen_at + active.
     */
    public function deviceStatus(Request $request, $deviceId): JsonResponse
    {
        try {
            $device = $this->resolveDevice($deviceId);
            if (! $device) {
                return response()->json([
                    'success' => false,
                    'message' => 'Device not found',
                ], 404);
            }

            return response()->json([
                'success'      => true,
                'status'       => $device->status,
                'progress'     => $this->progressFor($device),
                'phone_number' => $this->fullPhone($device),
                'active'       => (bool) $device->active,
                'last_seen_at' => optional($device->last_seen_at)->toIso8601String(),
            ], 200);
        } catch (\Throwable $e) {
            Log::error('WaDesk app deviceStatus failed: ' . $e->getMessage(), [
                'user_id'   => $request->user()?->id,
                'device_id' => $deviceId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get status',
            ], 500);
        }
    }

    /**
     * GET /device-contacts/{id} — contacts synced for this device.
     *
     * Old contract (WhatsAppController::getDeviceContacts) proxied to the
     * Node bridge (GET /api/get-contacts/{phone}) and reshaped the result
     * to {phoneNumber, contacts: [{name, number, pushname, isMyContact,
     * avatar}]}. We keep that exact shape: try the live bridge first (so a
     * freshly-paired device returns its real address book), then fall back
     * to the workspace's stored Contact rows — our Contact model is
     * workspace-scoped, not device-scoped, so the fallback returns the
     * workspace's contacts.
     */
    public function deviceContacts(Request $request, $id): JsonResponse
    {
        try {
            $device = $this->resolveDevice($id);
            if (! $device) {
                return response()->json([
                    'status'  => 404,
                    'error'   => 'Device not found',
                    'message' => 'No device matches that id in this workspace.',
                ], 404);
            }

            $phone = $this->fullPhone($device);

            // 1) Live address book from the Node bridge — but ONLY if it
            //    actually returned contacts. WhatsApp multi-device does NOT
            //    sync the phone address book into the Unofficial-API store, so
            //    the bridge usually returns an EMPTY list. In that case fall
            //    through to the workspace's stored contacts rather than handing
            //    the app an empty array.
            $live = $this->fetchLiveContacts($phone);
            if ($live !== null && ! empty($live['contacts'])) {
                return response()->json($live, 200);
            }

            // 2) Fall back to the workspace's stored contacts.
            $contacts = Contact::query()
                ->forCurrentWorkspace()
                ->orderByDesc('id')
                ->get()
                ->map(function (Contact $c) use ($phone) {
                    $number = preg_replace('/\D+/', '', (string) $c->mobile) ?: '';

                    return [
                        'name'        => (string) ($c->name ?: trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? ''))),
                        'number'      => $number,
                        'pushname'    => (string) ($c->name ?? ''),
                        'isMyContact' => ($number !== '' && $number === $phone),
                        'avatar'      => $c->image
                            ? (\Illuminate\Support\Str::startsWith($c->image, ['http://', 'https://']) ? $c->image : media_url($c->image))
                            : null,
                    ];
                })
                ->values();

            return response()->json([
                'phoneNumber' => $phone,
                'contacts'    => $contacts,
                'source'      => 'workspace',
            ], 200);
        } catch (\Throwable $e) {
            Log::error('WaDesk app deviceContacts failed: ' . $e->getMessage(), [
                'user_id'   => $request->user()?->id,
                'device_id' => $id,
            ]);

            return response()->json([
                'status'  => 500,
                'error'   => 'Failed to fetch contacts',
                'message' => 'Something went wrong.',
            ], 500);
        }
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    // -----------------------------------------------------------------
    // POST /devices — add a new Baileys device (creates row, starts the
    // Node session, returns the QR data URL + a status URL the app can
    // poll). Mirrors the web /devices store flow.
    // -----------------------------------------------------------------
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_name'             => 'required|string|min:2|max:191',
            'country_code'            => 'required|string|max:8',
            'phone_number'            => 'required|string|min:5|max:32',
            'region'                  => 'nullable|string|max:16',
            'activate_after_pairing'  => 'nullable|boolean',
        ]);

        $user = $request->user();
        $wsId = (int) ($user->current_workspace_id ?? 0);

        // Plan cap — same workspace-scoped UNIFIED count the web uses
        // (Baileys devices + WABA/Twilio configs). Throws when the
        // workspace is at its plan limit.
        try {
            \App\Services\PlanLimitGuard::check(
                $user->currentWorkspace,
                'device_limit',
                Device::query()->forCurrentWorkspace()->count()
                    + \App\Models\WaProviderConfig::query()
                        ->where('workspace_id', $wsId)
                        ->whereIn('engine', ['waba', 'twilio'])
                        ->count(),
            );
        } catch (\App\Exceptions\PlanLimitReachedException $e) {
            return response()->json([
                'success' => false,
                'error'   => 'plan_limit',
                'message' => $e->getMessage() ?: 'Device limit reached on your plan.',
            ], 402);
        }

        // Normalise: country_code stored as bare digits ("91", not "+91"),
        // phone_number stored as bare local part with cc prefix stripped
        // once. Same normaliser the web uses to avoid double-prefix bugs.
        $cc    = preg_replace('/\D+/', '', (string) $data['country_code']);
        $local = preg_replace('/\D+/', '', (string) $data['phone_number']);
        if ($cc !== '' && str_starts_with($local, $cc)) {
            $local = substr($local, strlen($cc));
        }
        if ($cc === '' || $local === '') {
            return response()->json(['success' => false, 'message' => 'Invalid country_code or phone_number.'], 422);
        }
        $full = $cc . $local;

        // Phone uniqueness in PHP — encrypted column can't be SQL-unique.
        $taken = Device::query()->forCurrentWorkspace()->get(['id', 'phone_number', 'country_code'])
            ->first(fn ($d) => preg_replace('/\D+/', '', $d->country_code . $d->phone_number) === $full);
        if ($taken) {
            return response()->json([
                'success'     => false,
                'message'     => 'A device with that phone number already exists.',
                'existing_id' => $taken->id,
            ], 422);
        }

        $device = Device::create([
            'user_id'                => $user->id,
            'assigned_user_id'       => $user->id,
            'workspace_id'           => $wsId ?: null,
            'device_name'            => $data['device_name'],
            'country_code'           => $cc,
            'phone_number'           => $local,
            'region'                 => $data['region'] ?? null,
            'status'                 => 'disconnected',
            'active'                 => false,
            'activate_after_pairing' => (bool) ($data['activate_after_pairing'] ?? true),
        ]);

        // Kick off Node pairing immediately so the app can poll QR/status.
        $qr     = $this->bridgeInitialize($full);
        $status = $qr['status'] ?? 'pending';

        return response()->json([
            'success' => true,
            'message' => 'Device created — scan the QR or use the pairing code to connect.',
            'data'    => [
                'device'    => $this->devicePayload($device),
                'qr'        => $qr['qr'] ?? null,        // base64 data URL or raw SVG depending on Node
                'status'    => $status,                  // 'qr_ready' | 'connected' | 'pending'
                'phone'     => $full,
                'qr_url'        => "/api/app/devices/{$device->id}/qr",
                'pair_code_url' => "/api/app/devices/{$device->id}/pair-code",
                'status_url'    => "/api/app/device-status/{$device->id}",
            ],
        ], 201);
    }

    // -----------------------------------------------------------------
    // GET /devices/{id}/qr — fetch / refresh the QR data URL for a
    // device currently in pairing. The Node bridge re-uses an existing
    // QR if one is in flight; we terminate stale sessions first when
    // the device is not connected so a fresh socket is built.
    // -----------------------------------------------------------------
    public function qr(Request $request, int $id): JsonResponse
    {
        $device = Device::query()->forCurrentWorkspace()->find($id);
        if (! $device) return response()->json(['success' => false, 'message' => 'Device not found.'], 404);
        $phone  = $this->fullPhone($device);
        $body   = $this->bridgeInitialize($phone, $device->status !== 'connected');
        return response()->json([
            'success' => true,
            'data'    => [
                'qr'     => $body['qr']     ?? null,
                'status' => $body['status'] ?? 'pending',
                'phone'  => $phone,
            ],
        ]);
    }

    // -----------------------------------------------------------------
    // GET /devices/{id}/pair-code — request an 8-digit pairing code
    // (alternative to QR for users who can't scan).
    // -----------------------------------------------------------------
    public function pairCode(Request $request, int $id): JsonResponse
    {
        $device = Device::query()->forCurrentWorkspace()->find($id);
        if (! $device) return response()->json(['success' => false, 'message' => 'Device not found.'], 404);
        $phone  = $this->fullPhone($device);
        $base   = $this->nodeBaseUrl();
        if ($base === '') {
            return response()->json(['success' => false, 'message' => 'Node bridge URL is not configured.'], 500);
        }
        try {
            $r = Http::timeout(15)->acceptJson()
                ->get(rtrim($base, '/') . '/api/get-pairing-code/' . urlencode($phone));
            $j = $r->json() ?: [];
            return response()->json([
                'success' => $r->successful(),
                'data'    => [
                    'code'   => $j['code'] ?? $j['pairing_code'] ?? null,
                    'phone'  => $phone,
                    'status' => $j['status'] ?? null,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 502);
        }
    }

    // -----------------------------------------------------------------
    // POST /devices/{id}/disconnect — terminate the Baileys session on
    // Node so the device shows as disconnected. Keeps the local row so
    // the user can re-pair later without re-entering the number.
    // -----------------------------------------------------------------
    public function disconnect(Request $request, int $id): JsonResponse
    {
        $device = Device::query()->forCurrentWorkspace()->find($id);
        if (! $device) return response()->json(['success' => false, 'message' => 'Device not found.'], 404);
        $phone  = $this->fullPhone($device);
        $base   = $this->nodeBaseUrl();
        if ($base !== '') {
            try {
                Http::timeout(10)->acceptJson()
                    ->get(rtrim($base, '/') . '/api/terminate-client/' . urlencode($phone));
            } catch (\Throwable $e) {
                Log::warning('[App\Device] disconnect Node call failed', ['device' => $id, 'err' => $e->getMessage()]);
            }
        }
        $device->forceFill(['active' => false, 'status' => 'disconnected'])->save();
        return response()->json([
            'success' => true,
            'message' => 'Device disconnected.',
            'data'    => $this->devicePayload($device->refresh()),
        ]);
    }

    // -----------------------------------------------------------------
    // DELETE /devices/{id} — disconnect AND remove the device row.
    // Closes any open conversations attached to it so the team inbox
    // doesn't keep showing dead threads as live.
    // -----------------------------------------------------------------
    public function destroy(Request $request, int $id): JsonResponse
    {
        $device = Device::query()->forCurrentWorkspace()->find($id);
        if (! $device) return response()->json(['success' => false, 'message' => 'Device not found.'], 404);

        $phone = $this->fullPhone($device);
        $base  = $this->nodeBaseUrl();
        if ($base !== '') {
            try {
                Http::timeout(10)->acceptJson()
                    ->get(rtrim($base, '/') . '/api/terminate-client/' . urlencode($phone));
            } catch (\Throwable $e) {
                Log::warning('[App\Device] destroy Node call failed', ['device' => $id, 'err' => $e->getMessage()]);
            }
        }

        // Close conversations attached to this device — history stays
        // readable but the thread drops out of the active queue.
        \App\Models\Conversation::where('device_id', $device->id)
            ->whereIn('inbox_status', ['open', 'pending', 'snoozed'])
            ->update(['inbox_status' => 'closed', 'resolved_at' => now()]);

        $device->delete();
        return response()->json(['success' => true, 'message' => 'Device removed.', 'data' => ['id' => $id]]);
    }

    // =================================================================
    // Helpers — Node bridge URL + initialize call
    // =================================================================

    /** Read the Node bridge URL from SystemSetting first, env second. */
    private function nodeBaseUrl(): string
    {
        return (string) (\App\Models\SystemSetting::get('baileys_server_url', '') ?: env('SERVER_URL', ''));
    }

    /**
     * Call POST /api/initialize-client on the Node bridge to start (or
     * re-start) the Baileys session for this phone. When
     * `terminateFirst` is true we hit /api/terminate-client first so a
     * fresh socket + new QR is generated for a stale session.
     */
    private function bridgeInitialize(string $phone, bool $terminateFirst = false): array
    {
        $base = $this->nodeBaseUrl();
        if ($base === '' || $phone === '') {
            return ['qr' => null, 'status' => 'pending'];
        }
        try {
            if ($terminateFirst) {
                try {
                    Http::timeout(8)->acceptJson()
                        ->get(rtrim($base, '/') . '/api/terminate-client/' . urlencode($phone));
                } catch (\Throwable $e) { /* best-effort */ }
            }
            $r = Http::timeout(15)->acceptJson()
                ->get(rtrim($base, '/') . '/api/initialize-client/' . urlencode($phone));
            return $r->json() ?: ['qr' => null, 'status' => 'pending'];
        } catch (\Throwable $e) {
            Log::warning('[App\Device] bridgeInitialize failed', ['phone' => $phone, 'err' => $e->getMessage()]);
            return ['qr' => null, 'status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Shape one Device row into the app's device payload. Keeps the old
     * keys (phone_number, device_name, active) and adds the columns the
     * batch lists. device_name + phone_number are decrypted by the model
     * accessor before they reach here.
     */
    private function devicePayload(Device $d): array
    {
        return [
            'id'               => $d->id,
            'device_name'      => (string) ($d->device_name ?? ''),
            'country_code'     => $d->country_code ?: null,
            'phone_number'     => $this->fullPhone($d),
            'region'           => $d->region ?: null,
            'active'           => (int) ((bool) $d->active),
            'status'           => $d->status,
            'last_seen_at'     => optional($d->last_seen_at)->toIso8601String(),
            'assigned_user_id' => $d->assigned_user_id,
        ];
    }

    /**
     * Resolve a device by id OR bare phone number, always scoped to the
     * current workspace so a user can never read another workspace's
     * device. The route param is numeric for an id but may be a phone
     * number on legacy app builds — handle both.
     */
    private function resolveDevice($key): ?Device
    {
        $key = (string) $key;

        // Numeric + short → treat as a row id first.
        if (ctype_digit($key) && strlen($key) <= 12) {
            $byId = Device::query()->forCurrentWorkspace()->find((int) $key);
            if ($byId) {
                return $byId;
            }
        }

        // Otherwise match on the full digits-only phone number. Phone is
        // encrypted-at-rest so we can't WHERE on ciphertext — hydrate the
        // workspace's devices and compare in PHP.
        $target = preg_replace('/\D+/', '', $key);
        if ($target === '') {
            return null;
        }

        return Device::query()
            ->forCurrentWorkspace()
            ->get()
            ->first(fn (Device $d) => $this->fullPhone($d) === $target);
    }

    /** Full digits-only E.164 phone (country code + national number). */
    private function fullPhone(Device $d): string
    {
        return preg_replace('/\D+/', '', (string) ($d->country_code . $d->phone_number)) ?: '';
    }

    /**
     * Derive a 0-100 progress value from the device status so the old
     * polling shape ({status, progress}) keeps working. We don't persist
     * a `progress` column, so this is a stable mapping, not live telemetry.
     */
    private function progressFor(Device $d): int
    {
        return match ($d->status) {
            'connected'  => 100,
            'needs_pair' => 30,
            'failed'     => 0,
            default      => $d->active ? 100 : 0,
        };
    }

    /**
     * Best-effort live contact fetch from the Node bridge, reshaped to the
     * app's contact rows. Returns null (not an error) when no bridge is
     * configured or the call fails, so the caller falls back to stored
     * contacts. Mirrors the old getDeviceContacts proxy + formatting.
     */
    private function fetchLiveContacts(string $phone): ?array
    {
        if ($phone === '' || ! function_exists('wd_node_url')) {
            return null;
        }

        $base = '';
        try {
            $base = (string) wd_node_url();
        } catch (\Throwable $e) {
            $base = '';
        }
        if ($base === '') {
            return null;
        }

        try {
            $res = Http::timeout(15)->acceptJson()
                ->get(rtrim($base, '/') . '/api/get-contacts/' . urlencode($phone));
            if (! $res->successful()) {
                return null;
            }
            $data = $res->json() ?: [];
        } catch (\Throwable $e) {
            return null;
        }

        $rawContacts = $data['contacts'] ?? null;
        if (! is_array($rawContacts)) {
            return null;
        }

        $bridgePhone = (string) ($data['phoneNumber'] ?? $phone);
        $formatted = [];
        foreach ($rawContacts as $waId => $contact) {
            $contact = is_array($contact) ? $contact : [];
            $number  = str_replace('@s.whatsapp.net', '', (string) $waId);

            $formatted[] = [
                'name'        => (string) ($contact['name'] ?? ''),
                'number'      => $number,
                'pushname'    => (string) ($contact['verifiedName'] ?? ''),
                'isMyContact' => ($number === $bridgePhone),
                'avatar'      => $contact['profilePicUrl'] ?? null,
            ];
        }

        $data['contacts']    = $formatted;
        $data['phoneNumber'] = $bridgePhone;
        $data['source']      = 'bridge';

        return $data;
    }
}
