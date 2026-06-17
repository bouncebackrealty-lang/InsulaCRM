<?php

namespace Tests\Feature;

use App\Models\Deal;
use Tests\TestCase;

class DealManagementTest extends TestCase
{
    public function test_admin_can_view_pipeline(): void
    {
        $this->actingAsAdmin();

        $response = $this->get('/pipeline');
        $response->assertStatus(200);
    }

    public function test_admin_can_view_deal_detail(): void
    {
        $this->actingAsAdmin();
        $deal = $this->createDeal();

        $response = $this->get("/pipeline/{$deal->id}");
        $response->assertStatus(200);
    }

    public function test_admin_can_update_deal_stage(): void
    {
        $this->actingAsAdmin();
        $deal = $this->createDeal(['stage' => 'prospecting']);

        $response = $this->patch("/pipeline/{$deal->id}/stage", [
            'stage' => 'under_contract',
        ]);

        $response->assertJson(['success' => true]);
        $this->assertEquals('under_contract', $deal->fresh()->stage);
    }

    public function test_field_scout_cannot_access_pipeline(): void
    {
        $this->createTenantWithAdmin();
        $this->actingAsRole('field_scout');

        $response = $this->get('/pipeline');
        $response->assertStatus(403);
    }

    public function test_disposition_agent_can_access_pipeline(): void
    {
        $this->createTenantWithAdmin();
        $this->actingAsRole('disposition_agent');

        $response = $this->get('/pipeline');
        $response->assertStatus(200);
    }

    public function test_admin_can_create_deal_from_lead_and_see_it_in_pipeline(): void
    {
        $this->actingAsAdmin();
        $lead = $this->createLead(['first_name' => 'Latanya', 'last_name' => 'White']);
        $property = $this->createProperty([
            'lead_id' => $lead->id,
            'address' => '123 Main St',
            'city' => 'Atlanta',
            'state' => 'GA',
            'zip_code' => '30301',
            'after_repair_value' => 200000,
            'repair_estimate' => 20000,
            'our_offer' => 90000,
        ]);

        $response = $this->post("/leads/{$lead->id}/deals");

        $deal = Deal::where('lead_id', $lead->id)->first();
        $response->assertRedirect("/pipeline/{$deal->id}");
        $this->assertDatabaseHas('deals', [
            'tenant_id' => $this->tenant->id,
            'lead_id' => $lead->id,
            'agent_id' => $this->adminUser->id,
            'stage' => 'prospecting',
            'contract_price' => 90000,
            'assignment_fee' => 30000,
        ]);

        $pipeline = $this->get('/pipeline');
        $pipeline->assertStatus(200);
        $pipeline->assertSee($property->fresh()->address);
    }

    public function test_creating_deal_from_lead_is_idempotent(): void
    {
        $this->actingAsAdmin();
        $lead = $this->createLead();
        $existingDeal = $this->createDeal(['lead_id' => $lead->id]);

        $response = $this->post("/leads/{$lead->id}/deals");

        $response->assertRedirect("/pipeline/{$existingDeal->id}");
        $this->assertSame(1, Deal::where('lead_id', $lead->id)->count());
    }

    public function test_deal_created_from_unassigned_lead_falls_back_to_current_user(): void
    {
        $this->actingAsAdmin();
        $lead = $this->createLead(['agent_id' => null]);

        $this->post("/leads/{$lead->id}/deals");

        $deal = Deal::where('lead_id', $lead->id)->first();
        $this->assertEquals($this->adminUser->id, $deal->agent_id);
        $this->assertEquals($this->tenant->id, $deal->tenant_id);
        $this->assertEquals('prospecting', $deal->stage);
    }
}
