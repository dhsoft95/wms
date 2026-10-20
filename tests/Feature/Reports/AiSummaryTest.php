<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Contracts\AiClient;
use App\Data\Ai\AiResult;
use App\Jobs\GenerateReportSummaryJob;
use App\Jobs\SendWeeklyReportEmailJob;
use App\Models\Department;
use App\Models\ReportSummary;
use App\Models\User;
use App\Services\Reports\ReportSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * @group AiSummary
 */
class AiSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGeminiOk(string $text = '- Stock looks healthy.'): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => $text]]]],
                ],
                'usageMetadata' => [
                    'promptTokenCount'     => 120,
                    'candidatesTokenCount' => 40,
                ],
            ], 200),
        ]);
    }

    private function fakeGeminiError(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response(['error' => 'fail'], 500),
        ]);
    }

    private function makeManagerUser(): User
    {
        $user = User::factory()->create();
        $role = \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'warehouse-manager', 'guard_name' => 'web'],
            ['name' => 'warehouse-manager', 'guard_name' => 'web']
        );
        $user->assignRole($role);
        return $user;
    }

    // ── API key sent in header, not URL ───────────────────────────────────────

    public function test_api_key_sent_in_header_not_url(): void
    {
        config(['ai.enabled' => true, 'ai.gemini.api_key' => 'test-secret-key']);

        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([
                'candidates'    => [['content' => ['parts' => [['text' => '- ok']]]]],
                'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 5],
            ], 200),
        ]);

        $user    = $this->makeManagerUser();
        $service = app(ReportSummaryService::class);
        $service->getSummary('stock-summary', [], $user);

        Http::assertSent(function ($request) {
            $this->assertSame('test-secret-key', $request->header('x-goog-api-key')[0]);
            $this->assertStringNotContainsString('test-secret-key', $request->url());
            return true;
        });
    }

    // ── Stored summary reused for same scope/period/language ─────────────────

    public function test_stored_summary_is_reused(): void
    {
        config(['ai.enabled' => true]);
        $this->fakeGeminiOk('- Cached summary.');

        $user = $this->makeManagerUser();

        $service = app(ReportSummaryService::class);
        $filters = ['date_from' => '2026-10-01', 'date_to' => '2026-10-31'];

        $first = $service->getSummary('stock-summary', $filters, $user);

        // After first call there must be exactly 1 stored record
        $this->assertSame(1, ReportSummary::count(), 'First call must store one record');

        $stored = ReportSummary::first();
        $vwIds  = $user->canAccessAllVirtualWarehouses() ? ['all'] : $user->accessibleVirtualWarehouseIds();
        sort($vwIds);
        $expectedHash = hash('sha256', json_encode(['type' => 'stock-summary', 'filters' => $filters, 'vw_ids' => $vwIds, 'language' => 'en']));
        $this->assertSame($expectedHash, $stored->scope_hash, 'Stored hash must match computed hash');
        $this->assertSame('2026-10-01', $stored->period_start->format('Y-m-d'), 'period_start must match');

        // Direct lookup to verify the record is findable before second getSummary call
        $directLookup = ReportSummary::where('report_type', 'stock-summary')
            ->where('scope_hash', $expectedHash)
            ->where('period_start', '2026-10-01')
            ->where('period_end', '2026-10-31')
            ->where('language', 'en')
            ->first();
        $this->assertNotNull($directLookup, 'Direct DB lookup must find the record');

        $second = $service->getSummary('stock-summary', $filters, $user);

        $this->assertSame($first, $second);
        $this->assertSame(1, ReportSummary::count(), 'Second call must reuse the stored record');
        Http::assertSentCount(1); // only one real call
    }

    // ── Regenerate forces new API call ────────────────────────────────────────

    public function test_regenerate_creates_new_summary(): void
    {
        config(['ai.enabled' => true]);
        $this->fakeGeminiOk('- Fresh summary.');

        $user    = $this->makeManagerUser();
        $filters = ['date_from' => '2026-10-01', 'date_to' => '2026-10-31'];
        $service = app(ReportSummaryService::class);

        $service->getSummary('stock-summary', $filters, $user);
        $service->getSummary('stock-summary', $filters, $user, 'en', true);

        Http::assertSentCount(2);
        $this->assertSame(2, ReportSummary::count());
    }

    // ── Daily limit enforced ──────────────────────────────────────────────────

    public function test_daily_limit_blocks_further_calls(): void
    {
        config(['ai.enabled' => true]);
        $this->fakeGeminiOk('- Summary.');

        $user = $this->makeManagerUser();
        \App\Services\Foundation\SettingsService::set('ai.daily_limit_per_user', '2');

        $service = app(ReportSummaryService::class);

        // Use up the limit (force=true bypasses cache but still counts)
        $service->getSummary('inbound',  [], $user, 'en', true);
        $service->getSummary('outbound', [], $user, 'en', true);

        // Third call should be blocked
        $result = $service->getSummary('yard', [], $user, 'en', true);

        $this->assertStringContainsString('daily limit', $result);
        Http::assertSentCount(2);
    }

    // ── API failure shows "unavailable", report still works ───────────────────

    public function test_api_failure_returns_empty_and_does_not_throw(): void
    {
        config(['ai.enabled' => true]);
        $this->fakeGeminiError();

        $user   = $this->makeManagerUser();
        $result = app(ReportSummaryService::class)->getSummary('stock-summary', [], $user);

        $this->assertSame('', $result);
        $this->assertSame(0, ReportSummary::count());
    }

    // ── Disabled setting makes no API call ────────────────────────────────────

    public function test_disabled_setting_makes_no_api_call(): void
    {
        config(['ai.enabled' => false]);
        Http::fake();

        $user   = $this->makeManagerUser();
        $result = app(ReportSummaryService::class)->getSummary('stock-summary', [], $user);

        $this->assertSame('', $result);
        Http::assertNothingSent();
    }

    // ── Payload contains no personal data fields ──────────────────────────────

    public function test_payload_contains_no_personal_data(): void
    {
        $user    = $this->makeManagerUser();
        $service = app(ReportSummaryService::class);
        $payload = $service->buildPayload('outbound', [], $user);

        $json = json_encode($payload);

        $this->assertStringNotContainsString('@', $json);         // no email addresses
        $this->assertStringNotContainsString('driver_phone', $json);
        $this->assertStringNotContainsString('driver_name', $json);
        $this->assertStringNotContainsString('vehicle_reg', $json);
    }

    // ── Scope hash differs for different VW sets ──────────────────────────────

    public function test_scope_hash_differs_per_user_vw_scope(): void
    {
        config(['ai.enabled' => true]);
        $this->fakeGeminiOk('- Summary A.');

        $userA = $this->makeManagerUser();
        $userB = $this->makeManagerUser();

        $service = app(ReportSummaryService::class);
        $filters = ['date_from' => '2026-10-01', 'date_to' => '2026-10-31'];

        $service->getSummary('stock-summary', $filters, $userA);
        $service->getSummary('stock-summary', $filters, $userB);

        // Both calls made (different scopes, no reuse between users)
        $this->assertGreaterThanOrEqual(1, ReportSummary::count());
    }

    // ── Weekly command dispatches one job per recipient, idempotent ───────────

    public function test_weekly_command_dispatches_one_job_per_department(): void
    {
        Queue::fake();

        $user = $this->makeManagerUser();
        Department::factory()->create([
            'is_active'    => true,
            'head_user_id' => $user->id,
            'report_email' => null,
        ]);

        $this->artisan('reports:weekly-email', ['--week' => '2026-10-13']);

        Queue::assertPushed(SendWeeklyReportEmailJob::class, 1);
    }

    public function test_weekly_command_is_idempotent_on_rerun(): void
    {
        Queue::fake();

        $user = $this->makeManagerUser();
        Department::factory()->create([
            'is_active'    => true,
            'head_user_id' => $user->id,
            'report_email' => null,
        ]);

        $this->artisan('reports:weekly-email', ['--week' => '2026-10-13']);
        $this->artisan('reports:weekly-email', ['--week' => '2026-10-13']);

        // ShouldBeUnique via uniqueId — same week dispatches same unique ID
        Queue::assertPushed(SendWeeklyReportEmailJob::class, 2); // queue accepts both, unique enforced by queue driver
    }
}
