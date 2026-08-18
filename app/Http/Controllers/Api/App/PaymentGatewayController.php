<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\PaymentGateway;
use App\Services\Payment\PaymentGatewayManager;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Admin Payment-Gateway API (B12).
 *
 * Mobile/web admin app counterpart to /admin/payment-gateways. Lets an
 * admin pass **ALL** credential keys for every installed gateway —
 * public AND private (secret) — without going through the web form.
 * Credentials are JSON-blob encrypted at rest on PaymentGateway.credentials
 * via Crypt::encryptString.
 *
 * Security model:
 *   - Admin-role only (gate inside `requireAdmin()`).
 *   - Listing NEVER returns the decrypted credential values. It returns
 *     the field schema + a `set: true|false` flag per key so the app can
 *     show "API secret: ★★★★ configured" without ever shipping the
 *     actual ciphertext or plaintext over the wire.
 *   - Activating a gateway is refused unless every `required` field is set.
 *
 * Endpoints (mounted at /api/app/admin/payment-gateways):
 *   GET    /                          → list all gateways + schemas + which keys are set
 *   GET    /{id}                      → one gateway, same shape
 *   PATCH  /{id}                      → set keys + mode + sort + supported currencies
 *   POST   /{id}/toggle               → activate / deactivate
 */
class PaymentGatewayController extends Controller
{
    public function __construct(private readonly PaymentGatewayManager $manager)
    {
    }

    // -----------------------------------------------------------------
    // GET /admin/payment-gateways
    // -----------------------------------------------------------------
    public function index(Request $request): JsonResponse
    {
        if ($err = $this->requireAdmin($request)) return $err;

        $rows = PaymentGateway::orderBy('sort_order')->orderBy('name')->get();
        // Admin endpoint always returns decrypted credentials so the admin
        // app can pre-fill edit forms without needing a separate reveal call.
        // Caller is already authenticated as admin (requireAdmin gate above);
        // the audit log below records every list-with-secrets call.
        $payload = $rows->map(fn (PaymentGateway $g) => $this->present($g, true))->values();

        Audit::log('admin.payment_gateway.api_list_with_secrets', [
            'meta' => [
                'count' => $payload->count(),
                'gateway_slugs' => $rows->pluck('slug')->all(),
                'via' => 'api',
            ],
        ]);

        return response()->json([
            'success' => true,
            'data'    => $payload,
            'total'   => $payload->count(),
            'currencies' => Currency::active()->orderBy('code')->get(['code', 'name'])->values(),
        ]);
    }

    // -----------------------------------------------------------------
    // GET /admin/payment-gateways/{id}
    //
    // Returns the gateway including the decrypted credentials in
    // `credentials_plain` so the admin app can pre-fill the edit form
    // without a separate reveal call. Caller is already authenticated
    // as admin (requireAdmin gate); every call is audit-logged so the
    // exposure trail is preserved for SOC2 / ISO / GDPR.
    //
    // Query:
    //   reveal=0  → suppress `credentials_plain` (use when the admin
    //               just needs the schema / mode / on-off state).
    // -----------------------------------------------------------------
    public function show(Request $request, int $id): JsonResponse
    {
        if ($err = $this->requireAdmin($request)) return $err;

        $g = PaymentGateway::find($id);
        if (! $g) return response()->json(['success' => false, 'message' => 'Gateway not found.'], 404);

        // Default reveal=true; pass ?reveal=0 to suppress secrets.
        $reveal = filter_var($request->query('reveal', '1'), FILTER_VALIDATE_BOOLEAN);
        $payload = $this->present($g, $reveal);

        if ($reveal) {
            Audit::log('admin.payment_gateway.api_credentials_revealed', [
                'resource' => $g,
                'meta'     => [
                    'gateway' => $g->slug,
                    'keys'    => array_keys($g->getDecryptedCredentials()),
                    'via'     => 'api',
                ],
            ]);
        }

        return response()->json(['success' => true, 'data' => $payload]);
    }

