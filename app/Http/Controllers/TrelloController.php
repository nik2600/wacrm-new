<?php

namespace App\Http\Controllers;

use App\Models\TrelloIntegration;
use App\Models\TrelloIntegrationLog;
use App\Models\SystemSetting;
use App\Services\Integrations\TrelloService;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * User-side Trello integration: connect a board (paste API key + secret +
 * token, pick a board) so card assignments / changes fire WhatsApp
 * notifications. On connect we register a Trello webhook on the board.
 */
class TrelloController extends Controller
{
    public function index(Request $request): View
    {
        $wsId        = (int) (Auth::user()?->current_workspace_id ?? 0);
        $integration = TrelloIntegration::where('workspace_id', $wsId)->first();
        $enabled     = (bool) SystemSetting::get('trello_enabled', true);
        $logs        = $integration
            ? TrelloIntegrationLog::where('integration_id', $integration->id)->latest()->limit(20)->get()
            : collect();

        // Connected senders across every enabled engine so the operator can pick
        // WHICH number card notifications send from (multi-device). key="engine:id".
        $senders = \App\Services\WorkspaceEngine::senders($wsId)->map(fn ($s) => [
            'key'    => $s['key'],
            'label'  => $s['label'],
            'phone'  => $s['phone'],
            'engine' => $s['descriptor']['label'] ?? ($s['engine'] ?? ''),
        ])->values();

        return view('user.trello.dashboard', [
            'integration'   => $integration,
            'enabled'       => $enabled,
            'logs'          => $logs,
            'callbackUrl'   => url('/webhooks/trello'),
            'senders'       => $senders,
            'currentSender' => $integration?->sendFrom() ?? '',
        ]);
    }

    public function connect(Request $request, TrelloService $trello): RedirectResponse
    {
        if (!(bool) SystemSetting::get('trello_enabled', true)) {
            return back()->withErrors(['trello' => __('Trello integration is currently disabled by the platform admin.')]);
        }

        $data = $request->validate([
            'api_key'       => 'required|string|max:120',
            'api_secret'    => 'required|string|max:191',
            'token'         => 'required|string|max:191',
            'board'         => 'required|string|max:255',   // board id, shortLink, or URL
            'events'        => 'nullable|array',
            'events.*'      => 'string|max:40',
            'notify_mode'   => 'nullable|in:assignee,fixed',
            'notify_number' => 'nullable|string|max:32',
            // Chosen sending channel — a WorkspaceEngine sender key ("engine:id").
            'send_from'     => 'nullable|string|max:64',
        ]);

        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        $uId  = (int) Auth::id();
        if (!$wsId) return back()->withErrors(['trello' => __('No active workspace.')]);

        // Validate token.
        if (!$trello->me($data['api_key'], $data['token'])) {
            return back()->withErrors(['token' => __('Trello rejected this API key + token. Generate a token with read scope and try again.')])->withInput();
        }

        // Resolve the board (accepts id, shortLink, or a board URL).
        $ref   = $this->boardRef($data['board']);
        $board = $trello->board($data['api_key'], $data['token'], $ref);
        if (!$board || empty($board['id'])) {
            return back()->withErrors(['board' => __('Could not find that board with this token. Check the board ID/URL and that the token can access it.')])->withInput();
        }
        $boardId = (string) $board['id'];

        $events = array_values(array_intersect(
            $data['events'] ?? TrelloIntegration::DEFAULT_EVENTS,
            TrelloIntegration::DEFAULT_EVENTS
        ));

        // Preserve any existing metadata while stamping the chosen sender.
        $existing = TrelloIntegration::where('workspace_id', $wsId)->where('board_id', $boardId)->first();

        $integration = TrelloIntegration::updateOrCreate(
            ['workspace_id' => $wsId, 'board_id' => $boardId],
            [
                'user_id'       => $uId,
                'api_key'       => $data['api_key'],
                'api_secret'    => $data['api_secret'],
                'token'         => $data['token'],
                'board_name'    => $board['name'] ?? null,
                'events'        => $events ?: TrelloIntegration::DEFAULT_EVENTS,
                'notify_mode'   => $data['notify_mode'] ?? 'assignee',
                'notify_number' => $data['notify_number'] ?? null,
                'metadata'      => \App\Services\Integrations\SenderResolver::stampMetadata($existing?->metadata ?? null, $data['send_from'] ?? null),
                'status'        => 'active',
                'connected_at'  => now(),
            ]
        );

        // Register the board webhook (needs a public HTTPS callback Trello can HEAD).
        $note = '';
        if (empty($integration->webhook_id)) {
            $reg = $trello->registerWebhook($data['api_key'], $data['token'], $boardId, url('/webhooks/trello'), 'WaDesk ' . ($board['name'] ?? ''));
            if ($reg['ok']) {
                $integration->webhook_id = $reg['id'];
                $integration->save();
            } else {
                $note = ' ' . __('Connected, but the Trello webhook could not be registered yet:') . ' ' . $reg['error']
                      . ' ' . __('Make sure your site is reachable over public HTTPS, then click Re-register.');
            }
        }

        Audit::log('integration.trello.connected', [
            'resource' => $integration,
            'meta'     => ['board' => $board['name'] ?? null, 'board_id' => $boardId, 'webhook' => (bool) $integration->webhook_id],
        ]);

        return redirect()->route('user.trello')->with('status', __('Trello board connected:') . ' "' . ($board['name'] ?? $boardId) . '".' . $note);
    }

