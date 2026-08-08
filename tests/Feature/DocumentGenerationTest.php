<?php

namespace Tests\Feature;

use App\Models\ComparableSale;
use App\Models\Contractor;
use App\Models\Buyer;
use App\Models\DealBuyerMatch;
use App\Models\DealContractor;
use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Services\DocumentMergeService;
use Tests\TestCase;

class DocumentGenerationTest extends TestCase
{
    public function test_document_generation_merges_deal_property_and_selected_contractor_data(): void
    {
        $this->actingAsAdmin();

        $lead = $this->createLead(['first_name' => 'Marcus', 'last_name' => 'Dowd']);
        $this->createProperty([
            'lead_id' => $lead->id,
            'address' => '123 Test Street',
            'city' => 'Atlanta',
            'state' => 'GA',
            'zip_code' => '30318',
            'property_type' => 'single_family',
        ]);
        $deal = $this->createDeal([
            'lead_id' => $lead->id,
            'contract_price' => 170000,
            'earnest_money' => 1700,
        ]);
        $contractor = Contractor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Acme Renovations',
            'phone' => '404-555-0100',
            'email' => 'bids@acme.test',
            'specialty' => ['roofing'],
            'service_area' => 'Atlanta',
            'status' => 'hired',
        ]);
        DealContractor::create([
            'deal_id' => $deal->id,
            'contractor_id' => $contractor->id,
        ]);
        $template = DocumentTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Contractor test document',
            'type' => 'other',
            'content' => '<p>{{property.full_address}}</p><p>{{deal.contract_price}}</p><p>{{contractor.name}}</p><p>{{contractor.trade}}</p>',
        ]);

        $response = $this->post(route('documents.store', $deal), [
            'template_id' => $template->id,
            'contractor_id' => $contractor->id,
        ]);

        $document = GeneratedDocument::latest('id')->firstOrFail();
        $response->assertRedirect(route('documents.show', $document));
        $this->assertStringContainsString('123 Test Street, Atlanta, GA 30318', $document->content);
        $this->assertStringContainsString('$170,000.00', $document->content);
        $this->assertStringContainsString('Acme Renovations', $document->content);
        $this->assertStringContainsString('Roofing', $document->content);
    }

    public function test_document_generation_defaults_to_the_only_attached_contractor(): void
    {
        $this->actingAsAdmin();

        $deal = $this->createDeal();
        $contractor = Contractor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Only Attached Contractor',
        ]);
        DealContractor::create([
            'deal_id' => $deal->id,
            'contractor_id' => $contractor->id,
        ]);
        $template = DocumentTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Single contractor default',
            'type' => 'other',
            'content' => '<p>{{contractor.name}}</p>',
        ]);

        $this->get(route('documents.generate', $deal))
            ->assertOk()
            ->assertSee('When one contractor is attached to the deal, it is used automatically.');

        $this->post(route('documents.store', $deal), [
            'template_id' => $template->id,
        ]);

        $this->assertStringContainsString(
            'Only Attached Contractor',
            GeneratedDocument::latest('id')->value('content'),
        );
    }

    public function test_generated_document_can_be_edited_without_changing_the_master_template(): void
    {
        $this->actingAsAdmin();
        $deal = $this->createDeal();
        $template = DocumentTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Editable template',
            'type' => 'other',
            'content' => '<p>Master wording</p>',
        ]);
        $document = GeneratedDocument::create([
            'tenant_id' => $this->tenant->id,
            'deal_id' => $deal->id,
            'template_id' => $template->id,
            'user_id' => $this->adminUser->id,
            'name' => 'Editable copy',
            'content' => '<p>Original generated wording</p>',
        ]);

        $this->get(route('documents.edit', $document))
            ->assertOk()
            ->assertSee('Edit generated document')
            ->assertSee('Original generated wording');

        $response = $this->put(route('documents.update', $document), [
            'name' => 'Corrected generated copy',
            'content' => '<p>Corrected wording</p><script>alert("remove me")</script>',
        ]);

        $response->assertRedirect(route('documents.show', $document));
        $document->refresh();
        $this->assertSame('Corrected generated copy', $document->name);
        $this->assertStringContainsString('Corrected wording', $document->content);
        $this->assertStringNotContainsString('<script>', $document->content);
        $this->assertSame('<p>Master wording</p>', $template->fresh()->content);
    }

    public function test_currency_merge_uses_one_dollar_symbol_when_template_includes_a_static_symbol(): void
    {
        $this->actingAsAdmin();
        $deal = $this->createDeal(['contract_price' => 170000]);
        $template = DocumentTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Currency label template',
            'type' => 'other',
            'content' => '<p>Purchase Price: $<span class="inline-line">{{deal.contract_price}}</span></p>',
        ]);

        $rendered = app(DocumentMergeService::class)->merge($template->content, $deal);

        $this->assertStringContainsString('Purchase Price: $<span class="inline-line">170,000.00</span>', $rendered);
        $this->assertStringNotContainsString('$$170,000.00', $rendered);
    }

    public function test_loi_merge_uses_the_top_matched_buyer_and_falls_back_to_the_company_line(): void
    {
        $this->actingAsAdmin();
        $deal = $this->createDeal();
        $buyer = Buyer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company' => 'Top Match Investments',
        ]);
        DealBuyerMatch::create([
            'deal_id' => $deal->id,
            'buyer_id' => $buyer->id,
            'match_score' => 99,
        ]);

        $service = app(DocumentMergeService::class);
        $this->assertStringContainsString(
            'Top Match Investments',
            $service->merge('<p>{{buyer.top_match}}</p>', $deal),
        );

        $dealWithoutMatch = $this->createDeal();
        $this->assertStringContainsString(
            'BOUNCE BACK REALTY and/or its assigns',
            $service->merge('<p>{{buyer.top_match}}</p>', $dealWithoutMatch),
        );
    }

    public function test_investor_packet_uses_saved_comparable_sale_data_for_arv_and_comp_rows(): void
    {
        $this->actingAsAdmin();
        $lead = $this->createLead();
        $property = $this->createProperty([
            'lead_id' => $lead->id,
            'address' => '456 Comp Avenue',
            'city' => 'Atlanta',
            'state' => 'GA',
            'zip_code' => '30318',
            'property_type' => 'single_family',
            'after_repair_value' => 250000,
        ]);
        $deal = $this->createDeal(['lead_id' => $lead->id, 'contract_price' => 160000]);
        ComparableSale::create([
            'tenant_id' => $this->tenant->id,
            'property_id' => $property->id,
            'address' => '789 Comp Street',
            'sale_price' => 200000,
            'adjusted_price' => 210000,
            'sale_date' => '2026-07-01',
            'sqft' => 1500,
        ]);

        $response = $this->get(route('documents.investorPacket', $deal));

        $response->assertOk();
        $response->assertSee('Average ARV');
        $response->assertSee('$210,000.00');
        $response->assertSee('789 Comp Street');
        $response->assertSee('$200,000.00');
        $response->assertSee('1,500');
        $response->assertSee('07/01/2026');
    }
}
