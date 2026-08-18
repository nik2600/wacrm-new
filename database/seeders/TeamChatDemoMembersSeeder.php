<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Ten demo teammates so Team Chat can be tested with a realistic member list —
 * the mobile layout in particular is hard to judge against a single row.
 *
 * Safe to run repeatedly: users are matched on their email, and the pivot is
 * only inserted when missing, so a second run adds nothing.
 *
 *   php artisan db:seed --class=TeamChatDemoMembersSeeder
 *   php artisan db:seed --class=TeamChatDemoMembersSeeder -- --workspace=2
 *
 * Every account uses the demo domain @wadesk-demo.test, which cannot receive
 * mail — that keeps them obviously fake and means nothing can be sent to a real
 * person by accident. Remove them with:
 *
 *   User::where('email','like','%@wadesk-demo.test')->delete();
 */
class TeamChatDemoMembersSeeder extends Seeder
{
    private const DOMAIN = 'wadesk-demo.test';

    private const PEOPLE = [
        ['Aarav Sharma',    'agent'],
        ['Priya Nair',      'agent'],
        ['Rahul Verma',     'agent'],
        ['Sneha Iyer',      'manager'],
        ['Vikram Singh',    'agent'],
        ['Ananya Reddy',    'agent'],
        ['Karan Mehta',     'manager'],
        ['Divya Kapoor',    'agent'],
        ['Arjun Desai',     'agent'],
        ['Meera Joshi',     'agent'],
    ];

    public function run(): void
    {
        $workspaceId = (int) (getenv('SEED_WORKSPACE_ID') ?: 0);

        $workspace = $workspaceId > 0
            ? Workspace::find($workspaceId)
            : Workspace::query()->orderBy('id')->first();

        if (! $workspace) {
            $this->command?->error('No workspace found — create one first.');
            return;
        }

        $this->command?->info("Seeding demo teammates into workspace #{$workspace->id} ({$workspace->name})");

        $added = 0;
        $linked = 0;

        foreach (self::PEOPLE as [$name, $role]) {
            $slug  = str_replace(' ', '.', mb_strtolower($name));
            $email = $slug . '@' . self::DOMAIN;

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name'              => $name,
                    'password'          => Hash::make('demo-password-' . bin2hex(random_bytes(8))),
                    'email_verified_at' => now(),
                ]
            );
            if ($user->wasRecentlyCreated) {
                $added++;
            }

            $already = DB::table('workspace_user')
                ->where('workspace_id', $workspace->id)
                ->where('user_id', $user->id)
                ->exists();

            if (! $already) {
                DB::table('workspace_user')->insert([
                    'workspace_id' => $workspace->id,
                    'user_id'      => $user->id,
                    'role'         => $role,
                    'invited_at'   => now(),
                    'joined_at'    => now(),
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
                $linked++;
            }
        }

        $total = DB::table('workspace_user')->where('workspace_id', $workspace->id)->count();

        $this->command?->info("Users created: {$added}   ·   linked to workspace: {$linked}");
        $this->command?->info("Workspace #{$workspace->id} now has {$total} members.");
    }
}
