<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\RehabLineItem;
use App\Services\CustomFieldService;
use Tests\TestCase;

class RehabTrackerTest extends TestCase
{
    private function makeContractor(array $overrides = []): Contractor
    {
        return Contractor::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Acme Roofing',
            'phone' => '555-0100',
            'email' => 'acme@example.com',
            'specialty' => ['roofing'],
            'service_area' => 'Atlanta Metro',
            'priority' => 'medium',
            'status' => 'hired',
        ], $overrides));
    }

    public function test_admin_can_create_rehab_line_item_from_deal(): void
    {
        $this->actingAsAdmin();
        $deal = $this->createDeal();
        $contractor = $this->makeContractor();

        $response = $this->post("/pipeline/{$deal->id}/rehab-items", [
            'line_item' => 'Roof replacement',
            'category' => 'exterior',
            'budgeted_cost' => 10000,
            'estimated_duration_days' => 5,
            'contractor_id' => $contractor->id,
            'status' => 'not_started',
            'amount_paid' => 2500,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('rehab_line_items', [
            'tenant_id' => $this->tenant->id,
            'deal_id' => $deal->id,
            'contractor_id' => $contractor->id,
            'line_item' => 'Roof replacement',
            'category' => 'exterior',
            'budgeted_cost' => 10000,
            'estimated_duration_days' => 5,
            'amount_paid' => 2500,
            'status' => 'not_started',
        ]);
        $this->assertEquals(7500.0, RehabLineItem::first()->remaining_balance);
    }

    public function test_rehab_line_item_renders_on_deal_detail_page(): void
    {
        $this->actingAsAdmin();
        $deal = $this->createDeal();
        $contractor = $this->makeContractor();

        RehabLineItem::create([
            'tenant_id' => $this->tenant->id,
            'deal_id' => $deal->id,
            'contractor_id' => $contractor->id,
            'line_item' => 'Kitchen cabinets',
            'category' => 'kitchen',
            'budgeted_cost' => 12000,
            'estimated_duration_days' => 7,
            'amount_paid' => 4000,
            'status' => 'in_progress',
        ]);

        $response = $this->get("/pipeline/{$deal->id}");

        $response->assertStatus(200);
        $response->assertSee('Rehab Tracker');
        $response->assertSee('Kitchen cabinets');
        $response->assertSee('Kitchen');
        $response->assertSee('Acme Roofing');
        $response->assertSee('$12,000.00');
        $response->assertSee('$4,000.00');
        $response->assertSee('$8,000.00');
    }

    public function test_rehab_tracker_renders_requested_field_order(): void
    {
        $this->actingAsAdmin();
        $deal = $this->createDeal();

        $response = $this->get("/pipeline/{$deal->id}");

        $response->assertStatus(200);
        $response->assertSeeInOrder([
            'Category',
            'Line Item',
            'Budget',
            'Duration',
            'Contractor Assigned',
            'Status',
            'Amount',
        ]);
    }

    public function test_admin_can_use_custom_rehab_category(): void
    {
        $this->actingAsAdmin();
        $deal = $this->createDeal();
        CustomFieldService::addOption('rehab_category', 'Landscaping', $this->tenant);

        $this->post("/pipeline/{$deal->id}/rehab-items", [
            'line_item' => 'Sod and cleanup',
            'category' => 'landscaping',
            'budgeted_cost' => 1800,
            'status' => 'not_started',
            'amount_paid' => 0,
        ])->assertRedirect();

        $this->assertDatabaseHas('rehab_line_items', [
            'deal_id' => $deal->id,
            'category' => 'landscaping',
            'line_item' => 'Sod and cleanup',
        ]);
    }

    public function test_admin_can_update_rehab_line_item_and_remaining_balance_recalculates(): void
    {
        $this->actingAsAdmin();
        $deal = $this->createDeal();

        $item = RehabLineItem::create([
            'tenant_id' => $this->tenant->id,
            'deal_id' => $deal->id,
            'line_item' => 'Flooring',
            'category' => 'floors',
            'budgeted_cost' => 8000,
            'estimated_duration_days' => 3,
            'amount_paid' => 1000,
            'status' => 'not_started',
        ]);

        $this->patch("/rehab-items/{$item->id}", [
            'line_item' => 'Flooring install',
            'category' => 'floors',
            'budgeted_cost' => 8500,
            'estimated_duration_days' => 4,
            'contractor_id' => null,
            'status' => 'in_progress',
            'amount_paid' => 2500,
        ])->assertRedirect();

        $item->refresh();
        $this->assertEquals('Flooring install', $item->line_item);
        $this->assertEquals('in_progress', $item->status);
        $this->assertEquals('8500.00', $item->budgeted_cost);
        $this->assertEquals('2500.00', $item->amount_paid);
        $this->assertEquals(6000.0, $item->remaining_balance);
    }

    public function test_admin_can_delete_rehab_line_item(): void
    {
        $this->actingAsAdmin();
        $deal = $this->createDeal();

        $item = RehabLineItem::create([
            'tenant_id' => $this->tenant->id,
            'deal_id' => $deal->id,
            'line_item' => 'Paint',
            'category' => 'interior_paint_and_drywall',
            'budgeted_cost' => 3000,
            'amount_paid' => 0,
            'status' => 'not_started',
        ]);

        $this->delete("/rehab-items/{$item->id}")->assertRedirect();

        $this->assertDatabaseMissing('rehab_line_items', ['id' => $item->id]);
    }

    public function test_foreign_tenant_contractor_cannot_be_assigned_to_rehab_line_item(): void
    {
        $this->actingAsAdmin();
        $foreignContractor = $this->makeContractor(['name' => 'Foreign Contractor']);

        $this->createTenantWithAdmin(['slug' => 'second-co', 'email' => 'admin2@test.com']);
        $this->actingAs($this->adminUser);
        $deal = $this->createDeal();

        $response = $this->post("/pipeline/{$deal->id}/rehab-items", [
            'line_item' => 'HVAC',
            'category' => 'hvac',
            'budgeted_cost' => 9000,
            'contractor_id' => $foreignContractor->id,
            'status' => 'not_started',
            'amount_paid' => 0,
        ]);

        $response->assertSessionHasErrors('contractor_id');
    }

    public function test_assigned_agent_can_manage_rehab_line_items(): void
    {
        $this->createTenantWithAdmin();
        $agent = $this->actingAsRole('agent');
        $deal = $this->createDeal(['agent_id' => $agent->id]);

        $this->post("/pipeline/{$deal->id}/rehab-items", [
            'line_item' => 'Electrical rough-in',
            'category' => 'electrical',
            'budgeted_cost' => 4500,
            'estimated_duration_days' => 2,
            'status' => 'not_started',
            'amount_paid' => 0,
        ])->assertRedirect();

        $this->assertDatabaseHas('rehab_line_items', [
            'tenant_id' => $this->tenant->id,
            'deal_id' => $deal->id,
            'line_item' => 'Electrical rough-in',
        ]);
    }

    public function test_unassigned_agent_cannot_manage_rehab_line_items(): void
    {
        $this->createTenantWithAdmin();
        $deal = $this->createDeal(['agent_id' => $this->adminUser->id]);
        $this->actingAsRole('agent');

        $this->post("/pipeline/{$deal->id}/rehab-items", [
            'line_item' => 'Plumbing repair',
            'category' => 'plumbing_and_baths',
            'budgeted_cost' => 2200,
            'status' => 'not_started',
            'amount_paid' => 0,
        ])->assertStatus(403);
    }
}