    // -----------------------------------------------------------------
    // PATCH /admin/payment-gateways/{id}
    //
    // Body:
    //   mode                   (string, optional)  sandbox | live
    //   sort_order             (int, optional)
    //   supported_currencies   (string[], optional) ISO codes — empty = accept all
    //   credentials            (object, optional)  { "<key>": "<value>", ... }
    //                                              Only non-empty values overwrite — pass
    //                                              "" to KEEP the existing secret unchanged
    //                                              (same semantics as the web form).
    // -----------------------------------------------------------------
    public function update(Request $request, int $id): JsonResponse
    {
        if ($err = $this->requireAdmin($request)) return $err;

        $g = PaymentGateway::find($id);
        if (! $g) return response()->json(['success' => false, 'message' => 'Gateway not found.'], 404);

        $validator = Validator::make($request->all(), [
            'mode'                   => 'nullable|in:sandbox,live',
            'sort_order'             => 'nullable|integer|min:0',
            'supported_currencies'   => 'nullable|array',
            'supported_currencies.*' => 'string|max:10',
            'credentials'            => 'nullable|array',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }
        $data = $validator->validated();

        $fields    = $this->manager->credentialFieldsFor($g->slug);   // [key => spec]
        $existing  = $g->getDecryptedCredentials();
        $incoming  = $data['credentials'] ?? [];

        // Merge: only overwrite a key when the request submitted a NON-EMPTY
        // value. Pass "" to keep the existing secret (matches the web form's
        // password placeholder UX — admins can edit OTHER fields without
        // resubmitting every key every time).
        $merged = $existing;
        $accepted = [];
        $unknown  = [];
        foreach ($incoming as $key => $value) {
            if (! is_string($key) || $key === '') continue;
            if (! array_key_exists($key, $fields)) {
                // Reject unknown keys — driver schema is the source of truth.
                $unknown[] = $key;
                continue;
            }
            if ($value === '' || $value === null) continue; // keep existing
            $merged[$key] = is_scalar($value) ? (string) $value : json_encode($value);
            $accepted[] = $key;
        }
        $g->setEncryptedCredentials($merged);

        if (isset($data['mode']))                  $g->mode = $data['mode'];
        if (isset($data['sort_order']))            $g->sort_order = $data['sort_order'];
        if (array_key_exists('supported_currencies', $data)) {
            $g->supported_currencies = $data['supported_currencies'] ?? [];
        }
        $g->save();

        // Audit — record WHICH credential keys changed (never the values).
        Audit::log('admin.payment_gateway.api_updated', [
            'resource' => $g,
            'meta'     => [
                'mode'                 => $g->mode,
                'credential_keys_set'  => $accepted,
                'unknown_keys'         => $unknown,
                'supported_currencies' => $g->supported_currencies ?? [],
                'via'                  => 'api',
            ],
        ]);

        return response()->json([
            'success'         => true,
            'message'         => $g->name . ' settings saved.',
            'data'            => $this->present($g->fresh(), true),
            'accepted_keys'   => $accepted,
            'ignored_unknown' => $unknown,
        ]);
    }

    // -----------------------------------------------------------------
    // POST /admin/payment-gateways/{id}/toggle
    // -----------------------------------------------------------------
    public function toggle(Request $request, int $id): JsonResponse
    {
        if ($err = $this->requireAdmin($request)) return $err;

        $g = PaymentGateway::find($id);
        if (! $g) return response()->json(['success' => false, 'message' => 'Gateway not found.'], 404);

        // Refuse to activate without the required credentials — same rule
        // the web /admin/payment-gateways form enforces.
        if (! $g->is_active) {
            $required = collect($this->manager->credentialFieldsFor($g->slug))
                ->filter(fn ($spec) => ! empty($spec['required']))->keys();
            $creds   = $g->getDecryptedCredentials();
            $missing = $required->filter(fn ($k) => empty($creds[$k]))->values()->all();
            if (! empty($missing)) {
                Audit::log('admin.payment_gateway.toggle_blocked', [
                    'resource' => $g,
                    'result'   => 'failure',
                    'meta'     => ['missing_keys' => $missing, 'via' => 'api'],
                ]);
                return response()->json([
                    'success'      => false,
                    'message'      => 'Configure required fields first.',
                    'missing_keys' => $missing,
                ], 422);
            }
        }

        $g->update(['is_active' => ! $g->is_active]);
        Audit::log($g->is_active ? 'admin.payment_gateway.activated' : 'admin.payment_gateway.deactivated', [
            'resource' => $g,
            'meta'     => ['via' => 'api'],
        ]);

        return response()->json([
            'success' => true,
            'message' => $g->is_active ? $g->name . ' activated.' : $g->name . ' deactivated.',
            'data'    => $this->present($g->fresh(), true),
        ]);
    }

    // =================================================================
    // Helpers
    // =================================================================

    /** Admin-role gate. Returns null on success, a 403 JsonResponse on fail. */
    private function requireAdmin(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (! $user) return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        // Match the rest of the codebase's admin check (Spatie role OR role string).
        $isAdmin = strtolower((string) ($user->role ?? '')) === 'admin'
            || (method_exists($user, 'hasRole') && $user->hasRole('Admin'));
        if (! $isAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Admin role required.',
            ], 403);
        }
        return null;
    }

    /**
     * Shape one gateway. By default credentials stay server-side and the
     * response carries only `credentials_set: { key: bool }` so the admin
     * app can render the form without ever seeing the secret values. When
     * `$reveal` is true (opt-in via `?reveal=1` on the SHOW endpoint),
     * the decrypted credentials are also returned under `credentials_plain`
     * so the app can pre-fill the edit form — the show() handler audits
     * every such call.
     */
    private function present(PaymentGateway $g, bool $reveal = false): array
    {
        $fields = $this->manager->credentialFieldsFor($g->slug);
        $creds  = $g->getDecryptedCredentials();

        // Mark "set" for any key the schema declares; preserve unknown keys
        // (legacy data) under `extra_set` so the admin can see + clean them.
        $setMap = [];
        foreach ($fields as $key => $_spec) {
            $setMap[$key] = ! empty($creds[$key]);
        }
        $extraSet = [];
        foreach ($creds as $key => $value) {
            if (! array_key_exists($key, $fields)) {
                $extraSet[$key] = ! empty($value);
            }
        }

        $payload = [
            'id'                   => $g->id,
            'slug'                 => $g->slug,
            'name'                 => $g->name,
            'description'          => $g->description,
            'is_active'            => (bool) $g->is_active,
            'mode'                 => (string) ($g->mode ?: 'sandbox'),
            'sort_order'           => (int) ($g->sort_order ?? 0),
            'supported_currencies' => $g->supported_currencies ?? [],
            'credential_fields'    => $fields,          // [key => {label, type, required, ...}]
            'credentials_set'      => $setMap,          // [key => bool]   ← NO values
            'extra_credentials_set'=> $extraSet,        // legacy keys outside schema
            'updated_at'           => $g->updated_at?->toIso8601String(),
        ];

        if ($reveal) {
            // Decrypted credentials, including the secret/private values. ONLY
            // returned when the caller explicitly passed ?reveal=1 — the show()
            // handler audit-logs every such call so reveals leave a trail.
            // List + update + toggle never set this flag.
            $payload['credentials_plain'] = $creds;
        }

        return $payload;
    }
}
