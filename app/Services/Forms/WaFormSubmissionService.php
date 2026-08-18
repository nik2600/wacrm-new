<?php

namespace App\Services\Forms;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\WaForm;
use App\Models\WaFormSubmission;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Lands a form submission from Meta's WABA webhook + resumes the paused
 * flow node so the conversation continues with the answers in scope.
 *
 * Meta's payload for a form reply (`type='interactive'`,
 * `interactive.type='nfm_reply'`) contains:
 *   {
 *     name: 'flow',
 *     body: 'Sent',
 *     response_json: '{"flow_token":"abc","field_id":"value", ...}'
 *   }
 * The `flow_token` is what we attached when we sent the form (carries
 * the session key); the rest of the JSON is the customer's answers.
 */
class WaFormSubmissionService
{
    public function ingest(int $workspaceId, array $msg, array $value): void
    {
        $interactive = $msg['interactive'] ?? null;
        if (!$interactive || ($interactive['type'] ?? '') !== 'nfm_reply') return;
        $reply = $interactive['nfm_reply'] ?? [];

        $rawJson = (string) ($reply['response_json'] ?? '');
        $payload = json_decode($rawJson, true);
        if (!is_array($payload)) {
            Log::warning('[WAFORM-SUB] non-JSON response_json: ' . substr($rawJson, 0, 200));
            return;
        }

        $flowToken = (string) ($payload['flow_token'] ?? '');
        if ($flowToken === '') {
            Log::warning('[WAFORM-SUB] no flow_token in response — cannot route to paused flow');
            return;
        }

        // The flow_token we stamped on send is `form-<form_id>-<session_key>`.
        // Extract both pieces.
        if (!preg_match('/^form-(\d+)-(.+)$/', $flowToken, $m)) {
            Log::warning('[WAFORM-SUB] flow_token does not match form-X-sessionKey shape: ' . $flowToken);
            return;
        }
        $formId     = (int) $m[1];
        $sessionKey = (string) $m[2];

        $form = WaForm::find($formId);
        if (!$form || $form->workspace_id !== $workspaceId) {
            Log::warning('[WAFORM-SUB] form not in workspace', ['form_id' => $formId, 'ws' => $workspaceId]);
            return;
        }

        $callerPhone = (string) ($msg['from'] ?? '');
        // Contact.mobile is encrypted-at-rest (non-deterministic ciphertext)
        // so a LIKE filter never matches; hydrate the workspace's contact
        // set and compare decrypted digits in PHP. Bounded by workspace
        // so a foreign submission can't reach into another tenant.
        $contact = null;
        if ($callerPhone !== '') {
            $digits = preg_replace('/\D+/', '', $callerPhone);
            $contact = Contact::query()
                ->where('workspace_id', $workspaceId)
                ->get()
                ->first(function ($c) use ($digits) {
                    $stored = preg_replace('/\D+/', '', (string) ($c->country_code . $c->mobile));
                    return $stored !== '' && $stored === $digits;
                });
        }
        // Resolve the customer's conversation the SAME way the WABA webhook does
        // (raw_jid / alt_jid = plain digits), plus the Baileys @s.whatsapp.net
        // form — else the submission card never lands in the thread.
        // A number can have several conversation rows (e.g. a Quick Send thread
        // + the live inbound thread). The inbound webhook + flow bind to the
        // ACTIVE inbox/chatbot thread — so mirror the submission there too:
        // scope to origin inbox/chatbot and pick the MOST RECENTLY ACTIVE one
        // (not the newest id), else the card lands on a thread nobody opens.
        // ONE THREAD PER NUMBER - see ConversationResolver. There is now a
        // single thread per number, so there is no "pick the most recently
        // active of several" to do; and the origin filter that used to be here
        // could miss the thread entirely, dropping the submission card.
        $digits = preg_replace('/\D+/', '', (string) $callerPhone);
        $conv = \App\Services\Inbox\ConversationResolver::find($workspaceId, $digits);

        \Illuminate\Support\Facades\Log::info('[WA-FORM] submission ingest', [
            'form_id'      => $form->id,
            'workspace_id' => $workspaceId,
            'caller_phone' => $callerPhone,
            'digits'       => $digits,
            'conv_id'      => $conv?->id,
            'conv_found'   => (bool) $conv,
        ]);

        // Strip the flow_token from the answers — keep only the actual
        // field values so downstream nodes see {{field_id}}.
        $answers = $payload;
        unset($answers['flow_token']);

        $submission = WaFormSubmission::create([
            'form_id'         => $form->id,
            'workspace_id'    => $workspaceId,
            'contact_id'      => $contact?->id,
            'conversation_id' => $conv?->id,
            'flow_token'      => $flowToken,
            'caller_phone'    => $callerPhone,
            'answers_json'    => $answers,
            'meta_payload'    => $msg,
            'submitted_at'    => now(),
        ]);

        $form->increment('submission_count');

        // Mirror the submission INTO the team-inbox thread as an inbound message
        // so the operator SEES the customer's answers in the conversation (the
        // nfm_reply webhook routes here and skips captureInboundMessage, so
        // without this the form + its answers never appear in the inbox — only
        // on the /wa-forms submissions page). Best-effort: a mirror failure must
        // never break the submission capture or the flow resume.
        if ($conv) {
            try {
                // Map field ids -> human labels from the form definition.
                $labels = [];
                foreach ((array) ($form->definition_json['screens'] ?? []) as $screen) {
                    foreach ((array) ($screen['fields'] ?? []) as $f) {
                        $fid = (string) ($f['id'] ?? '');
                        if ($fid !== '') $labels[$fid] = (string) ($f['label'] ?? $fid);
                    }
                }
                $lines  = ['Form submitted — ' . ($form->title ?: 'Form')];
                $fields = [];   // structured label→value pairs for the inbox card + detail panel
                foreach ($answers as $k => $v) {
                    $v        = $this->flattenAnswer($v);
                    $label    = $labels[$k] ?? ucfirst(trim(str_replace('_', ' ', (string) $k)));
                    $lines[]  = $label . ': ' . $v;
                    $fields[] = ['label' => $label, 'value' => $v];
                }
                $body = implode("\n", $lines);

                $mirror = \App\Models\InboxMessage::create([
                    'conversation_id' => $conv->id,
                    'contact_id'      => $contact?->id,
                    'provider'        => $conv->provider ?? 'waba',
                    'direction'       => 'in',
                    'from_number'     => $callerPhone,
                    'body'            => $body,
                    'status'          => 'received',
                    'meta'            => [
                        'type'          => 'wa_form_submission',
                        'form_id'       => $form->id,
                        'form_title'    => $form->title,
                        'submission_id' => $submission->id,
                        'fields'        => $fields,   // [{label, value}] — drives the clickable card + panel
                        'answers'       => $answers,
                    ],
                    'sent_at'      => now(),
                    'delivered_at' => now(),
                ]);
                $conv->forceFill(['last_message_at' => now(), 'preview' => mb_substr($body, 0, 200)])->save();
                \Illuminate\Support\Facades\Log::info('[WA-FORM] mirrored to inbox', [
                    'inbox_message_id' => $mirror->id, 'conv_id' => $conv->id, 'fields' => count($fields),
                ]);
                try {
                    broadcast(new \App\Events\Inbox\MessageReceived($mirror->id, $conv->id, (int) $conv->workspace_id, 'in', null));
                } catch (\Throwable $e) { /* realtime is best-effort */ }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[WA-FORM] inbox mirror failed: ' . $e->getMessage());
            }
        }

        // Resume the paused flow on Node — same shape as the commerce
        // resume-by-phone hook. Node sees the answers under
        // `formAnswers` and stamps them into session.userVariables
        // prefixed with `form_` so downstream nodes can reference
        // {{form_<field_id>}}.
        $this->resumeNodeFlow($sessionKey, $form->id, $answers);
    }

