<?php

namespace App\Http\Requests\Api\V1\Webhook;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Register an outbound webhook endpoint for the current workspace. Scramble
 * reads these rules to document the request body. Mirrors the validation in
 * the in-app WebhooksController::store.
 *
 * The platform POSTs a JSON envelope to the registered `url` whenever a
 * subscribed event fires. When a signing `secret` is configured the request
 * carries an `X-WaDesk-Signature` header (HMAC-SHA256 of the body).
 */
class StoreWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // api-key middleware already authenticated the workspace
    }

    public function rules(): array
    {
        return [
            // Public HTTPS endpoint the platform POSTs events to. Required.
            'url'      => ['required', 'url', 'max:2048'],
            // Event names to subscribe to. Omit (or pass ["*"]) to receive all.
            // Supported events fired by the system:
            //   message_received                 — inbound message received
            //   message_sent                     — outbound message sent
            //   message_delivered                — outbound message delivered
            //   message_read                     — outbound message read
            //   message_failed                   — outbound message failed
            //   broadcast_created                — broadcast created
            //   broadcast_status_updated         — broadcast status changed
            //   broadcast_message_status_updated — per-recipient broadcast status changed
            //   campaign_created                 — campaign created
            //   campaign_status_updated          — campaign status changed
            //   campaign_contact_status_updated  — per-contact campaign status changed
            //   campaign_contact_clicked         — campaign contact clicked a link
            //   campaign_contact_replied         — campaign contact replied
            //   contact_opt_in                   — contact opted in
            //   contact_updated                  — contact updated
            //   device_status_updated            — device (number) status changed
            //   "*"                              — subscribe to every event
            'events'   => ['nullable', 'array'],
            'events.*' => ['string', 'max:64'],
            // Optional friendly label shown in the dashboard.
            'name'     => ['nullable', 'string', 'max:191'],
            // Optional HMAC signing secret. When set, deliveries carry an
            // X-WaDesk-Signature header so receivers can verify authenticity.
            'secret'   => ['nullable', 'string', 'max:191'],
            // Whether the endpoint is active and should receive events.
            // Defaults to true.
            'active'   => ['nullable', 'boolean'],
        ];
    }
}
