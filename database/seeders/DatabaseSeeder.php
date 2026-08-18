<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // updateOrCreate so re-seeding never fails on the unique email
        // constraint and always lifts the test user to admin role.
        \App\Models\SystemSetting::set('auto_verify_email', '1');

        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name'     => 'Test User',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'role'     => 'admin',
            ]
        );

        $this->call([
            // Roles + permissions first so the test user can be promoted to
            // Super Admin and any later seeder can rely on the role table.
            RolePermissionSeeder::class,
            ContactSeeder::class,
            ChatSeeder::class,
            MetaCampaignSeeder::class,
            DeviceSeeder::class,
            WaCampaignSeeder::class,
            BroadcastSeeder::class,
            WaTemplateSeeder::class,
            PaymentGatewaySeeder::class,
            AdminAiKeySeeder::class,
            TranslationProviderSeeder::class,
            CheckoutDefaultsSeeder::class,
            GuidebookArticleSeeder::class,
            LegalPagesSeeder::class,
        ]);
    }
}
