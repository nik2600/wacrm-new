<?php

namespace Tests\Feature;

use App\Models\Broadcast;
use App\Models\SystemSetting;
use App\Models\WaLinkClick;
use App\Services\Waba\LinkTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers the read + click tracking fixes across all three engines.
 *
 * READ  — Broadcast::recomputeAggregates is now the single definition of
 *         the cached counters, shared by the WABA webhook and the
 *         Unofficial/Twilio Node callback (which used to write only
 *         success_count/fail_count).
 * CLICK — LinkTracker scoping, and LinkRedirectController writing
 *         clicked_at alongside clicked=1.
 */
class ReadClickTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Running phpunit from the CLI leaks the script path into the URL
        // generator, so url('/r/x') becomes '/vendor/phpunit/phpunit/r/x'
        // and every request 404s. Pin the root so both the generated
        // shortlink and the test request agree.
        \Illuminate\Support\Facades\URL::forceRootUrl('http://localhost');

        // The array cache store lives for the whole PHPUnit process, so a
        // SystemSetting written by one test would leak into the next even
        // though RefreshDatabase rolls the row back.
        \Illuminate\Support\Facades\Cache::flush();

        DB::table('users')->insert([
            'id' => 1, 'name' => 'Owner', 'email' => 'owner@example.test',
            'password' => bcrypt('secret'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('workspaces')->insert([
            'id' => 1, 'owner_user_id' => 1, 'name' => 'WS', 'slug' => 'ws',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeBroadcast(array $overrides = []): Broadcast
    {
        return Broadcast::create(array_merge([
            'user_id'          => 1,
            'workspace_id'     => 1,
            'name'             => 'Test broadcast',
            'status'           => 'completed',
            'total_recipients' => 4,
        ], $overrides));
    }

    private function pivot(int $broadcastId, int $contactId, string $status): void
    {
        DB::table('broadcast_contacts')->insert([
            'broadcast_id' => $broadcastId,
            'contact_id'   => $contactId,
            'status'       => $status,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    /** Read counters cascade: a read row is also delivered and sent. */
    public function test_recompute_aggregates_cascades_read_into_delivered_and_sent(): void
    {
        $b = $this->makeBroadcast();
        $this->pivot($b->id, 101, 'sent');
        $this->pivot($b->id, 102, 'delivered');
        $this->pivot($b->id, 103, 'read');
        $this->pivot($b->id, 104, 'failed');

        $b->recomputeAggregates();
        $b->refresh();

        $this->assertSame(3, (int) $b->success_count, 'sent+delivered+read');
        $this->assertSame(2, (int) $b->delivered_count, 'delivered+read');
        $this->assertSame(1, (int) $b->read_count);
        $this->assertSame(1, (int) $b->fail_count);
    }

    /**
     * The bug this whole change exists for: with only success_count written,
     * getStatusCounts takes its fast path and reports Delivered 0 / Read 0
     * forever. After the recompute the accessor agrees with the pivot rows.
     */
    public function test_status_counts_accessor_reports_delivered_and_read(): void
    {
        $b = $this->makeBroadcast();
        $this->pivot($b->id, 201, 'read');
        $this->pivot($b->id, 202, 'delivered');

        // Simulate the OLD behaviour — success_count only.
        $b->forceFill(['success_count' => 2, 'fail_count' => 0])->saveQuietly();
        $this->assertSame(0, $b->refresh()->status_counts['read'], 'pre-fix baseline');

        $b->recomputeAggregates();
        $counts = $b->refresh()->status_counts;

        $this->assertSame(1, $counts['read']);
        $this->assertSame(2, $counts['delivered']);
        $this->assertSame(2, $counts['sent']);
    }

    /** clicked_count has no per-recipient status, so a recompute must not reset it. */
    public function test_recompute_preserves_clicked_count(): void
    {
        $b = $this->makeBroadcast();
        $this->pivot($b->id, 301, 'read');
        $b->forceFill(['clicked_count' => 7])->saveQuietly();

        $b->recomputeAggregates();

        $this->assertSame(7, (int) $b->refresh()->clicked_count);
    }

    /** Two campaigns, same URL, same contact — must NOT share a token. */
    public function test_wrap_scopes_token_per_campaign(): void
    {
        $url = 'https://shop.example.com/p/123';

        $a = LinkTracker::wrap($url, ['workspace_id' => 1, 'campaign_id' => 10, 'contact_id' => 55]);
        $b = LinkTracker::wrap($url, ['workspace_id' => 1, 'campaign_id' => 11, 'contact_id' => 55]);

        $this->assertNotSame($a, $b, 'campaign_id must scope the token');
        $this->assertSame(2, WaLinkClick::count());
        $this->assertSame(10, (int) WaLinkClick::where('token', basename($a))->first()->campaign_id);
        $this->assertSame(11, (int) WaLinkClick::where('token', basename($b))->first()->campaign_id);
    }

    /** Same send asked twice (a retry) reuses the row rather than bloating the table. */
    public function test_wrap_is_idempotent_for_the_same_send(): void
    {
        $ctx = ['workspace_id' => 1, 'campaign_id' => 10, 'contact_id' => 55];
        $a = LinkTracker::wrap('https://shop.example.com/p/1', $ctx);
        $b = LinkTracker::wrap('https://shop.example.com/p/1', $ctx);

        $this->assertSame($a, $b);
        $this->assertSame(1, WaLinkClick::count());
    }

    /** A broadcast row must not be handed to a campaign send of the same URL. */
    public function test_wrap_does_not_reuse_across_different_scopes(): void
    {
        $url = 'https://shop.example.com/p/9';
        LinkTracker::wrap($url, ['workspace_id' => 1, 'broadcast_id' => 3, 'contact_id' => 55]);
        LinkTracker::wrap($url, ['workspace_id' => 1, 'campaign_id'  => 3, 'contact_id' => 55]);

        $this->assertSame(2, WaLinkClick::count());
    }

    /** An expired row would answer 410 — a re-send has to mint a new token. */
    public function test_wrap_skips_expired_rows(): void
    {
        $ctx = ['workspace_id' => 1, 'campaign_id' => 10, 'contact_id' => 55];
        $first = LinkTracker::wrap('https://shop.example.com/p/2', $ctx);
        WaLinkClick::query()->update(['expires_at' => now()->subDay()]);

        $second = LinkTracker::wrap('https://shop.example.com/p/2', $ctx);

        $this->assertNotSame($first, $second);
        $this->assertSame(2, WaLinkClick::count());
    }

    /** Each recipient needs their OWN shortlink or attribution is impossible. */
    public function test_wrap_in_text_gives_each_recipient_a_distinct_link(): void
    {
        $body = 'Hi {{name}}, your order is ready: https://shop.example.com/o/7 — thanks!';

        $one = LinkTracker::wrapInText($body, ['workspace_id' => 1, 'campaign_id' => 20, 'contact_id' => 1]);
        $two = LinkTracker::wrapInText($body, ['workspace_id' => 1, 'campaign_id' => 20, 'contact_id' => 2]);

        $this->assertNotSame($one, $two);
        $this->assertStringContainsString('{{name}}', $one, 'placeholders survive wrapping');
        $this->assertStringNotContainsString('https://shop.example.com/o/7', $one);
        $this->assertSame(2, WaLinkClick::count());
    }

    /** tel:/mailto: and tracking-off are pass-throughs, not silent breakage. */
    public function test_wrap_passes_through_untrackable_urls_and_disabled_tracking(): void
    {
        $this->assertSame('tel:+15551234', LinkTracker::wrap('tel:+15551234', ['contact_id' => 1]));

        SystemSetting::set('waba_link_tracking_enabled', false);
        $this->assertSame(
            'https://shop.example.com/x',
            LinkTracker::wrap('https://shop.example.com/x', ['contact_id' => 1])
        );
        $this->assertSame(0, WaLinkClick::count());
    }

    /**
     * The Recipients tab and the per-day clicks chart both read clicked_at,
     * not the boolean — writing only clicked=1 left both blank.
     */
    public function test_redirect_writes_clicked_at_and_rolls_up_the_campaign(): void
    {
        DB::table('wpcampaigns')->insert([
            'id' => 500, 'workspace_id' => 1,
            'campaign_name' => 'C', 'status' => 'completed',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('wp_campaign_contacts')->insert([
            'campaign_id' => 500, 'contact_id' => 77, 'status' => 'sent',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $short = LinkTracker::wrap('https://shop.example.com/deal', [
            'workspace_id' => 1, 'campaign_id' => 500, 'contact_id' => 77,
        ]);

        $this->get('/r/' . basename($short), ['User-Agent' => 'Mozilla/5.0 (iPhone)'])
            ->assertRedirect('https://shop.example.com/deal');

        $row = DB::table('wp_campaign_contacts')->where('campaign_id', 500)->first();
        $this->assertSame(1, (int) $row->clicked);
        $this->assertNotNull($row->clicked_at, 'clicked_at is what the UI actually reads');
        $this->assertSame(1, (int) DB::table('wpcampaigns')->where('id', 500)->value('clicked_count'));
    }

    /** Link-preview bots must not inflate the numbers. */
    public function test_bot_user_agents_redirect_without_counting(): void
    {
        $short = LinkTracker::wrap('https://shop.example.com/bot', [
            'workspace_id' => 1, 'campaign_id' => 501, 'contact_id' => 78,
        ]);

        $this->get('/r/' . basename($short), ['User-Agent' => 'WhatsApp/2.23 A'])
            ->assertRedirect('https://shop.example.com/bot');

        $this->assertSame(0, (int) WaLinkClick::first()->clicks);
    }
}
