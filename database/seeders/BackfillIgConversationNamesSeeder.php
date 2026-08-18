<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\InstagramAccount;
use Illuminate\Database\Seeder;

/**
 * BackfillIgConversationNamesSeeder — one-time fix for Instagram conversations
 * whose title is still the raw IGSID number (created before username resolution
 * worked). Re-resolves each sender's real @username / name via the Graph
 * participants fallback and rewrites the title in place. Only overwrites a
 * numeric/placeholder title — never a human name an operator set.
 *
 * Run: php artisan db:seed --class=Database\\Seeders\\BackfillIgConversationNamesSeeder
 */
class BackfillIgConversationNamesSeeder extends Seeder
{
    public function run(): void
    {
        $convos = Conversation::query()->where('channel', 'instagram')->get();
        $fixed = 0; $skipped = 0;

        foreach ($convos as $c) {
            $title = (string) $c->title;
            $isPlaceholder = $title === '' || $title === 'Instagram' || ctype_digit(ltrim($title, '@'));
            if (! $isPlaceholder) { $skipped++; continue; }

            // raw_jid = "ig:<igUserId>:<senderIgsid>"
            $rj = (string) $c->raw_jid;
            if (! str_starts_with($rj, 'ig:')) { $skipped++; continue; }
            [$igUser, $sender] = array_pad(explode(':', substr($rj, 3), 2), 2, '');
            $igUser = preg_replace('/[^0-9]/', '', (string) $igUser);
            $sender = preg_replace('/[^0-9]/', '', (string) $sender);
            if ($sender === '') { $skipped++; continue; }

            $account = InstagramAccount::where('ig_user_id', $igUser)->where('workspace_id', $c->workspace_id)->first()
                ?: InstagramAccount::where('ig_user_id', $igUser)->first();
            if (! $account) { $this->command?->warn("  · no account for ig_user={$igUser} (convo #{$c->id})"); $skipped++; continue; }

            try {
                $prof = (new \App\Services\Instagram\InstagramService($account))->getSenderProfile($sender);
            } catch (\Throwable $e) {
                $this->command?->warn("  · resolve failed convo #{$c->id}: " . $e->getMessage());
                $skipped++; continue;
            }

            $username = trim((string) ($prof['username'] ?? ''));
            $name     = trim((string) ($prof['name'] ?? ''));
            $newTitle = $name !== '' ? $name : ($username !== '' ? '@' . ltrim($username, '@') : '');

            if ($newTitle === '' || $newTitle === $title) {
                $this->command?->warn("  · still unresolved convo #{$c->id} sender={$sender}");
                $skipped++; continue;
            }

            $c->forceFill(['title' => $newTitle])->save();
            $fixed++;
            $this->command?->info("  • #{$c->id}  {$title}  →  {$newTitle}");
        }

        $this->command?->info("[BackfillIgNames] Done — {$fixed} fixed, {$skipped} skipped/unresolved.");
    }
}
