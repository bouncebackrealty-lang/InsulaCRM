<?php

namespace Tests\Feature;

use App\Models\ComparableSale;
use App\Models\Contractor;
use App\Models\Buyer;
use App\Models\DealBuyerMatch;
use App\Models\DealContractor;
use App\Models\DocumentTemplate;
use App\Models\DealLender;
use App\Models\GeneratedDocument;
use App\Models\Lender;
use App\Models\LenderLoanProgram;
use App\Models\TitleCompany;
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
            ->assertSee('When one contractor is attached to the deal, it is used automatically.')
            ->assertDontSee("input.addEventListener('input'");

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

    public function test_document_date_input_merges_as_month_day_year(): void
    {
        $this->actingAsAdmin();
        $deal = $this->createDeal();

        $rendered = app(DocumentMergeService::class)->merge(
            '<p>Top date: {{input.document_date}}</p><p>Signature date: {{input.document_date}}</p>',
            $deal,
            null,
            ['document_date' => '2026-08-10'],
        );

        $this->assertSame(2, substr_count($rendered, '08/10/2026'));
        $this->assertStringNotContainsString('2026-08-10', $rendered);
    }

    public function test_loi_buyer_line_uses_the_company_instead_of_a_matched_buyer(): void
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

        $this->assertStringContainsString(
            $this->tenant->name,
            $service->merge('<p>{{company.name}}</p>', $deal),
        );

        $this->assertStringNotContainsString(
            'Top Match Investments',
            $service->merge('<p>{{company.name}}</p>', $deal),
        );
    }

    public function test_letter_of_intent_repair_moves_fields_into_the_body_and_removes_details_footer(): void
    {
        $this->actingAsAdmin();

        $template = DocumentTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => '1A-01-Letter of Intent',
            'type' => 'loi',
            'input_fields' => [
                ['key' => 'printed_name', 'label' => 'Printed Name', 'type' => 'text'],
                ['key' => 'document_date', 'label' => 'Document Date', 'type' => 'date'],
                ['key' => 'closing_attorney', 'label' => 'Closing Attorney / Title Company', 'type' => 'text'],
            ],
            'content' => <<<'HTML'
<html><head><style>.sig-line { flex: 1; border-bottom: 1px solid #000000; height: 1px; margin-bottom: 2px; }</style></head><body>
<p><strong>Closing Attorney / Title Company:</strong> <span class="inline-line"></span></p>
<p><strong>Buyer:</strong> {{buyer.top_match}}</p>
<table><tr><td class="signature-cell"><p><strong>SELLER(S):</strong></p>
<div><span class="signature-label">Printed Name:</span><span class="sig-line"></span></div>
<div><span class="signature-label">Date:</span><span class="sig-line"></span></div>
</td><td class="signature-space"></td><td class="signature-cell"><p><strong>BUYER:</strong> BOUNCE BACK REALTY</p>
<div><span class="signature-label">Printed Name:</span><span class="sig-line"></span></div>
<div><span class="signature-label">Date:</span><span class="sig-line"></span></div>
</td></tr></table>
<div data-document-entry-fields="true"><p><strong>DOCUMENT DETAILS</strong></p></div>
</body></html>
HTML,
        ]);

        $migration = require database_path('migrations/2026_08_10_000000_fix_letter_of_intent_template.php');
        $migration->up();
        $signatureMigration = require database_path('migrations/2026_08_10_000001_correct_letter_of_intent_signature_side.php');
        $signatureMigration->up();
        $layoutMigration = require database_path('migrations/2026_08_10_000002_align_letter_of_intent_signature_values.php');
        $layoutMigration->up();
        $content = $template->fresh()->content;

        $this->assertStringContainsString('{{input.closing_attorney}}', $content);
        $this->assertStringContainsString('{{company.name}}', $content);
        $this->assertStringContainsString('{{input.printed_name}}', $content);
        $this->assertStringContainsString('{{input.document_date}}', $content);
        $this->assertStringNotContainsString('data-document-entry-fields', $content);
        $this->assertStringNotContainsString('{{buyer.top_match}}', $content);
        $this->assertStringContainsString('min-height: 18px; line-height: 18px;', $content);
        $this->assertStringNotContainsString('height: 1px;', $content);

        $buyerCellStart = strpos($content, '<strong>BUYER:');
        $buyerCellEnd = strpos($content, '</td>', $buyerCellStart);
        $buyerCell = substr($content, $buyerCellStart, $buyerCellEnd - $buyerCellStart);
        $sellerContent = substr($content, 0, $buyerCellStart);

        $this->assertStringContainsString('{{input.printed_name}}', $buyerCell);
        $this->assertStringContainsString('{{input.document_date}}', $buyerCell);
        $this->assertStringNotContainsString('{{input.printed_name}}', $sellerContent);
        $this->assertStringNotContainsString('{{input.document_date}}', $sellerContent);

    }

    public function test_purchase_agreement_repair_moves_title_company_and_signature_fields_into_the_body(): void
    {
        $this->actingAsAdmin();

        $template = DocumentTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => '1A-02-Purchase Agreement',
            'type' => 'purchase_agreement',
            'input_fields' => [],
            'content' => <<<'HTML'
<html><head><style>.sig-line { flex: 1; border-bottom: 1px solid #000000; height: 1px; margin-bottom: 2px; }</style></head><body>
<p><strong>Title Company:</strong> <span class="inline-line"></span></p>
<p><strong>Title Company Address:</strong> <span class="inline-line"></span></p>
<table class="signature-table"><tr><td class="signature-cell"><p><strong>SELLER(S):</strong></p>
<div class="signature-field"><span class="signature-label">Printed Name:</span><span class="sig-line"></span></div>
<div class="signature-field"><span class="signature-label">Date:</span><span class="sig-line"></span></div>
</td><td class="signature-space"></td><td class="signature-cell"><p><strong>BUYER:</strong> BOUNCE BACK REALTY</p>
<div class="signature-field"><span class="signature-label">Printed Name:</span><span class="sig-line"></span></div>
<div class="signature-field"><span class="signature-label">Date:</span><span class="sig-line"></span></div>
</td></tr></table>
<div data-document-entry-fields="true"><p><strong>DOCUMENT DETAILS</strong></p>
<p><strong>Title Company:</strong> {{title_company.name}}</p>
<p><strong>Title Company Address:</strong> {{title_company.full_address}}</p>
<p><strong>Printed Name:</strong> {{input.printed_name}}</p>
<p><strong>Date:</strong> {{input.document_date}}</p></div>
</body></html>
HTML,
        ]);

        $migration = require database_path('migrations/2026_08_10_000003_fix_purchase_agreement_template.php');
        $migration->up();
        $content = $template->fresh()->content;

        $this->assertStringContainsString('{{title_company.name}}', $content);
        $this->assertStringContainsString('{{title_company.full_address}}', $content);
        $this->assertStringContainsString('{{input.printed_name}}', $content);
        $this->assertStringContainsString('{{input.document_date}}', $content);
        $this->assertStringContainsString('min-height: 18px; line-height: 18px;', $content);
        $this->assertStringNotContainsString('height: 1px;', $content);
        $this->assertStringNotContainsString('data-document-entry-fields', $content);

        $buyerCellStart = strpos($content, '<strong>BUYER:');
        $buyerCellEnd = strpos($content, '</td>', $buyerCellStart);
        $buyerCell = substr($content, $buyerCellStart, $buyerCellEnd - $buyerCellStart);
        $sellerContent = substr($content, 0, $buyerCellStart);

        $this->assertStringContainsString('{{input.printed_name}}', $buyerCell);
        $this->assertStringContainsString('{{input.document_date}}', $buyerCell);
        $this->assertStringNotContainsString('{{input.printed_name}}', $sellerContent);
        $this->assertStringNotContainsString('{{input.document_date}}', $sellerContent);

        $deal = $this->createDeal();
        $titleCompany = TitleCompany::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Peachtree Title',
            'address' => '10 Main St',
            'city' => 'Atlanta',
            'state' => 'GA',
            'zip_code' => '30303',
        ]);
        $deal->update(['title_company_id' => $titleCompany->id]);

        $rendered = app(DocumentMergeService::class)->merge(
            $content,
            $deal,
            null,
            ['printed_name' => 'Latanya White', 'document_date' => '2026-08-10'],
        );

        $this->assertStringContainsString('Peachtree Title', $rendered);
        $this->assertStringContainsString('10 Main St, Atlanta, GA 30303', $rendered);
        $this->assertStringContainsString('Latanya White', $rendered);
        $this->assertStringContainsString('08/10/2026', $rendered);
        $this->assertStringNotContainsString('2026-08-10', $rendered);
        $this->assertStringNotContainsString('data-document-entry-fields', $rendered);
    }

    public function test_purchase_agreement_does_not_use_contractor_data(): void
    {
        $this->actingAsAdmin();

        $deal = $this->createDeal();
        $contractor = Contractor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Should Not Appear In PSA',
        ]);
        DealContractor::create([
            'deal_id' => $deal->id,
            'contractor_id' => $contractor->id,
        ]);
        $template = DocumentTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => '1A-02-Purchase Agreement',
            'type' => 'purchase_agreement',
            'content' => '<p>{{contractor.name}}</p><p>{{company.name}} and/or its assigns</p>',
        ]);

        $this->post(route('documents.store', $deal), [
            'template_id' => $template->id,
            'contractor_id' => $contractor->id,
        ])->assertRedirect();

        $content = GeneratedDocument::latest('id')->value('content');
        $this->assertStringNotContainsString('Should Not Appear In PSA', $content);
        $this->assertStringContainsString('and/or its assigns', $content);
    }

    public function test_document_merges_selected_lender_title_company_buyer_address_and_entry_values(): void
    {
        $this->actingAsAdmin();
        $deal = $this->createDeal();
        $buyer = Buyer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'address' => '42 Buyer Way', 'city' => 'Atlanta', 'state' => 'GA', 'zip_code' => '30318',
        ]);
        DealBuyerMatch::create(['deal_id' => $deal->id, 'buyer_id' => $buyer->id, 'match_score' => 99]);
        $titleCompany = TitleCompany::create(['tenant_id' => $this->tenant->id, 'name' => 'Peachtree Title', 'closing_attorney' => 'Avery Stone', 'address' => '10 Main St', 'city' => 'Atlanta', 'state' => 'GA', 'zip_code' => '30303']);
        $deal->update(['title_company_id' => $titleCompany->id]);
        $lender = Lender::create(['tenant_id' => $this->tenant->id, 'name' => 'Capital Funding']);
        $program = LenderLoanProgram::create(['tenant_id' => $this->tenant->id, 'lender_id' => $lender->id, 'program_name' => 'Bridge']);
        DealLender::create(['deal_id' => $deal->id, 'lender_id' => $lender->id, 'lender_loan_program_id' => $program->id]);
        $template = DocumentTemplate::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Partner merge test', 'type' => 'other',
            'input_fields' => [['key' => 'source_of_funds'], ['key' => 'printed_name']],
            'content' => '<p>{{lender.name}} | {{title_company.closing_attorney}} | {{title_company.full_address}} | {{buyer.full_address}} | {{input.closing_attorney}} | {{input.source_of_funds}} | {{input.printed_name}}</p>',
        ]);

        $this->post(route('documents.store', $deal), [
            'template_id' => $template->id,
            'document_inputs' => ['source_of_funds' => 'Hard Money / Private Lender', 'printed_name' => 'Latanya White'],
        ])->assertRedirect();

        $content = GeneratedDocument::latest('id')->value('content');
        $this->assertStringContainsString('Capital Funding', $content);
        $this->assertStringContainsString('Avery Stone', $content);
        $this->assertStringContainsString('Avery Stone Peachtree Title', $content);
        $this->assertStringContainsString('10 Main St, Atlanta, GA 30303', $content);
        $this->assertStringContainsString('42 Buyer Way, Atlanta, GA 30318', $content);
        $this->assertStringContainsString('Hard Money / Private Lender', $content);
        $this->assertStringContainsString('Latanya White', $content);
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
