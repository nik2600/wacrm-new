<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\App\CampaignController as AppCampaignController;
use App\Http\Controllers\WaCampaignsController;
use App\Http\Requests\Api\V1\Campaign\StoreCampaignRequest;
use App\Http\Resources\Api\V1\CampaignResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Campaigns — create + manage multi-recipient WhatsApp campaigns (template /
 * custom / flow, optional A/B testing) for the current workspace.
 *
 * Reuses the tested mobile-app pipeline (App\Http\Controllers\Api\App\
 * CampaignController → WpCampaign + WpCampaignContact, workspace-scoped) and
 * re-wraps every result in the public { data } / { error } envelope.
 */
class CampaignController extends V1Controller
{
    /** GET /api/v1/campaigns — list the workspace's campaigns. */
    public function index(Request $request): JsonResponse
    {
        $internal = Request::create('/api/app/campaigns', 'GET', $request->query());
        $internal->setUserResolver(fn () => $request->user());

        $payload = app(AppCampaignController::class)->index($internal)->getData(true);

        if (($payload['success'] ?? false) !== true) {
            return $this->fail('list_failed', $payload['message'] ?? 'Campaigns could not be listed.', 422);
        }

        $items = collect($payload['data'] ?? [])
            ->map(fn ($c) => (new CampaignResource($c))->resolve())
            ->values();

        return $this->ok($items, ['count' => $items->count()]);
    }

