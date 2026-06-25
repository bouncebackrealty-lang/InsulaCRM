<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\DealContractor;
use Tests\TestCase;

class ContractorManagementTest extends TestCase
{
    private function makeContractor(array $overrides = []): Contractor
    {
        return Contractor::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Acme Roofing',
            'phone' => '555-0100',
            'email' => 'acme@example.com',
            'specialty' => ['roofing', 'hvac'],
            'service_area' => 'Atlanta Metro',
            'priority' => 'high',
            'status' => 'bid_submitted',
        ], $overrides));
    }

    public function test_admin_can_view_contractors_index(): void
    {
        $this->actingAsAdmin();
        $this->makeContractor();

        $response = $this->get('/contractors');
        $response->assertStatus(200);
        $response->assertSee('Acme Roofing');
    }

    public function test_admin_can_view_create_form(): void
    {
        $this->actingAsAdmin();

        $this->get('/contractors/create')->assertStatus(200);
    }

    public function test_admin_can_create_contractor_with_multiple_specialties(): void
    {
        $this->actingAsAdmin();

        $response = $this->post('/contractors', [
            'name' => 'Bright Electric',
            'phone' => '555-0111',
            'email' => 'bright@example.com',
            'specialty' => ['electrical', 'general_contractor'],
            'service_area' => 'GA',
            'priority' => 'medium',
            'status' => 'contacted',
            'notes' => 'Reliable',
        ]);

        $contractor = Contractor::where('name', 'Bright Electric')->first();
        $response->assertRedirect("/contractors/{$contractor->id}");
        $this->assertSame(['electrical', 'general_contractor'], $contractor->specialty);
        $this->assertDatabaseHas('contractors', [
            'name' => 'Bright Electric',
            'tenant_id' => $this->tenant->id,
            'priority' => 'medium',
        ]);
    }

    public function test_invalid_priority_is_rejected(): void
    {
        $this->actingAsAdmin();

        $response = $this->post('/contractors', [
            'name' => 'Bad Priority',
            'priority' => 'urgent',
            'status' => 'contacted',
        ]);

        $response->assertSessionHasErrors('priority');
    }

    public function test_name_is_required(): void
    {
        $this->actingAsAdmin();

        $response = $this->post('/contractors', [
            'priority' => 'medium',
            'status' => 'contacted',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_admin_can_view_and_update_contractor(): void
    {
        $this->actingAsAdmin();
        $contractor = $this->makeContractor();

        $this->get("/contractors/{$contractor->id}")->assertStatus(200);

        $response = $this->put("/contractors/{$contractor->id}", [
            'name' => 'Acme Roofing & Gutters',
            'priority' => 'low',
            'status' => 'hired',
            'specialty' => ['roofing'],
        ]);

        $response->assertRedirect("/contractors/{$contractor->id}");
        $contractor->refresh();
        $this->assertEquals('Acme Roofing & Gutters', $contractor->name);
        $this->assertEquals('hired', $contractor->status);
        $this->assertSame(['roofing'], $contractor->specialty);
    }

    public function test_admin_can_delete_contractor(): void
    {
        $this->actingAsAdmin();
        $contractor = $this->makeContractor();

        $this->delete("/contractors/{$contractor->id}")->assertRedirect('/contractors');
        $this->assertDatabaseMissing('contractors', ['id' => $contractor->id]);
    }

    public function test_admin_can_attach_contractor_to_deal_and_deal_page_renders(): void
    {
        $this->actingAsAdmin();
        $contractor = $this->makeContractor();
        $deal = $this->createDeal();

        $response = $this->post("/pipeline/{$deal->id}/contractors", [
            'contractor_id' => $contractor->id,
            'quoted_amount' => 5000,
            'accepted_amount' => 4500,
        ]);
        $response->assertRedirect();

        $this->assertDatabaseHas('deal_contractors', [
            'deal_id' => $deal->id,
            'contractor_id' => $contractor->id,
            'quoted_amount' => 5000,
            'accepted_amount' => 4500,
        ]);

        // The deal show page renders the Contractors card with the attached contractor
        $show = $this->get("/pipeline/{$deal->id}");
        $show->assertStatus(200);
        $show->assertSee('Acme Roofing');
    }

    public function test_same_contractor_cannot_be_attached_twice(): void
    {
        $this->actingAsAdmin();
        $contractor = $this->makeContractor();
        $deal = $this->createDeal();
        DealContractor::create(['deal_id' => $deal->id, 'contractor_id' => $contractor->id]);

        $response = $this->post("/pipeline/{$deal->id}/contractors", [
            'contractor_id' => $contractor->id,
        ]);

        $response->assertSessionHasErrors('contractor_id');
    }

    public function test_admin_can_update_and_detach_deal_contractor(): void
    {
        $this->actingAsAdmin();
        $contractor = $this->makeContractor();
        $deal = $this->createDeal();
        $dc = DealContractor::create(['deal_id' => $deal->id, 'contractor_id' => $contractor->id]);

        $this->patch("/deal-contractors/{$dc->id}", ['quoted_amount' => 7200])->assertRedirect();
        $this->assertEquals('7200.00', $dc->fresh()->quoted_amount);

        $this->delete("/deal-contractors/{$dc->id}")->assertRedirect();
        $this->assertDatabaseMissing('deal_contractors', ['id' => $dc->id]);
    }

    public function test_acquisition_agent_can_access_contractors(): void
    {
        $this->createTenantWithAdmin();
        $this->actingAsRole('acquisition_agent');

        $this->get('/contractors')->assertStatus(200);
    }

    public function test_field_scout_cannot_access_contractors(): void
    {
        $this->createTenantWithAdmin();
        $this->actingAsRole('field_scout');

        $this->get('/contractors')->assertStatus(403);
    }

    public function test_contractor_is_scoped_to_tenant(): void
    {
        // Contractor belongs to first tenant
        $this->actingAsAdmin();
        $foreign = $this->makeContractor(['name' => 'Foreign Co']);

        // Switch to a second tenant's admin
        $this->createTenantWithAdmin(['slug' => 'second-co', 'email' => 'admin2@test.com']);
        $this->actingAs($this->adminUser);

        $this->get('/contractors')->assertDontSee('Foreign Co');
        $this->get("/contractors/{$foreign->id}")->assertStatus(404);
    }
}