    /** Retry webhook registration (used when the first attempt failed). */
    public function registerWebhook(int $id, TrelloService $trello): RedirectResponse
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        $row  = TrelloIntegration::where('id', $id)->where('workspace_id', $wsId)->first();
        if (!$row) return redirect()->route('user.trello');

        $reg = $trello->registerWebhook($row->api_key, $row->token, $row->board_id, url('/webhooks/trello'), 'WaDesk ' . (string) $row->board_name);
        if ($reg['ok']) {
            $row->update(['webhook_id' => $reg['id']]);
            return redirect()->route('user.trello')->with('status', __('Trello webhook registered. Card events will now notify on WhatsApp.'));
        }
        return redirect()->route('user.trello')->withErrors(['trello' => __('Webhook registration failed:') . ' ' . $reg['error']]);
    }

    public function updateSettings(Request $request, int $id): RedirectResponse
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        $row  = TrelloIntegration::where('id', $id)->where('workspace_id', $wsId)->first();
        if (!$row) return redirect()->route('user.trello');

        $data = $request->validate([
            'events'        => 'nullable|array',
            'events.*'      => 'string|max:40',
            'notify_mode'   => 'nullable|in:assignee,fixed',
            'notify_number' => 'nullable|string|max:32',
            'send_from'     => 'nullable|string|max:64',
        ]);
        $events = array_values(array_intersect($data['events'] ?? [], TrelloIntegration::DEFAULT_EVENTS));
        $row->update([
            'events'        => $events ?: TrelloIntegration::DEFAULT_EVENTS,
            'notify_mode'   => $data['notify_mode'] ?? 'assignee',
            'notify_number' => $data['notify_number'] ?? null,
            'metadata'      => \App\Services\Integrations\SenderResolver::stampMetadata($row->metadata, $data['send_from'] ?? null),
        ]);
        Audit::log('integration.trello.updated', ['resource' => $row]);
        return redirect()->route('user.trello')->with('status', __('Notification settings saved.'));
    }

    public function disconnect(int $id, TrelloService $trello): RedirectResponse
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        $row  = TrelloIntegration::where('id', $id)->where('workspace_id', $wsId)->first();
        if ($row) {
            if ($row->webhook_id) {
                $trello->deleteWebhook($row->api_key, $row->token, $row->webhook_id);
            }
            Audit::log('integration.trello.disconnected', ['resource' => $row]);
            $row->delete();
        }
        return redirect()->route('user.trello')->with('status', __('Trello disconnected.'));
    }

    /** Normalise a board id / shortLink / full URL down to a usable ref. */
    private function boardRef(string $input): string
    {
        $input = trim($input);
        if (preg_match('~trello\.com/b/([A-Za-z0-9]+)~', $input, $m)) {
            return $m[1];
        }
        return $input;
    }
}
