<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Buyer;
use App\Models\Deal;
use App\Models\DealBuyerMatch;
use App\Models\DealDocument;
use App\Models\GeneratedDocument;
use App\Models\DealLender;
use App\Models\LeadPhoto;
use App\Models\Lender;
use App\Models\LenderLoanProgram;
use Carbon\Carbon;
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

    public function test_admin_can_select_any_crm_buyer_as_the_official_deal_buyer_and_clear_it(): void
    {
        $this->actingAsAdmin();
        $deal = $this->createDeal();
        $buyer = Buyer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Janet',
            'last_name' => 'Buyer',
            'company' => 'Chosen Homes LLC',
        ]);

        $this->patch(route('deals.selectBuyer', $deal), ['buyer_id' => $buyer->id])
            ->assertRedirect();

        $this->assertSame($buyer->id, $deal->fresh()->selected_buyer_id);
        $this->get(route('deals.show', $deal))
            ->assertOk()
            ->assertSee('Buyer for This Deal')
            ->assertSee('Chosen Homes LLC')
            ->assertSee('separate from Notify Buyers');

        $this->patch(route('deals.selectBuyer', $deal), ['buyer_id' => null])
            ->assertRedirect();

        $this->assertNull($deal->fresh()->selected_buyer_id);
    }

    public function test_matched_buyer_can_be_designated_for_documents_even_when_scores_are_tied(): void
    {
        $this->actingAsAdmin();
        $deal = $this->createDeal();
        $firstBuyer = Buyer::factory()->create(['tenant_id' => $this->tenant->id, 'company' => 'First Match LLC']);
        $chosenBuyer = Buyer::factory()->create(['tenant_id' => $this->tenant->id, 'company' => 'Chosen Match LLC']);

        foreach ([$firstBuyer, $chosenBuyer] as $buyer) {
            DealBuyerMatch::create([
                'deal_id' => $deal->id,
                'buyer_id' => $buyer->id,
                'match_score' => 90,
            ]);
        }

        $this->patch(route('deals.selectBuyer', $deal), ['buyer_id' => $chosenBuyer->id])
            ->assertRedirect();

        $this->assertSame($chosenBuyer->id, $deal->fresh()->selected_buyer_id);
        $this->get(route('deals.show', $deal))
            ->assertSee('Chosen Match LLC')
            ->assertSee('Buyer for this deal');
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

    public function test_under_contract_stage_sets_an_editable_ten_business_day_due_diligence_default(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-07 09:00:00')); // Friday

        try {
            $this->actingAsAdmin();
            $deal = $this->createDeal([
                'stage' => 'offer_presented',
                'contract_date' => null,
                'inspection_period_days' => null,
                'due_diligence_end_date' => null,
            ]);

            $response = $this->patch("/pipeline/{$deal->id}/stage", [
                'stage' => 'under_contract',
            ]);

            $response->assertJson(['success' => true]);

            $deal->refresh();
            $this->assertSame(10, $deal->inspection_period_days);
            $this->assertSame('2026-08-07', $deal->contract_date->toDateString());
            $this->assertSame('2026-08-21', $deal->due_diligence_end_date->toDateString());
        } finally {
            Carbon::setTestNow();
        }
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
            'deal_type' => 'wholesale',
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
            'inspection_period_days' => 10,
        ]);

        $pipeline = $this->get('/pipeline');
        $pipeline->assertStatus(200);
        $pipeline->assertSee($property->fresh()->address);
    }

    public function test_pipeline_visibility_page_includes_full_quoted_controls(): void
    {
        $this->actingAsAdmin();
        $this->createDeal(['is_priority' => false]);

        $response = $this->get('/pipeline');

        $response->assertStatus(200);
        $response->assertSee('data-testid="pipeline-summary-bar"', false);
        $response->assertSee('data-testid="pipeline-stage-filter"', false);
        $response->assertSee('data-testid="pipeline-deal-type-filter"', false);
        $response->assertSee('data-testid="pipeline-lender-filter"', false);
        $response->assertSee('data-testid="pipeline-search"', false);
        $response->assertSee('data-testid="pipeline-view-toggle"', false);
        $response->assertSee('data-testid="pipeline-priority-star"', false);
        $response->assertSee('data-testid="pipeline-add-deal"', false);
        $response->assertSee('data-testid="pipeline-move-stage"', false);
        $response->assertSee('Card View');
        $response->assertSee('List View');
        $response->assertSee('Add Deal');
        $response->assertSee('Under Contract');
        $response->assertSee('Dispositions');
        $response->assertSee('Closing');
        $response->assertSee('Closed');
        $response->assertDontSee('In Contract');
        $response->assertDontSee('In Disposition');
        $response->assertDontSee('Pending Close');
        $response->assertDontSee('Closed This Month');
        $response->assertDontSee('Under Inspection');
    }

    public function test_pipeline_stage_colors_are_unique_and_match_summary_tiles(): void
    {
        $this->actingAsAdmin();

        $stageClasses = [
            'prospecting' => 'pipeline-stage-prospecting',
            'contacting' => 'pipeline-stage-contacting',
            'engaging' => 'pipeline-stage-engaging',
            'offer_presented' => 'pipeline-stage-offer-presented',
            'under_contract' => 'pipeline-stage-under-contract',
            'dispositions' => 'pipeline-stage-dispositions',
            'assigned' => 'pipeline-stage-assigned',
            'closing' => 'pipeline-stage-closing',
            'closed_won' => 'pipeline-stage-closed-won',
            'closed_lost' => 'pipeline-stage-closed-lost',
        ];

        $this->assertSameSize($stageClasses, array_unique($stageClasses));

        foreach (array_keys($stageClasses) as $stage) {
            $this->createDeal(['stage' => $stage]);
        }

        $response = $this->get('/pipeline');

        $response->assertStatus(200);
        foreach ($stageClasses as $stageClass) {
            $response->assertSee('class="badge ' . $stageClass . ' pipeline-stage-badge"', false);
        }

        $response->assertSee('class="summary-icon pipeline-stage-under-contract"', false);
        $response->assertSee('class="summary-icon pipeline-stage-dispositions"', false);
        $response->assertSee('class="summary-icon pipeline-stage-closing"', false);
        $response->assertSee('class="summary-icon pipeline-stage-closed-won"', false);
    }

    public function test_pipeline_card_renders_expected_public_deal_fields_without_internal_fee(): void
    {
        $this->actingAsAdmin();
        $lead = $this->createLead(['first_name' => 'Latanya', 'last_name' => 'White']);
        $property = $this->createProperty([
            'lead_id' => $lead->id,
            'address' => '1234 Oakwood Dr SW',
            'city' => 'Atlanta',
            'state' => 'GA',
            'zip_code' => '30310',
            'asking_price' => 245000,
            'after_repair_value' => 425000,
            'repair_estimate' => 65500,
            'our_offer' => 218000,
            'mao_percentage' => 70,
        ]);
        $deal = $this->createDeal([
            'lead_id' => $lead->id,
            'stage' => 'under_contract',
            'deal_type' => 'fix_and_flip',
            'contract_price' => 218000,
            'assignment_fee' => 14500,
            'is_priority' => true,
            'stage_changed_at' => now()->subDays(2),
        ]);
        $lender = Lender::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Alliance Capital Contact',
            'company' => 'Alliance Capital',
        ]);
        $program = LenderLoanProgram::create([
            'tenant_id' => $this->tenant->id,
            'lender_id' => $lender->id,
            'program_name' => 'Fix and Flip',
        ]);
        DealLender::create([
            'deal_id' => $deal->id,
            'lender_id' => $lender->id,
            'lender_loan_program_id' => $program->id,
        ]);
        LeadPhoto::create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $lead->id,
            'uploaded_by' => $this->adminUser->id,
            'filename' => 'front.jpg',
            'original_name' => 'front.jpg',
            'path' => 'leads/photos/front.jpg',
            'thumbnail_path' => 'leads/photos/thumb-front.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1000,
            'caption' => 'Front exterior',
        ]);
        LeadPhoto::create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $lead->id,
            'uploaded_by' => $this->adminUser->id,
            'filename' => 'back.jpg',
            'original_name' => 'back.jpg',
            'path' => 'leads/photos/back.jpg',
            'thumbnail_path' => 'leads/photos/thumb-back.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1000,
            'caption' => 'Back exterior',
        ]);

        $response = $this->get('/pipeline');

        $response->assertStatus(200);
        $response->assertSee('data-testid="pipeline-deal-card"', false);
        $response->assertSee('data-testid="pipeline-photo-trigger"', false);
        $response->assertSee('Property Photos');
        $response->assertSee('pipeline-lightbox-next');
        $response->assertSee('pipeline-lightbox-prev');
        $response->assertSee($property->address);
        $response->assertSee('Atlanta');
        $response->assertSee('Under Contract');
        $response->assertSee('Asking Price');
        $response->assertSee('$245,000');
        $response->assertSee('Contract Price');
        $response->assertSee('$218,000');
        $response->assertSee('ARV');
        $response->assertSee('$425,000');
        $response->assertSee('Max Offer / MAO');
        $response->assertSee('$232,000');
        $response->assertSee('Alliance Capital');
        $response->assertSee('Fix &amp; Flip', false);
        $response->assertSee('2 Days in Stage');
        $response->assertDontSee('Assignment Fee');
        $response->assertDontSee('Estimated Profit');
        $response->assertDontSee('Internal Spread');
    }

    public function test_pipeline_search_finds_deals_by_address_seller_and_title(): void
    {
        $this->actingAsAdmin();
        $lead = $this->createLead(['first_name' => 'UniqueSeller', 'last_name' => 'Pipeline']);
        $property = $this->createProperty([
            'lead_id' => $lead->id,
            'address' => '777 Searchable Avenue',
        ]);
        $this->createDeal([
            'lead_id' => $lead->id,
            'title' => 'Unique Pipeline Deal',
            'is_priority' => false,
        ]);
        $otherLead = $this->createLead(['first_name' => 'Other', 'last_name' => 'Seller']);
        $this->createProperty([
            'lead_id' => $otherLead->id,
            'address' => '999 Hidden Road',
        ]);
        $this->createDeal([
            'lead_id' => $otherLead->id,
            'title' => 'Hidden Pipeline Deal',
            'is_priority' => false,
        ]);

        $this->get('/pipeline?search=Searchable')
            ->assertStatus(200)
            ->assertSee($property->address)
            ->assertDontSee('999 Hidden Road');

        $this->get('/pipeline?search=UniqueSeller')
            ->assertStatus(200)
            ->assertSee($property->address)
            ->assertDontSee('999 Hidden Road');

        $this->get('/pipeline?search=' . urlencode('UniqueSeller Pipeline'))
            ->assertStatus(200)
            ->assertSee($property->address)
            ->assertDontSee('999 Hidden Road');

        $this->get('/pipeline?search=Unique+Pipeline')
            ->assertStatus(200)
            ->assertSee($property->address)
            ->assertDontSee('999 Hidden Road');
    }

    public function test_pipeline_filters_by_stage_deal_type_and_lender(): void
    {
        $this->actingAsAdmin();
        $matchingLead = $this->createLead();
        $matchingProperty = $this->createProperty([
            'lead_id' => $matchingLead->id,
            'address' => '456 Filter Match Ln',
        ]);
        $matchingDeal = $this->createDeal([
            'lead_id' => $matchingLead->id,
            'stage' => 'under_contract',
            'deal_type' => 'rental',
            'is_priority' => false,
        ]);
        $otherLead = $this->createLead();
        $this->createProperty([
            'lead_id' => $otherLead->id,
            'address' => '789 Filter Miss Rd',
        ]);
        $this->createDeal([
            'lead_id' => $otherLead->id,
            'stage' => 'prospecting',
            'deal_type' => 'wholesale',
            'is_priority' => false,
        ]);
        $lender = Lender::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Filter Lender',
        ]);
        $program = LenderLoanProgram::create([
            'tenant_id' => $this->tenant->id,
            'lender_id' => $lender->id,
            'program_name' => 'Rental DSCR',
        ]);
        DealLender::create([
            'deal_id' => $matchingDeal->id,
            'lender_id' => $lender->id,
            'lender_loan_program_id' => $program->id,
        ]);

        $this->get('/pipeline?stage=under_contract')
            ->assertStatus(200)
            ->assertSee($matchingProperty->address)
            ->assertDontSee('789 Filter Miss Rd');

        $this->get('/pipeline?deal_type=rental')
            ->assertStatus(200)
            ->assertSee($matchingProperty->address)
            ->assertDontSee('789 Filter Miss Rd');

        $this->get('/pipeline?lender=' . $lender->id)
            ->assertStatus(200)
            ->assertSee($matchingProperty->address)
            ->assertDontSee('789 Filter Miss Rd');
    }

    public function test_pipeline_list_view_renders_same_deal_set(): void
    {
        $this->actingAsAdmin();
        $lead = $this->createLead();
        $property = $this->createProperty([
            'lead_id' => $lead->id,
            'address' => '321 List View Way',
        ]);
        $this->createDeal([
            'lead_id' => $lead->id,
            'deal_type' => 'other',
            'is_priority' => false,
        ]);

        $response = $this->get('/pipeline?view=list');

        $response->assertStatus(200);
        $response->assertSee('data-testid="pipeline-list-results"', false);
        $response->assertSee('data-testid="pipeline-list-row"', false);
        $response->assertSee($property->address);
        $response->assertSee('Max Offer / MAO');
        $response->assertSee('Days in Stage');
    }

    public function test_deal_type_can_be_set_from_deal_edit_form(): void
    {
        $this->actingAsAdmin();
        $deal = $this->createDeal(['deal_type' => null]);

        $show = $this->get("/pipeline/{$deal->id}");
        $show->assertStatus(200);
        $show->assertSee('name="deal_type"', false);

        $response = $this->putJson("/pipeline/{$deal->id}", [
            'deal_type' => 'wholesale',
        ]);

        $response->assertOk();
        $this->assertEquals('wholesale', $deal->fresh()->deal_type);
    }

    public function test_pipeline_priority_star_toggle_persists(): void
    {
        $this->actingAsAdmin();
        $deal = $this->createDeal([
            'is_priority' => false,
        ]);

        $response = $this->patchJson("/pipeline/{$deal->id}/priority");

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'is_priority' => true,
        ]);
        $this->assertTrue($deal->fresh()->is_priority);
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

    public function test_admin_can_delete_an_uploaded_deal_document(): void
    {
        Storage::fake('local');
        $this->actingAsAdmin();
        $deal = $this->createDeal();
        $document = DealDocument::create([
            'tenant_id' => $this->tenant->id,
            'deal_id' => $deal->id,
            'filename' => 'inspection.pdf',
            'original_name' => 'Inspection.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'path' => "deals/{$deal->id}/inspection.pdf",
        ]);
        Storage::disk('local')->put($document->path, 'test document');

        $response = $this->delete(route('deals.destroyDocument', [$deal, $document]));

        $response->assertRedirect(route('deals.show', $deal));
        $this->assertDatabaseMissing('deal_documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing($document->path);
    }

    public function test_admin_can_delete_a_deal_from_the_pipeline_and_its_files_are_removed(): void
    {
        Storage::fake('local');
        $this->actingAsAdmin();
        $deal = $this->createDeal();
        $uploadedDocument = DealDocument::create([
            'tenant_id' => $this->tenant->id,
            'deal_id' => $deal->id,
            'filename' => 'contract.pdf',
            'original_name' => 'Contract.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'path' => "deals/{$deal->id}/contract.pdf",
        ]);
        $generatedDocument = GeneratedDocument::create([
            'tenant_id' => $this->tenant->id,
            'deal_id' => $deal->id,
            'name' => 'Purchase Agreement',
            'content' => '<p>Original purchase agreement</p>',
            'pdf_path' => "generated-documents/{$deal->id}/purchase-agreement.pdf",
        ]);
        Storage::disk('local')->put($uploadedDocument->path, 'uploaded document');
        Storage::disk('local')->put($generatedDocument->pdf_path, 'generated document');

        $this->get('/pipeline')
            ->assertOk()
            ->assertSee('data-testid="pipeline-delete-deal"', false)
            ->assertSee('Delete Deal');

        $response = $this->delete(route('deals.destroy', $deal));

        $response->assertRedirect(route('pipeline'));
        $this->assertDatabaseMissing('deals', ['id' => $deal->id]);
        $this->assertDatabaseMissing('deal_documents', ['id' => $uploadedDocument->id]);
        $this->assertDatabaseMissing('generated_documents', ['id' => $generatedDocument->id]);
        Storage::disk('local')->assertMissing($uploadedDocument->path);
        Storage::disk('local')->assertMissing($generatedDocument->pdf_path);
    }

    public function test_agent_cannot_delete_another_agents_deal_from_the_pipeline(): void
    {
        $this->actingAsAdmin();
        $deal = $this->createDeal();
        $otherAgent = $this->createUserWithRole('agent');

        $this->actingAs($otherAgent)
            ->delete(route('deals.destroy', $deal))
            ->assertForbidden();

        $this->assertDatabaseHas('deals', ['id' => $deal->id]);
    }

    public function test_assignment_fee_is_hidden_and_cleared_for_a_fix_and_flip_deal(): void
    {
        $this->actingAsAdmin();
        $deal = $this->createDeal([
            'deal_type' => 'fix_and_flip',
            'assignment_fee' => 12500,
        ]);

        $this->get(route('deals.show', $deal))
            ->assertOk()
            ->assertDontSee('Assignment Fee');

        $this->putJson(route('deals.update', $deal), [
            'deal_type' => 'fix_and_flip',
            'contract_price' => 180000,
        ])->assertOk();

        $this->assertNull($deal->fresh()->assignment_fee);
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