    /**
     * Flatten one form answer to a readable string. Meta returns strings for
     * text/email fields but arrays/objects for date pickers, dropdowns, opt-ins
     * and multi-selects — a naive (string) cast on those yields "Array". Extract
     * the human value (title/name/label/value/id) or comma-join a list instead.
     */
    private function flattenAnswer($v): string
    {
        if ($v === null) return '';
        if (is_array($v)) {
            // Associative (object-like) → pick a human field; else it's a list.
            $isList = array_keys($v) === range(0, count($v) - 1);
            if (!$isList) {
                foreach (['title', 'name', 'label', 'value', 'text', 'id'] as $key) {
                    if (isset($v[$key]) && !is_array($v[$key])) return (string) $v[$key];
                }
                return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
            }
            return implode(', ', array_filter(array_map(fn ($x) => $this->flattenAnswer($x), $v), fn ($s) => $s !== ''));
        }
        return is_scalar($v) ? (string) $v : '';
    }

    private function resumeNodeFlow(string $sessionKey, int $formId, array $answers): void
    {
        $base = (string) (\App\Models\SystemSetting::get('baileys_server_url', '') ?: env('SERVER_URL', ''));
        if ($base === '') {
            Log::warning('[WAFORM-SUB] no Node URL — flow cannot resume');
            return;
        }
        try {
            Http::withHeaders(['X-Node-Token' => node_token()])
                ->timeout(8)
                ->acceptJson()
                ->post(rtrim($base, '/') . '/api/flow/resume-form/' . rawurlencode($sessionKey), [
                    'form_id'      => $formId,
                    'form_answers' => $answers,
                ]);
        } catch (\Throwable $e) {
            Log::warning('[WAFORM-SUB] resume Node failed: ' . $e->getMessage());
        }
    }
}
