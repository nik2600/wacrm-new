<?php

namespace App\Services\Whatsapp;

use App\Models\WaTemplate;
use App\Services\AttributeResolver;

/**
 * Single source of truth for the `templateData` blob the Node bridge consumes
 * for ALL template types (standard, media, carousel, auth). Extracted from
 * BroadcastsController::buildTemplateData so broadcasts, the mobile-app queue,
 * scheduled sends and anything else produce IDENTICAL payloads — every place
 * that sends a template renders buttons / media / carousel exactly like
 * campaigns do.
 *
 * Workspace-level attribute substitution happens here once; contact-level
 * placeholders ({{name}}, {{phone}}, …) stay literal so Node does per-recipient
 * substitution at send time.
 */
class TemplateDataBuilder
{
    public static function build(WaTemplate $tpl, int $workspaceId): array
    {
        $resolver = app(AttributeResolver::class);

        $variableMap = $tpl->variable_map ?? null;
        if (is_string($variableMap)) {
            $decoded = json_decode($variableMap, true);
            $variableMap = is_array($decoded) ? $decoded : [];
        }
        $variableMap = is_array($variableMap) ? $variableMap : [];

        $body   = $resolver->resolve((string) ($tpl->template_body ?? ''), $variableMap, $workspaceId);
        $header = $resolver->resolve((string) ($tpl->header ?? ''),        $variableMap, $workspaceId);
        $footer = $resolver->resolve((string) ($tpl->footer ?? ''),        $variableMap, $workspaceId);

        $carousel = $tpl->carousel_data ?? null;
        if (is_string($carousel)) {
            $decoded = json_decode($carousel, true);
            $carousel = is_array($decoded) ? $decoded : null;
        }
        if (is_array($carousel)) {
            $carousel = array_map(function ($card) use ($resolver, $variableMap, $workspaceId) {
                if (!is_array($card)) return $card;
                foreach (['title', 'body', 'footer'] as $field) {
                    if (isset($card[$field]) && is_string($card[$field])) {
                        $card[$field] = $resolver->resolve($card[$field], $variableMap, $workspaceId);
                    }
                }
                return $card;
            }, $carousel);
        }

        $buttons = $tpl->buttons ?? [];
        if (is_string($buttons)) {
            $decoded = json_decode($buttons, true);
            $buttons = is_array($decoded) ? $decoded : [];
        }

        $attachmentUrl = null;
        if (!empty($tpl->attachment_file)) {
            $attachmentUrl = media_url($tpl->attachment_file);
        }

        // Base64-inline the attachment ONCE so Node never downloads media
        // per recipient; attachment_url stays as the network fallback.
        $inlineMedia = WaTemplate::inlineAttachment($tpl->attachment_file);

        return [
            'id'                 => $tpl->id,
            'template_name'      => (string) ($tpl->template_name ?? ''),
            'template_type'      => $tpl->template_type ?? 'standard',
            'category'           => $tpl->category ?? null,
            'language'           => (string) ($tpl->language ?? 'en_US'),
            'header'             => $header ?: null,
            'title_text'         => $header ?: null,
            'template_body'      => $body ?: '',
            'footer'             => $footer ?: null,
            'buttons'            => $buttons,
            // LOCATION header — {latitude, longitude, name, address}. Node
            // ships it as a location pin after the body (Unofficial API); the
            // WABA path reads it from the template directly via TemplateSender.
            'location'           => (is_array($tpl->header_location) && !empty($tpl->header_location)) ? $tpl->header_location : null,
            'attachment_type'    => $tpl->attachment_type ?? null,
            'attachment_file'    => $tpl->attachment_file ?? null,
            'attachment_url'     => $attachmentUrl,
            'attachment_base64'  => $inlineMedia['attachment_base64'],
            'attachment_mime'    => $inlineMedia['attachment_mime'],
            'carousel_data'      => $carousel,
            'variable_map'       => $variableMap,
            'twilio_content_sid' => $tpl->twilio_content_sid ?: null,
        ];
    }
}
