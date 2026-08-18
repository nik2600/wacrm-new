<?php

namespace Database\Seeders;

use App\Models\ChatTemplate;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Seeder;

/**
 * Seeds realistic conversations + messages + templates so the
 * /chat page has something to render on first load. Idempotent:
 * re-running won't duplicate templates (firstOrCreate by title).
 *
 * Conversations + messages are wiped on re-run because the IDs
 * tie back to the messages table, and de-duping each row
 * heuristically would be more confusing than resetting.
 */
class ChatSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedTemplates();
        $this->seedConversations();
    }

    private function seedTemplates(): void
    {
        $templates = [
            ['New Year VIP offer',          'marketing',      'Marketing',
                'Happy New Year, {{name}}! Celebrate 2026 with *25% off* your next order. Use code `NEW26` before Jan 10.'],
            ['Flash sale reminder',         'marketing',      'Marketing',
                'Hi {{name}}, our flash sale is live for the next 3 hours. Reply *SHOP* and we will send the best picks.'],
            ['Back in stock',               'marketing',      'Marketing',
                'Good news, {{name}}. The item you asked for is back in stock. Reply here and we will reserve it for you.'],
            ['Order update',                'utility',        'Utility',
                'Your order {{order_id}} has been packed and is ready for dispatch. Track it from your account anytime.'],
            ['Delivery slot confirmation',  'utility',        'Utility',
                'Hi {{name}}, please confirm your preferred delivery slot: *morning*, *afternoon*, or *evening*.'],
            ['Support follow-up',           'utility',        'Utility',
                'Thanks for reaching out. Our support team has received your request and will update you shortly.'],
            ['Login code',                  'authentication', 'Authentication',
                'Your WaDesk verification code is `482913`. This code expires in 10 minutes. Do not share it with anyone.'],
            ['Password reset',              'authentication', 'Authentication',
                'Use code `739204` to reset your password. If you did not request this, please ignore this message.'],
        ];

        foreach ($templates as [$title, $category, $tone, $body]) {
            ChatTemplate::firstOrCreate(
                ['title' => $title],
                ['category' => $category, 'tone' => $tone, 'body' => $body, 'status' => 'approved']
            );
        }
    }

    private function seedConversations(): void
    {
        Message::query()->delete();
        Conversation::query()->delete();

        $now = now();

        $specs = [
            [
                'title'    => 'New Year VIP drop',
                'preview'  => 'Happy New Year! 25% off your next order — use code NEW26.',
                'status'   => 'sent',
                'count'    => 210,
                'archived' => false,
                'minutes'  => 25,
                'messages' => [
                    ['out', 'Happy New Year, Priya! Celebrate *2026* with 25% off your next order.', 'sent',      $now->copy()->subMinutes(180)],
                    ['in',  'Love it. Is this valid on gift cards?',                                  null,        $now->copy()->subMinutes(178)],
                    ['out', 'Yes — use code `NEW26` at checkout. Offer valid till Jan 10.',           'delivered', $now->copy()->subMinutes(175)],
                ],
            ],
            [
                'title'    => 'Cart reminder — Tuesday',
                'preview'  => 'Complete your order in the next 2 hours and get free shipping.',
                'status'   => 'pending',
                'count'    => 42,
                'archived' => false,
                'minutes'  => 60,
                'messages' => [
                    ['out', 'Hi Marco, you left 3 items in your cart.',                       'sent', $now->copy()->subDays(1)->subHours(1)],
                    ['out', 'Complete your order in the next 2 hours and get free shipping.', 'sent', $now->copy()->subDays(1)],
                ],
            ],
            [
                'title'        => 'Festival flash sale',
                'preview'      => 'Pre-launch teaser for Saturday flash sale — stay tuned!',
                'status'       => 'scheduled',
                'count'        => 180,
                'archived'     => false,
                'minutes'      => 90,
                'scheduled_at' => $now->copy()->addDays(2),
                'messages' => [
                    ['out', 'Pre-launch teaser for Saturday flash sale — stay tuned!', 'scheduled', $now->copy()->addDays(2)],
                ],
            ],
            [
                'title'    => 'Welcome series v3',
                'preview'  => 'Welcome to Bloomly! Here is what you can expect from us.',
                'status'   => 'sent',
                'count'    => 65,
                'archived' => false,
                'minutes'  => 60 * 24 * 6,
                'messages' => [
                    ['out', 'Welcome to Bloomly! Here is what you can expect from us.', 'read', $now->copy()->subDays(6)],
                    ['in',  'Great, thanks!',                                            null,    $now->copy()->subDays(6)->addMinutes(2)],
                ],
            ],
            [
                'title'         => 'Order #A-2841 update',
                'preview'       => 'Your package is on the way.',
                'status'        => 'failed',
                'count'         => 1,
                'archived'      => false,
                'minutes'       => 60 * 24 * 9,
                'failure_reason' => 'Recipient number is not registered on WhatsApp.',
                'messages' => [
                    ['out', 'Your package is on the way.', 'failed', $now->copy()->subDays(9)],
                ],
            ],
            [
                'title'    => 'Yoga retreat leads',
                'preview'  => 'Limited seats available for the May yoga retreat.',
                'status'   => 'sent',
                'count'    => 32,
                'archived' => true,
                'minutes'  => 60 * 24 * 30,
                'messages' => [
                    ['out', 'Limited seats available for the May yoga retreat.', 'read', $now->copy()->subDays(30)],
                ],
            ],
        ];

        foreach ($specs as $spec) {
            $convo = Conversation::create([
                'user_id'          => null,
                'device_id'        => null,
                'contact_group_id' => null,
                'title'            => $spec['title'],
                'preview'          => $spec['preview'],
                'status'           => $spec['status'],
                'archived'         => $spec['archived'],
                'platform'         => 'W',
                'recipients_count' => $spec['count'],
                'last_message_at'  => end($spec['messages'])[3] ?? $now,
                'scheduled_at'     => $spec['scheduled_at'] ?? null,
            ]);

            foreach ($spec['messages'] as [$direction, $body, $status, $when]) {
                Message::create([
                    'conversation_id' => $convo->id,
                    'user_id'         => null,
                    'direction'       => $direction,
                    'to_number'       => $direction === 'out' ? '+91 98104 ' . random_int(10000, 99999) : null,
                    'body'            => $body,
                    'status'          => $status ?: 'delivered',
                    'failure_reason'  => $status === 'failed' ? ($spec['failure_reason'] ?? null) : null,
                    'sent_at'         => in_array($status, ['sent', 'delivered', 'read'], true) ? $when : null,
                    'delivered_at'    => in_array($status, ['delivered', 'read'], true) ? $when : null,
                    'read_at'         => $status === 'read' ? $when : null,
                    'scheduled_at'    => $status === 'scheduled' ? $when : null,
                    'created_at'      => $when,
                    'updated_at'      => $when,
                ]);
            }
        }
    }
}
