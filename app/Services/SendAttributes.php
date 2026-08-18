<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Contact;
use Illuminate\Support\Facades\Cache;

/**
 * The catalog of insertable tokens offered on every send screen.
 *
 * Two attribute universes existed in WaDesk and neither was visible
 * where an operator actually composes a send:
 *
 *   - CONTACT fields — resolved per recipient by
 *     BroadcastsController::varsForRecipient()'s $pull()
 *   - WORKSPACE attributes — static Attribute rows, previously only
 *     reachable from the custom-message composer's `/` picker
 *
 * This merges both (plus custom attributes and a couple of system
 * tokens) into one grouped, click-to-insert list.
 *
 * Every key offered here MUST be resolvable by
 * TemplateOverrideResolver::lookup() against the recipient row that
 * varsForRecipient() receives — otherwise the UI would advertise a
 * token that silently renders empty at send time. The contact group
 * below is deliberately kept in lockstep with the recipient row built
 * in BroadcastsController (~L1718).
 *
 * @see \App\Services\TemplateOverrideResolver
 */
class SendAttributes
{
    /**
     * Contact-row tokens. Key => label.
     * Mirrors the recipient row keys exactly.
     */
    private const CONTACT_FIELDS = [
        'name'          => 'Full name',
        'first_name'    => 'First name',
        'last_name'     => 'Last name',
        'middle_name'   => 'Middle name',
        'title'         => 'Title',
        'phone'         => 'Phone (full)',
        'mobile'        => 'Mobile (no country code)',
        'country_code'  => 'Country code',
        'email'         => 'Email',
        'address'       => 'Address',
        'language'      => 'Language',
        'contact_group' => 'Contact group',
    ];

    /** Tokens computed at send time, not stored anywhere. */
    private const SYSTEM_FIELDS = [
        'today'         => "Today's date",
        // Meta gives us the WhatsApp number's verified business name at
        // connect time, so this resolves to the name the CUSTOMER sees the
        // message coming from — the natural value for a header like
        // "Welcome to {{1}}". Falls back to the workspace name for
        // Unofficial-API / Twilio senders, which have no Meta verified name.
        'business_name' => 'Your WhatsApp business name',
        'company_name'  => 'Your company name',
    ];

    /**
     * Build the catalog for a workspace.
     *
     * @return array<int, array{key:string,token:string,label:string,group:string,sample:string}>
     */
    public function catalog(int $workspaceId): array
    {
        $sampleContact = $this->sampleContact($workspaceId);
        $out = [];

        foreach (self::CONTACT_FIELDS as $key => $label) {
            $out[] = $this->entry($key, $label, 'Contact', $this->sampleFor($key, $sampleContact));
        }

        // Custom attributes — discovered from real contact data, so the
        // list reflects what this workspace actually uses rather than a
        // hard-coded guess.
        foreach ($this->customAttributeKeys($workspaceId) as $key) {
            $out[] = $this->entry(
                $key,
                $this->humanize($key),
                'Custom field',
                $this->scalarize($sampleContact['custom_attributes'][$key] ?? '')
            );
        }

        // Workspace static attributes — same value for every recipient.
        foreach ($this->workspaceAttributes($workspaceId) as $key => $row) {
            $out[] = $this->entry($key, $row['label'], 'Workspace', $row['value']);
        }

        // Ask the RESOLVER for the sample rather than recomputing it here.
        // This class's contract is that every advertised token resolves at
        // send time; duplicating the logic is how the two drift. It mattered
        // immediately: company_name used to be previewed as the platform
        // brand while the send now produces the workspace's own business
        // name, so a hard-coded sample would have shown the operator a value
        // their customer never receives. Empty contact = workspace + system
        // scope only, which is exactly what a System token resolves against.
        $resolver = app(TemplateOverrideResolver::class);
        foreach (self::SYSTEM_FIELDS as $key => $label) {
            $out[] = $this->entry($key, $label, 'System', $resolver->lookup($key, [], $workspaceId));
        }

        // A later group must never shadow an earlier one — lookup()
        // resolves contact → custom → workspace → system in that order,
        // so the catalog has to advertise the same precedence.
        $seen = [];
        return array_values(array_filter($out, function ($e) use (&$seen) {
            if (isset($seen[$e['key']])) return false;
            $seen[$e['key']] = true;
            return true;
        }));
    }

