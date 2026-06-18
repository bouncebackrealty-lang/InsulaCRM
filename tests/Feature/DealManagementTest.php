<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Buyer;
use App\Models\Deal;
use App\Models\DealBuyerMatch;
use App\Models\DealDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        $this->assertDatabaseHas('activities', [
            'deal_id' => $deal->id,
            'type' => 'stage_change',
        ]);
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

    public function test_buyer_matching_runs_on_disposition_stage_and_shows_profile_link(): void
    {
        $this->actingAsAdmin();
        $lead = $this->createLead();
        $property = $this->createProperty([
            'lead_id' => $lead->id,
            'state' => 'GA',
            'zip_code' => '30301',
            'property_type' => 'single_family',
        ]);
        $deal = $this->createDeal([
            'lead_id' => $lead->id,
            'contract_price' => 150000,
        ]);
        $buyer = Buyer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Latanya',
            'last_name' => 'White',
            'company' => 'Bounce Back Realty',
            'max_purchase_price' => 250000,
            'preferred_property_types' => ['single_family'],
            'preferred_zip_codes' => [$property->zip_code],
            'preferred_states' => [$property->state],
        ]);

        $response = $this->patch("/pipeline/{$deal->id}/stage", [
            'stage' => 'dispositions',
        ]);

        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('deal_buyer_matches', [
            'deal_id' => $deal->id,
            'buyer_id' => $buyer->id,
            'match_score' => 90,
        ]);

        $dealPage = $this->get("/pipeline/{$deal->id}");
        $dealPage->assertStatus(200);
        $dealPage->assertSee('Bounce Back Realty');
        $dealPage->assertSee(route('buyers.show', $buyer), false);
    }

    public function test_admin_can_manually_upload_document_to_deal(): void
    {
        Storage::fake('local');
        $this->actingAsAdmin();
        $deal = $this->createDeal();

        $response = $this->post("/pipeline/{$deal->id}/documents", [
            'document' => UploadedFile::fake()->create('purchase-contract.pdf', 100, 'application/pdf'),
        ]);

        $response->assertRedirect();
        $document = DealDocument::where('deal_id', $deal->id)->first();
        $this->assertNotNull($document);
        $this->assertSame($this->tenant->id, $document->tenant_id);
        $this->assertSame('purchase-contract.pdf', $document->original_name);
        Storage::disk('local')->assertExists($document->path);
    }

    public function test_closed_won_preserves_documents_buyer_matches_and_activity_history(): void
    {
        $this->actingAsAdmin();
        $deal = $this->createDeal(['stage' => 'closing']);
        $buyer = Buyer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company' => 'Bounce Back Realty',
        ]);

        DealDocument::create([
            'tenant_id' => $this->tenant->id,
            'deal_id' => $deal->id,
            'filename' => 'closing-doc.pdf',
            'original_name' => 'Closing Package.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'path' => "deals/{$deal->id}/closing-doc.pdf",
        ]);

        DealBuyerMatch::create([
            'deal_id' => $deal->id,
            'buyer_id' => $buyer->id,
            'match_score' => 75,
        ]);

        Activity::create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $deal->lead_id,
            'deal_id' => $deal->id,
            'agent_id' => $this->adminUser->id,
            'type' => 'note',
            'subject' => 'Manual closing note',
            'body' => 'Ready for final review.',
            'logged_at' => now(),
        ]);

        $response = $this->patch("/pipeline/{$deal->id}/stage", [
            'stage' => 'closed_won',
        ]);

        $response->assertJson(['success' => true]);
        $dealPage = $this->get("/pipeline/{$deal->id}");
        $dealPage->assertStatus(200);
        $dealPage->assertSee('Closed Won');
        $dealPage->assertSee('Closing Package.pdf');
        $dealPage->assertSee('Bounce Back Realty');
        $dealPage->assertSee('Manual closing note');
        $dealPage->assertSee('Deal stage changed');
    }
}
