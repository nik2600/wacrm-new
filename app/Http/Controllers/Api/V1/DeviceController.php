<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\DeviceResource;
use App\Models\Device;
use App\Services\WorkspaceEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Devices — read the workspace's connected WhatsApp numbers.
 *
 * Reuses the same Device::forCurrentWorkspace() scope the mobile-app
 * DeviceController and the web DevicesController query. Each row is stamped
 * with the workspace's messaging engine (the devices table has no per-row
 * provider column) before being wrapped in the public { data } envelope.
 */
class DeviceController extends V1Controller
{
    /** GET /api/v1/devices — list every channel the workspace owns. */
    public function index(Request $request): JsonResponse
    {
        $wsId = $this->workspaceId();

        // Unofficial-API channels: every row in the `devices` table IS the
        // QR / Unofficial (Baileys) engine — NOT the workspace default. Stamping
        // the workspace default here mislabeled QR numbers as twilio/waba.
        $rows = Device::query()
            ->forCurrentWorkspace()
            ->orderByDesc('active')
            ->orderByDesc('id')
            ->get()
            ->map(function (Device $d) {
                $d->engine = 'baileys';
                return (new DeviceResource($d))->resolve();
            })
            ->all();

        // Meta (WABA) + Twilio channels live in wa_provider_configs — list them
        // alongside, each with its OWN engine, so /api/v1/devices mirrors the
        // multi-engine "My channels" page (Unofficial + WABA + Twilio).
        $configs = \App\Models\WaProviderConfig::query()
            ->where('workspace_id', $wsId)
            ->whereIn('provider', ['waba', 'twilio'])
            ->orderByDesc('is_primary')
            ->orderByDesc('id')
            ->get()
            ->map(function (\App\Models\WaProviderConfig $c) {
                $meta      = is_array($c->meta_json) ? $c->meta_json : [];
                $connected = $c->status === \App\Models\WaProviderConfig::STATUS_CONNECTED;
                return [
                    'id'           => 'cfg_' . $c->id,
                    'name'         => $c->display_label ?: ($meta['verified_name'] ?? ucfirst((string) $c->provider)),
                    'phone'        => preg_replace('/\D+/', '', (string) $c->phone_number) ?: null,
                    'engine'       => $c->provider === 'twilio' ? 'twilio' : 'whatsapp_cloud',
                    'status'       => $connected ? 'connected' : 'disconnected',
                    'active'       => $connected,
                    'last_seen_at' => optional($c->connected_at)->toIso8601String(),
                ];
            })
            ->all();

        $all = array_values(array_merge($rows, $configs));
        return $this->ok($all, ['count' => count($all)]);
    }

    /** GET /api/v1/devices/{id} — one device. */
    public function show(int $id): JsonResponse
    {
        $device = Device::query()->forCurrentWorkspace()->find($id);
        if (!$device) {
            return $this->fail('not_found', 'Device not found.', 404);
        }

        // A `devices` row is always the Unofficial (Baileys/QR) channel.
        $device->engine = 'baileys';

        return $this->ok((new DeviceResource($device))->resolve());
    }
}