    private function entry(string $key, string $label, string $group, string $sample): array
    {
        return [
            'key'    => $key,
            'token'  => '{{' . $key . '}}',
            'label'  => $label,
            'group'  => $group,
            'sample' => mb_substr($sample, 0, 60),
        ];
    }

    /**
     * One real contact, used only to show truthful sample values in the
     * picker and live preview. Cached briefly — this runs on every send
     * page load and the samples don't need to be fresh to the second.
     */
    private function sampleContact(int $workspaceId): array
    {
        return Cache::remember("send_attrs:sample:{$workspaceId}", 300, function () use ($workspaceId) {
            $c = Contact::query()
                ->where('workspace_id', $workspaceId)
                ->latest('id')
                ->first();

            if (! $c) return [];

            $cc    = preg_replace('/\D+/', '', (string) ($c->country_code ?? ''));
            $local = preg_replace('/\D+/', '', (string) ($c->mobile ?? ''));

            return [
                'name'              => (string) ($c->name ?? ''),
                'first_name'        => (string) ($c->first_name ?? ''),
                'last_name'         => (string) ($c->last_name ?? ''),
                'middle_name'       => (string) ($c->middle_name ?? ''),
                'title'             => (string) ($c->title ?? ''),
                'phone'             => ($cc && $local && strpos($local, $cc) !== 0) ? $cc . $local : $local,
                'mobile'            => $local,
                'country_code'      => $cc,
                'email'             => (string) ($c->email ?? ''),
                'address'           => (string) ($c->address ?? ''),
                'language'          => (string) ($c->language ?? ''),
                // contact_group is cast encrypted:ARRAY — never a plain string.
                'contact_group'     => $this->scalarize($c->contact_group),
                'custom_attributes' => is_array($c->custom_attributes) ? $c->custom_attributes : [],
            ];
        });
    }

    private function sampleFor(string $key, array $sample): string
    {
        return $this->scalarize($sample[$key] ?? '');
    }

    /**
     * Distinct custom_attributes keys in use. Sampled rather than scanned
     * in full — custom_attributes is an encrypted column on some rows,
     * so it can't be queried with JSON_KEYS; we read a bounded slice and
     * union the keys.
     */
    private function customAttributeKeys(int $workspaceId): array
    {
        return Cache::remember("send_attrs:custom:{$workspaceId}", 300, function () use ($workspaceId) {
            $keys = [];
            Contact::query()
                ->where('workspace_id', $workspaceId)
                ->whereNotNull('custom_attributes')
                ->latest('id')
                ->limit(200)
                ->get(['id', 'custom_attributes'])
                ->each(function ($c) use (&$keys) {
                    if (! is_array($c->custom_attributes)) return;
                    foreach (array_keys($c->custom_attributes) as $k) {
                        if (is_string($k) && preg_match('/^[a-zA-Z_][\w.-]*$/', $k)) {
                            $keys[$k] = true;
                        }
                    }
                });
            ksort($keys);
            return array_keys($keys);
        });
    }

    /** @return array<string, array{label:string,value:string}> */
    private function workspaceAttributes(int $workspaceId): array
    {
        return Attribute::query()
            ->forWorkspace($workspaceId)
            ->get(['attribute_key', 'attribute_name', 'attribute_value'])
            ->mapWithKeys(fn ($a) => [
                (string) $a->attribute_key => [
                    'label' => (string) ($a->attribute_name ?: $this->humanize((string) $a->attribute_key)),
                    'value' => (string) $a->attribute_value,
                ],
            ])
            ->all();
    }

    private function humanize(string $key): string
    {
        return ucfirst(str_replace(['_', '-', '.'], ' ', $key));
    }

    private function scalarize($v): string
    {
        if (is_array($v)) {
            return implode(', ', array_map('strval', array_filter($v, 'is_scalar')));
        }
        return is_scalar($v) ? (string) $v : '';
    }
}
