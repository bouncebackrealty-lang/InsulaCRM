<?php

namespace Tests\Feature\Api;

use Carbon\Carbon;
use App\Models\Deal;
use Tests\TestCase;

class DealApiTest extends TestCase
{
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTenantWithAdmin([
            'api_key' => 'test-api-key-for-deals',
            'api_enabled' => true,
            'business_mode' => 'wholesale',
        ]);
        $this->headers = ['X-API-Key' => 'test-api-key-for-deals'];
    }

    public function test_create_under_contract_deal_defaults_to_ten_business_days(): void
    {
        Carbon::setTestNow('2026-08-07 10:00:00');
        $lead = $this->createLead();

        $response = $this->postJson('/api/v1/deals', [
            'lead_id' => $lead->id,
            'stage' => 'under_contract',
            'contract_price' => 170000,
        ], $this->headers);

        $response->assertCreated();
        $dealId = $response->json('deal_id');

        $deal = Deal::withoutGlobalScopes()->findOrFail($dealId);
        $this->assertSame(10, $deal->inspection_period_days);
        $this->assertSame('2026-08-07', $deal->contract_date->toDateString());
        $this->assertSame('2026-08-21', $deal->due_diligence_end_date->toDateString());

        Carbon::setTestNow();
    }

    public function test_updating_the_inspection_period_recalculates_the_due_diligence_date(): void
    {
        $deal = $this->createDeal([
            'stage' => 'under_contract',
            'contract_date' => '2026-08-07',
            'inspection_period_days' => 10,
            'due_diligence_end_date' => '2026-08-21',
        ]);

        $response = $this->putJson("/api/v1/deals/{$deal->id}", [
            'inspection_period_days' => 5,
        ], $this->headers);

        $response->assertOk();
        $deal->refresh();
        $this->assertSame(5, $deal->inspection_period_days);
        $this->assertSame('2026-08-14', $deal->due_diligence_end_date->toDateString());
    }

    public function test_changing_stage_to_under_contract_through_the_api_applies_the_default(): void
    {
        Carbon::setTestNow('2026-08-07 10:00:00');
        $deal = $this->createDeal([
            'stage' => 'prospecting',
            'contract_date' => null,
            'inspection_period_days' => null,
            'due_diligence_end_date' => null,
        ]);

        $response = $this->putJson("/api/v1/deals/{$deal->id}", [
            'stage' => 'under_contract',
        ], $this->headers);

        $response->assertOk();
        $deal->refresh();
        $this->assertSame('under_contract', $deal->stage);
        $this->assertSame(10, $deal->inspection_period_days);
        $this->assertSame('2026-08-07', $deal->contract_date->toDateString());
        $this->assertSame('2026-08-21', $deal->due_diligence_end_date->toDateString());

        Carbon::setTestNow();
    }
}