    /**
     * POST /api/v1/campaigns — create a campaign and dispatch it.
     *
     * Routes through the web WaCampaignsController::store — the SINGLE real
     * dispatch path. `now` campaigns fire immediately; `scheduled`/`recurring`
     * are armed for the heartbeat sweeper. (The mobile-app controller only
     * persists rows and never dispatches, so we deliberately don't use it.)
     * The public `schedule_type=later` maps to the web vocabulary `scheduled`,
     * and recipient phone numbers ride in as `manual_numbers` — the web path
     * materialises a workspace Contact for each, so sends are always scoped to
     * the caller's own workspace.
     */
    public function store(StoreCampaignRequest $request): JsonResponse
    {
        $scheduleMap = ['now' => 'now', 'later' => 'scheduled', 'recurring' => 'recurring'];

        // The public `device_id` accepts BOTH a bare Baileys `devices` id
        // (integer) AND the `cfg_<n>` id that GET /api/v1/devices reports for a
        // WABA / Twilio (Cloud API) sender. The web WaCampaignsController wants
        // an INTEGER device_id (Baileys) OR a composite `engine:id` `sender`
        // key (WABA/Twilio). Without translating here, a Cloud number's
        // "cfg_4" id hits the web validator's `device_id => integer` rule and
        // 422s with "The device id field must be an integer".
        $rawDevice = trim((string) $request->input('device_id'));
        $deviceId  = null;   // bare Baileys devices id
        $senderKey = null;   // engine:id composite for WABA / Twilio
        if ($rawDevice !== '') {
            if (preg_match('/^cfg[_-]?(\d+)$/i', $rawDevice, $m)) {
                $cfg = \App\Models\WaProviderConfig::query()
                    ->where('workspace_id', $this->workspaceId())
                    ->whereKey((int) $m[1])
                    ->first();
                if ($cfg) {
                    $senderKey = $cfg->provider . ':' . $cfg->id;   // e.g. "waba:4"
                }
            } elseif (ctype_digit($rawDevice)) {
                $deviceId = (int) $rawDevice;
            }
        }

        $params = [
            'campaign_name'  => $request->input('campaign_name'),
            'device_id'      => $deviceId,
            'sender'         => $senderKey,
            'campaign_type'  => $request->input('campaign_type'),
            'schedule_type'  => $scheduleMap[$request->input('schedule_type')] ?? 'now',
            'template_id'    => $request->input('template_id'),
            'template_id_a'  => $request->input('template_id_a'),
            'template_id_b'  => $request->input('template_id_b'),
            'flow_id'        => $request->input('flow_id'),
            'custom_message' => $request->input('custom_message'),
            'send_date'      => $request->input('send_date'),
            'send_time'      => $request->input('send_time'),
            'timezone'       => $request->input('timezone'),
            'ab_testing'     => $request->boolean('ab_testing'),
            'ab_split'       => $request->input('ab_split'),
            // Public API supplies international phone numbers; the web path
            // turns each into a workspace Contact via manual_numbers.
            'manual_numbers' => implode("\n", array_map(
                fn ($p) => preg_replace('/[^0-9+]/', '', (string) $p),
                (array) $request->input('contacts', [])
            )),
        ];

        $internal = Request::create('/wa-campaigns', 'POST', $params, [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $internal->setUserResolver(fn () => $request->user());

        try {
            $response = app(WaCampaignsController::class)->store($internal);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->fail('create_failed', 'Campaign validation failed.', 422, $e->errors());
        } catch (\App\Exceptions\PlanLimitReachedException $e) {
            return $this->fail('plan_limit', $e->getMessage() ?: 'Campaign plan limit reached.', 402);
        }

        // Success returns a JsonResponse {ok:true, campaign}. Business-rule
        // rejections (template not approved, auth template, etc.) come back as
        // a RedirectResponse carrying the errors in the session bag.
        if ($response instanceof JsonResponse) {
            $payload = $response->getData(true);
            if (($payload['ok'] ?? false) === true) {
                return $this->created((new CampaignResource($payload['campaign'] ?? []))->resolve());
            }
            return $this->fail('create_failed', $payload['message'] ?? 'Campaign could not be created.', 422);
        }

        $errors  = [];
        $message = 'Campaign could not be created.';
        if (method_exists($response, 'getSession') && $response->getSession()) {
            $session = $response->getSession();
            if ($bag = $session->get('errors')) {
                $errors  = method_exists($bag, 'getMessages') ? $bag->getMessages() : [];
                $first   = collect($errors)->flatten()->first();
                $message = $first ?: ($session->get('error') ?: $message);
            } elseif ($session->get('error')) {
                $message = (string) $session->get('error');
            }
        }

        return $this->fail('create_failed', $message, 422, $errors);
    }

    /** GET /api/v1/campaigns/{id} — one campaign with counts + metrics. */
    public function show(int $id): JsonResponse
    {
        $internal = Request::create('/api/app/campaigns/' . $id, 'GET');
        $internal->setUserResolver(fn () => request()->user());

        $payload = app(AppCampaignController::class)->show($internal, $id)->getData(true);

        if (($payload['success'] ?? false) !== true) {
            return $this->fail('not_found', $payload['message'] ?? 'Campaign not found.', 404);
        }

        $data = $payload['data'] ?? [];

        return $this->ok((new CampaignResource($data['campaign'] ?? []))->resolve(), [
            'counts'  => $data['counts'] ?? [],
            'metrics' => $data['raw_metrics'] ?? [],
        ]);
    }

    /** POST /api/v1/campaigns/{id}/stop — stop a running / scheduled campaign. */
    public function stop(int $id): JsonResponse
    {
        $internal = Request::create('/api/app/campaigns/' . $id . '/stop', 'POST');
        $internal->setUserResolver(fn () => request()->user());

        $payload = app(AppCampaignController::class)->stop($internal, $id)->getData(true);

        if (($payload['success'] ?? false) !== true) {
            $status = ($payload['message'] ?? '') === 'Campaign not found' ? 404 : 422;
            return $this->fail(
                $status === 404 ? 'not_found' : 'not_stoppable',
                $payload['message'] ?? 'Campaign could not be stopped.',
                $status
            );
        }

        return $this->ok((new CampaignResource($payload['data'] ?? []))->resolve());
    }

    /** DELETE /api/v1/campaigns/{id} — delete a scheduled / failed / completed campaign. */
    public function destroy(int $id): JsonResponse
    {
        $internal = Request::create('/api/app/campaigns/' . $id, 'DELETE');
        $internal->setUserResolver(fn () => request()->user());

        $payload = app(AppCampaignController::class)->destroy($internal, $id)->getData(true);

        if (($payload['success'] ?? false) !== true) {
            $status = ($payload['message'] ?? '') === 'Campaign not found' ? 404 : 422;
            return $this->fail(
                $status === 404 ? 'not_found' : 'delete_failed',
                $payload['message'] ?? 'Campaign could not be deleted.',
                $status
            );
        }

        return $this->ok(['deleted' => true, 'message' => $payload['message'] ?? 'Campaign deleted successfully']);
    }

    /** GET /api/v1/campaigns/statistics — workspace-wide rollup. */
    public function statistics(Request $request): JsonResponse
    {
        $internal = Request::create('/api/app/campaigns/statistics', 'GET');
        $internal->setUserResolver(fn () => $request->user());

        $payload = app(AppCampaignController::class)->statistics($internal)->getData(true);

        if (($payload['success'] ?? false) !== true) {
            return $this->fail('stats_failed', $payload['message'] ?? 'Statistics could not be fetched.', 422);
        }

        return $this->ok($payload['data'] ?? []);
    }
}
