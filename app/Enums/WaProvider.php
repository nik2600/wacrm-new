<?php

namespace App\Enums;

/**
 * The three WhatsApp send providers we support.
 *
 * Encoded as short uppercase keys ('W' / 'WB' / 'T') for back-compat
 * with the old project's `messages.platform_type` column, but the
 * preferred external representation is the lowercase string ('waba'
 * / 'baileys' / 'twilio') used in admin settings and config rows.
 *
 * Values map:
 *   slug      legacy   label
 *   --------------------------------
 *   waba       WB      WhatsApp Business API (Meta Cloud)
 *   baileys    W       Baileys / Custom Node bridge
 *   twilio     T       Twilio WhatsApp
 */
enum WaProvider: string
{
    case Waba    = 'waba';
    case Baileys = 'baileys';
    case Twilio  = 'twilio';

    public function legacyCode(): string
    {
        return match ($this) {
            self::Waba    => 'WB',
            self::Baileys => 'W',
            self::Twilio  => 'T',
        };
    }

    public static function fromLegacyCode(?string $code): self
    {
        return match (strtoupper((string) $code)) {
            'WB' => self::Waba,
            'T'  => self::Twilio,
            default => self::Baileys,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Waba    => 'Official WABA (Meta Cloud)',
            self::Baileys => 'Baileys (Node bridge)',
            self::Twilio  => 'Twilio WhatsApp',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Waba    => '<svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="8" cy="8" r="6"/><path d="M5.5 8.5l2 2 3-4"/></svg>',
            self::Baileys => '<svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 4h12v8H2zM5 4v8M11 4v8"/></svg>',
            self::Twilio  => '<svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 5l5-3 5 3v6l-5 3-5-3z"/><circle cx="8" cy="8" r="1.5"/></svg>',
        };
    }

    public static function options(): array
    {
        return [
            self::Waba->value    => self::Waba->label(),
            self::Baileys->value => self::Baileys->label(),
            self::Twilio->value  => self::Twilio->label(),
        ];
    }
}
