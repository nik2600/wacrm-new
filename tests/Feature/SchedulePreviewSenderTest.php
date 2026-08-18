<?php

namespace Tests\Feature;

use App\Models\ScheduledMessage;
use App\Models\User;
use App\Services\Scheduled\SchedulePreviewSender;
use App\Services\WhatsAppDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * "Send a test to me first" (step 5 of /scheduled/new).
 *
 * The dispatcher is ALWAYS replaced with a fake here — these tests must
 * never put a message on a real WhatsApp session. Every assertion is about
 * what the service decides and what it hands the dispatcher, never about a
 * network call actually happening.
 */
class SchedulePreviewSenderTest extends TestCase
{
    use RefreshDatabase;

    /** Records sendRaw() calls instead of performing them. */
    private object $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('users')->insert([
            'id' => 1, 'name' => 'Sudhir Sharma', 'email' => 'op@example.test',
            'mobile' => '919876543210', 'password' => bcrypt('secret'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('workspaces')->insert([
            'id' => 1, 'owner_user_id' => 1, 'name' => 'WS', 'slug' => 'ws',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->dispatcher = new class {
            public array $calls = [];
            public array $reply = ['ok' => true, 'provider_id' => 'wamid.TEST'];
            public function sendRaw(array $params, ?int $userId = null, string $platform = 'W'): array
            {
                $this->calls[] = $params;
                return $this->reply;
            }
        };
        $this->app->instance(WhatsAppDispatcher::class, $this->dispatcher);
    }

    private function schedule(array $overrides = []): ScheduledMessage
    {
        return ScheduledMessage::create(array_merge([
            'user_id'         => 1,
            'workspace_id'    => 1,
            'provider'        => 'baileys',
            'schedule_name'   => 'Test schedule',
            'message_content' => 'Hello from WaDesk',
            'schedule_type'   => 'once',
            'send_date'       => now()->addDay()->toDateString(),
            'send_time'       => '10:00',
            'scheduled_time'  => now()->addDay(),
            'timezone'        => 'Asia/Kolkata',
            'recipient_type'  => 'number',
            'target_numbers'  => ['919111111111'],
            'total_recipients' => 1,
            'from_number'     => '918888888888',
            'status'          => 'scheduled',
        ], $overrides));
    }

    public function test_sends_preview_to_the_operators_own_number(): void
    {
        $user = User::find(1);
        $res  = app(SchedulePreviewSender::class)->send($this->schedule(), $user);

        $this->assertTrue($res['ok'], 'preview should report success');
        $this->assertCount(1, $this->dispatcher->calls, 'exactly one preview, not one per recipient');

        $sent = $this->dispatcher->calls[0];
        $this->assertSame('919876543210', $sent['to_number'], 'goes to the operator, NOT the schedule recipients');
        $this->assertSame('918888888888', $sent['from_number'], 'uses the schedule sender');
        $this->assertSame('baileys', $sent['provider'], 'routes on the schedule engine');
        $this->assertSame('Hello from WaDesk', $sent['body']);
        $this->assertSame(1, $sent['workspace_id']);
    }

    /** The engine is whatever the schedule was stamped with — not a default. */
    public function test_carries_the_schedules_engine_through(): void
    {
        app(SchedulePreviewSender::class)->send($this->schedule(['provider' => 'twilio']), User::find(1));

        $this->assertSame('twilio', $this->dispatcher->calls[0]['provider']);
    }

    /** No number on the account means nowhere to send — refuse, don't guess. */
    public function test_refuses_when_the_operator_has_no_number(): void
    {
        DB::table('users')->where('id', 1)->update(['mobile' => null]);

        $res = app(SchedulePreviewSender::class)->send($this->schedule(), User::find(1));

        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('Account settings', $res['reason']);
        $this->assertCount(0, $this->dispatcher->calls, 'must not attempt a send');
    }

    public function test_refuses_a_junk_number(): void
    {
        DB::table('users')->where('id', 1)->update(['mobile' => '12']);

        $res = app(SchedulePreviewSender::class)->send($this->schedule(), User::find(1));

        $this->assertFalse($res['ok']);
        $this->assertCount(0, $this->dispatcher->calls);
    }

    /**
     * `local_only` means the dispatcher stored a row but nothing left the
     * server, while still returning ok=true. Reporting that as a sent preview
     * would send the operator to stare at a phone that never buzzes.
     */
    public function test_local_only_is_a_failure_not_a_silent_success(): void
    {
        $this->dispatcher->reply = ['ok' => true, 'local_only' => true, 'error' => null];

        $res = app(SchedulePreviewSender::class)->send($this->schedule(), User::find(1));

        $this->assertFalse($res['ok'], 'ok=true + local_only must NOT count as delivered');
    }

    public function test_surfaces_the_dispatcher_error(): void
    {
        $this->dispatcher->reply = ['ok' => false, 'error' => 'No connected device or server url'];

        $res = app(SchedulePreviewSender::class)->send($this->schedule(), User::find(1));

        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('No connected device', $res['reason']);
    }

    /** Nothing to preview is a clear refusal, not an empty message on someone's phone. */
    public function test_refuses_an_empty_body_with_no_media(): void
    {
        $res = app(SchedulePreviewSender::class)
            ->send($this->schedule(['message_content' => '   ']), User::find(1));

        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('empty', $res['reason']);
        $this->assertCount(0, $this->dispatcher->calls);
    }

    /** A media schedule with no caption is still worth previewing. */
    public function test_media_with_no_caption_still_previews(): void
    {
        app(SchedulePreviewSender::class)->send(
            $this->schedule(['message_content' => '', 'media_file' => 'promo.jpg']),
            User::find(1)
        );

        $this->assertCount(1, $this->dispatcher->calls);
        $this->assertSame('uploads/scheduled/promo.jpg', $this->dispatcher->calls[0]['media_path']);
    }

    /** A thrown exception must degrade to a reason, never bubble up and 500 the save. */
    public function test_never_throws_out_of_send(): void
    {
        $boom = new class {
            public function sendRaw(array $p, ?int $u = null, string $pl = 'W'): array
            {
                throw new \RuntimeException('bridge exploded');
            }
        };
        $this->app->instance(WhatsAppDispatcher::class, $boom);

        $res = app(SchedulePreviewSender::class)->send($this->schedule(), User::find(1));

        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('bridge exploded', $res['reason']);
    }
}
