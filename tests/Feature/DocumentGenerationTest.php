<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\ComparableSale;
use App\Models\Contractor;
use App\Models\DealBuyerMatch;
use App\Models\DealContractor;
use App\Models\DealLender;
use App\Models\DocumentTemplate;
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

    public function test_buyer_fields_use_the_manually_selected_buyer_instead_of_the_top_match(): void
    {
        $this->actingAsAdmin();
        $deal = $this->createDeal();
        $topMatch = Buyer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company' => 'Top Match Investments',
        ]);
        $selectedBuyer = Buyer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company' => 'Client Selected Homes LLC',
        ]);
        DealBuyerMatch::create([
            'deal_id' => $deal->id,
            'buyer_id' => $topMatch->id,
            'match_score' => 99,
        ]);
        DealBuyerMatch::create([
            'deal_id' => $deal->id,
            'buyer_id' => $selectedBuyer->id,
            'match_score' => 90,
        ]);
        $deal->update(['selected_buyer_id' => $selectedBuyer->id]);

        $service = app(DocumentMergeService::class);
        $this->assertStringContainsString(
            'Client Selected Homes LLC',
            $service->merge('<p>{{buyer.top_match}}</p>', $deal),
        );
        $this->assertStringNotContainsString(
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
        $closingAttorneyMigration = require database_path('migrations/2026_08_10_000005_align_letter_of_intent_closing_attorney.php');
        $closingAttorneyMigration->up();
        $restoreLayoutMigration = require database_path('migrations/2026_08_11_000007_restore_document_layouts.php');
        $restoreLayoutMigration->up();
        $content = $template->fresh()->content;

        $this->assertStringContainsString('{{input.closing_attorney}}', $content);
        $this->assertStringContainsString('{{company.name}}', $content);
        $this->assertStringContainsString('{{input.printed_name}}', $content);
        $this->assertStringContainsString('{{input.document_date}}', $content);
        $this->assertStringNotContainsString('data-document-entry-fields', $content);
        $this->assertStringNotContainsString('{{buyer.top_match}}', $content);
        $this->assertStringContainsString('min-height: 18px; line-height: 18px;', $content);
        $this->assertStringNotContainsString('height: 1px;', $content);
        $this->assertStringContainsString('<p><strong>Closing Attorney / Title Company:</strong> <span class="inline-line" style="width: 50%;">{{input.closing_attorney}}</span></p>', $content);
        $this->assertStringNotContainsString('class="document-field"', $content);

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

    public function test_seller_disclosure_repair_moves_fields_into_the_buyer_signature_block(): void
    {
        $this->actingAsAdmin();

        $template = DocumentTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => '1A-03-Seller Disclosure',
            'type' => 'other',
            'input_fields' => [
                ['key' => 'printed_name', 'label' => 'Printed Name (Bounce Back Realty)', 'type' => 'text'],
                ['key' => 'document_date', 'label' => 'Document Date', 'type' => 'date'],
            ],
            'content' => <<<'HTML'
<html><head><style>.sig-line { flex: 1; border-bottom: 1px solid #000000; height: 1px; margin-bottom: 2px; } .footer { margin-top: 40px; } .footer .divider-line { margin: 15px 0 25px 0; } .footer .footer-text { margin: 10px 0; }</style></head><body>
<h3>SELLER DISCLOSURE ACKNOWLEDGMENT</h3>
<table class="signature-table"><tr><td class="signature-cell"><p><strong>SELLER:</strong></p>
<div class="signature-field"><span class="signature-label">Signature:</span><span class="sig-line"></span></div>
<div class="signature-field"><span class="signature-label">Printed Name:</span><span class="sig-line"></span></div>
<div class="signature-field"><span class="signature-label">Date:</span><span class="sig-line"></span></div>
</td><td class="signature-space"></td><td class="signature-cell"><p><strong>BUYER:</strong> BOUNCE BACK REALTY</p>
<div class="signature-field"><span class="signature-label">Signature:</span><span class="sig-line"></span></div>
<div class="signature-field"><span class="signature-label">Printed Name:</span><span class="sig-line"></span></div>
<div class="signature-field"><span class="signature-label">Date:</span><span class="sig-line"></span></div>
</td></tr></table>
<div data-document-entry-fields="true"><p><strong>DOCUMENT DETAILS</strong></p>
<p><strong>Printed Name:</strong> {{input.printed_name}}</p>
<p><strong>Date:</strong> {{input.document_date}}</p></div>
</body></html>
HTML,
        ]);

        $migration = require database_path('migrations/2026_08_10_000004_fix_seller_disclosure_template.php');
        $migration->up();
        $restoreLayoutMigration = require database_path('migrations/2026_08_11_000007_restore_document_layouts.php');
        $restoreLayoutMigration->up();
        $footerMigration = require database_path('migrations/2026_08_11_000008_fit_seller_disclosure_footer.php');
        $footerMigration->up();
        $printFooterMigration = require database_path('migrations/2026_08_11_000009_override_seller_disclosure_print_footer.php');
        $printFooterMigration->up();
        $content = $template->fresh()->content;

        $this->assertStringContainsString('{{input.printed_name}}', $content);
        $this->assertStringContainsString('{{input.document_date}}', $content);
        $this->assertStringNotContainsString('data-document-entry-fields', $content);
        $this->assertStringContainsString('height: 1px;', $content);
        $this->assertStringNotContainsString('min-height: 18px; line-height: 18px;', $content);
        $this->assertStringContainsString('margin-top: 0; page-break-inside: avoid; break-inside: avoid;', $content);
        $this->assertStringContainsString('margin: 5px 0 8px 0;', $content);
        $this->assertStringContainsString('margin: 4px 0;', $content);
        $this->assertStringContainsString('seller-disclosure-print-footer-fix', $content);

        $buyerCellStart = strpos($content, '<strong>BUYER:');
        $buyerCellEnd = strpos($content, '</td>', $buyerCellStart);
        $buyerCell = substr($content, $buyerCellStart, $buyerCellEnd - $buyerCellStart);
        $sellerContent = substr($content, 0, $buyerCellStart);

        $this->assertStringContainsString('{{input.printed_name}}', $buyerCell);
        $this->assertStringContainsString('{{input.document_date}}', $buyerCell);
        $this->assertStringNotContainsString('{{input.printed_name}}', $sellerContent);
        $this->assertStringNotContainsString('{{input.document_date}}', $sellerContent);

        $rendered = app(DocumentMergeService::class)->merge(
            $content,
            $this->createDeal(),
            null,
            ['printed_name' => 'Latanya White', 'document_date' => '2026-08-09'],
        );

        $this->assertStringContainsString('Latanya White', $rendered);
        $this->assertStringContainsString('08/09/2026', $rendered);
        $this->assertStringNotContainsString('data-document-entry-fields', $rendered);
    }

    public function test_proof_of_funds_repair_checks_selected_source_and_moves_date_into_signature_block(): void
    {
        $this->actingAsAdmin();

        $template = DocumentTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => '1A-04-Proof of Funds',
            'type' => 'other',
            'input_fields' => [
                ['key' => 'source_of_funds', 'label' => 'Source of Funds', 'type' => 'radio'],
                ['key' => 'printed_name', 'label' => 'Printed Name', 'type' => 'text'],
                ['key' => 'document_date', 'label' => 'Document Date', 'type' => 'date'],
            ],
            'content' => <<<'HTML'
<html><head><style>.sig-line { flex: 1; border-bottom: 1px solid #000000; height: 1px; margin-bottom: 2px; }</style></head><body>
<table><tr><td><strong>Source of Funds</strong></td><td>
☐ Cash on Hand<br>
☐ Hard Money / Private Lender<br>
☐ Investor Partner<br>
☐ Combination
</td></tr></table>
<table class="signature-table"><tr><td class="signature-cell">
<p><strong>BOUNCE BACK REALTY</strong></p>
<div class="signature-field"><span class="signature-label">Printed Name:</span><span style="font-size: 13px; margin-left: 5px;">LaTanya White</span></div>
<div class="signature-field"><span class="signature-label">Date:</span><span class="sig-line"></span></div>
</td></tr></table>
<div data-document-entry-fields="true"><p><strong>DOCUMENT DETAILS</strong></p>
<p><strong>Source of Funds:</strong> {{input.source_of_funds}}</p>
<p><strong>Printed Name:</strong> {{input.printed_name}}</p>
<p><strong>Date:</strong> {{input.document_date}}</p></div>
</body></html>
HTML,
        ]);

        $migration = require database_path('migrations/2026_08_10_000006_fix_proof_of_funds_template.php');
        $migration->up();
        $content = $template->fresh()->content;

        $this->assertStringContainsString('{{input.source_of_funds_hard_money}}', $content);
        $this->assertStringContainsString('{{input.printed_name}}', $content);
        $this->assertStringContainsString('{{input.document_date}}', $content);
        $this->assertStringNotContainsString('LaTanya White', $content);
        $this->assertStringNotContainsString('data-document-entry-fields', $content);
        $this->assertStringContainsString('min-height: 18px; line-height: 18px;', $content);
        $this->assertStringNotContainsString('height: 1px;', $content);

        $rendered = app(DocumentMergeService::class)->merge(
            $content,
            $this->createDeal(),
            null,
            [
                'source_of_funds' => 'Hard Money / Private Lender',
                'printed_name' => 'Latanya White',
                'document_date' => '2026-08-09',
            ],
        );

        $this->assertStringContainsString('☑ Hard Money / Private Lender', $rendered);
        $this->assertStringContainsString('☐ Cash on Hand', $rendered);
        $this->assertStringContainsString('Latanya White', $rendered);
        $this->assertStringContainsString('08/09/2026', $rendered);
        $this->assertStringNotContainsString('2026-08-09', $rendered);
        $this->assertStringNotContainsString('data-document-entry-fields', $rendered);
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
        $deal->update([
            'title_company_id' => $titleCompany->id,
            'selected_buyer_id' => $buyer->id,
        ]);
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

    public function test_remaining_templates_move_document_inputs_into_body_fields_and_remove_details(): void
    {
        $this->actingAsAdmin();

        $templates = [];
        foreach ([
            ['name' => '2D-01-Assignment Contract', 'content' => '<table><tr><td>Assignee Address</td><td><span class="inline-line"></span></td></tr><tr><td>Assignment Fee</td><td>$<span class="inline-line"></span></td></tr><tr><td>Title Company / Closing Attorney</td><td><span class="inline-line"></span></td></tr></table><table class="signature-table"><tr><td class="signature-cell"><p>ASSIGNOR:</p><span class="signature-label">Printed Name:</span><span class="sig-line"></span><span class="signature-label">Date:</span><span class="sig-line"></span></td></tr></table><div data-document-entry-fields="true">{{input.printed_name}}{{input.document_date}}</div>'],
            ['name' => '3R-01-Independent Contractor Agreement', 'content' => 'Federal Employer Identification Number or Social Security Number is: <span class="inline-line"></span><table class="signature-table"><tr><td class="signature-cell"><p>COMPANY:</p><span class="signature-label">Printed Name:</span><span class="sig-line"></span><span class="signature-label">Date:</span><span class="sig-line"></span></td></tr></table><div data-document-entry-fields="true">{{input.contractor_ein_ssn}}{{input.printed_name}}{{input.document_date}}</div>'],
            ['name' => '3R-03-Scope of Work', 'content' => '<table><tr><td>Completion Deadline</td><td><span class="inline-line"></span></td></tr></table><table class="signature-table"><tr><td class="signature-cell"><p>OWNER:</p><span class="signature-label">Printed Name:</span><span class="sig-line"></span><span class="signature-label">Date:</span><span class="sig-line"></span></td></tr></table><div data-document-entry-fields="true">{{input.completion_deadline}}{{input.printed_name}}{{input.document_date}}</div>'],
            ['name' => '4C-01-Lien Waiver', 'content' => '<table><tr><td>Final Payment Amount</td><td><span class="inline-line"></span></td></tr><tr><td>Total Paid to Date</td><td><span class="inline-line"></span></td></tr></table><table class="signature-table"><tr><td class="signature-cell"><p>OWNER ACKNOWLEDGMENT:</p><span class="stacked-label">Printed Name:</span><span class="stacked-line"></span><span class="stacked-label">Date:</span><span class="stacked-line"></span></td></tr></table><div data-document-entry-fields="true">{{input.final_payment_amount}}{{input.total_paid_to_date}}{{input.printed_name}}{{input.document_date}}</div>'],
            ['name' => '1A-05-Earnest Money Deposit Release Form', 'content' => '<table class="signature-table"><tr><td class="signature-cell"><p>BUYER:</p><span class="signature-label">Printed Name:</span><span class="sig-line"></span><span class="signature-label">Date:</span><span class="sig-line"></span></td></tr></table><div data-document-entry-fields="true">{{input.printed_name}}{{input.document_date}}</div>'],
            ['name' => '4C-02-Partial Lien Waiver', 'content' => '<table><tr><td>Payment Amount Received</td><td>$<span class="inline-line"></span> (Payment #<span class="inline-line"></span>)</td></tr></table><table class="signature-table"><tr><td class="signature-cell"><p>CONTRACTOR:</p><span class="stacked-label">Printed Name:</span><span class="stacked-line"></span><span class="stacked-label">Date:</span><span class="stacked-line"></span></td></tr></table><div data-document-entry-fields="true">{{input.payment_amount_received}}{{input.payment_number}}{{input.printed_name}}{{input.document_date}}</div>'],
        ] as $definition) {
            $templates[] = DocumentTemplate::create([
                'tenant_id' => $this->tenant->id,
                'name' => $definition['name'],
                'type' => 'other',
                'input_fields' => [],
                'content' => $definition['content'],
            ]);
        }

        $migration = require database_path('migrations/2026_08_12_000010_fix_remaining_document_input_fields.php');
        $migration->up();
        $partialLienMigration = require database_path('migrations/2026_08_12_000011_fix_partial_lien_payment_number.php');
        $partialLienMigration->up();

        foreach ($templates as $template) {
            $content = $template->fresh()->content;
            $this->assertStringNotContainsString('data-document-entry-fields', $content, $template->name);
            $this->assertStringContainsString('{{input.printed_name}}', $content, $template->name);
            $this->assertStringContainsString('{{input.document_date}}', $content, $template->name);
        }

        $this->assertStringContainsString('{{buyer.full_address}}', $templates[0]->fresh()->content);
        $this->assertStringContainsString('{{deal.assignment_fee}}', $templates[0]->fresh()->content);
        $this->assertStringContainsString('{{input.contractor_ein_ssn}}', $templates[1]->fresh()->content);
        $this->assertStringContainsString('{{input.completion_deadline}}', $templates[2]->fresh()->content);
        $this->assertStringContainsString('{{input.final_payment_amount}}', $templates[3]->fresh()->content);
        $this->assertStringContainsString('{{input.payment_amount_received}}', $templates[5]->fresh()->content);
        $this->assertStringContainsString('{{input.payment_number}}', $templates[5]->fresh()->content);

        $deal = $this->createDeal(['assignment_fee' => 12500]);
        $buyer = Buyer::create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'James',
            'last_name' => 'Bond',
            'company' => 'Snap Back Homes LLC',
            'address' => '123 Buyer Lane',
            'city' => 'Atlanta',
            'state' => 'GA',
            'zip_code' => '30318',
        ]);
        DealBuyerMatch::create([
            'deal_id' => $deal->id,
            'buyer_id' => $buyer->id,
            'match_score' => 99,
        ]);
        $titleCompany = TitleCompany::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Peachtree Title',
            'closing_attorney' => 'Alex Morgan',
        ]);
        $deal->update([
            'title_company_id' => $titleCompany->id,
            'selected_buyer_id' => $buyer->id,
        ]);

        $renderedAssignment = app(DocumentMergeService::class)->merge(
            $templates[0]->fresh()->content,
            $deal,
            null,
            ['printed_name' => 'Latanya White', 'document_date' => '2026-08-10'],
        );

        $this->assertStringContainsString('123 Buyer Lane, Atlanta, GA 30318', $renderedAssignment);
        $this->assertStringContainsString('12,500.00', $renderedAssignment);
        $this->assertStringContainsString('Peachtree Title Alex Morgan', $renderedAssignment);
    }

    public function test_remaining_template_polish_aligns_signatures_dates_and_contractor_identity_fields(): void
    {
        $this->actingAsAdmin();

        $sigCss = '<style>.signature-field { margin-top: 20px; }.sig-line { flex: 1; border-bottom: 1px solid #000; height: 1px; margin-bottom: 2px; }</style>';
        $signatureCell = static fn (string $party): string => '<td class="signature-cell"><p>'.$party.':</p>'
            .'<span class="signature-label">Printed Name:</span><span class="sig-line"></span>'
            .'<span class="signature-label">Date:</span><span class="sig-line"></span></td>';
        $dateRow = '<table><tr><td><strong>Date</strong></td><td><span class="inline-line">{{today}}</span></td></tr></table>';

        $templates = collect([
            '1A-03-Seller Disclosure' => $sigCss.$dateRow.'<table><tr>'.$signatureCell('BUYER').'</tr></table><div data-document-entry-fields="true">legacy</div>',
            '1A-05-Earnest Money Deposit Release Form' => $sigCss.$dateRow.'<table><tr>'.$signatureCell('BUYER').$signatureCell('SELLER').'</tr></table><div data-document-entry-fields="true">legacy</div>',
            '2D-01-Assignment Contract' => $sigCss.'<p>as of <span class="inline-line">{{today_month_day}}</span>, <span class="inline-line">{{today_year}}</span>, by and between</p><table><tr>'.$signatureCell('ASSIGNEE (CASH BUYER)').$signatureCell('ASSIGNOR').'</tr></table>',
            '3R-01-Independent Contractor Agreement' => $sigCss.'<p>as of <span class="inline-line">{{today_month_day}}</span>, <span class="inline-line">{{today_year}}</span></p><table>'
                .'<tr><td><strong>Contractor Name / Business Name</strong></td><td><span class="inline-line">{{contractor.name}}</span></td></tr>'
                .'<tr><td><strong>Business Entity Type</strong></td><td><span class="inline-line">{{contractor.trade}}</span></td></tr>'
                .'<tr><td><strong>Principal Office / Mailing Address</strong></td><td><span class="inline-line">{{contractor.service_area}}</span></td></tr>'
                .'</table><p>Phone: <span class="inline-line" style="width: 35%;">{{contractor.phone}}</span> Email: <span class="inline-line" style="width: 35%;">{{contractor.email}}</span></p>',
            '3R-02-Contractor Bid Package' => '<p><strong>Contractor Company Name:</strong> {{contractor.name}}</p><p><strong>Contact Name:</strong> {{contractor.name}}</p><p><strong>License Number:</strong> __________________</p>',
            '3R-03-Scope of Work' => $sigCss.'<table>'
                .'<tr><td><strong>Agreement Date</strong></td><td><span class="inline-line">{{today}}</span></td></tr>'
                .'<tr><td><strong>Completion Deadline</strong></td><td><span class="inline-line">{{input.completion_deadline}}</span></td></tr>'
                .'</table><p><strong>Project Completion Deadline:</strong> <span class="inline-line"></span>, 20<span class="inline-line"></span></p>',
            '3R-04-Change Order Form' => '<table><tr><td><strong>New Completion Date</strong></td><td><input placeholder="MM/DD/YYYY" value="{{input.document_date}}"></td></tr></table>',
            '4C-01-Lien Waiver' => $dateRow,
            '4C-02-Partial Lien Waiver' => '<p>Date of Payment: {{today}}</p><p>Work completed through {{today}}</p>',
        ])->map(function (string $content, string $name): DocumentTemplate {
            return DocumentTemplate::create([
                'tenant_id' => $this->tenant->id,
                'name' => $name,
                'type' => 'other',
                'input_fields' => [],
                'content' => $content,
            ]);
        });

        $migration = require database_path('migrations/2026_08_12_000014_polish_remaining_document_templates.php');
        $migration->up();

        $seller = $templates['1A-03-Seller Disclosure']->fresh()->content;
        $this->assertStringContainsString('min-height: 18px; line-height: 18px;', $seller);
        $this->assertStringNotContainsString('height: 1px;', $seller);
        $this->assertStringContainsString('margin-top: 8px;', $seller);
        $this->assertStringContainsString('{{input.document_date}}', $seller);
        $this->assertStringNotContainsString('data-document-entry-fields', $seller);

        $emd = $templates['1A-05-Earnest Money Deposit Release Form']->fresh()->content;
        $this->assertStringContainsString('{{lead.full_name}}', $emd);
        $this->assertSame(2, substr_count($emd, '{{input.document_date}}'));

        $assignment = $templates['2D-01-Assignment Contract']->fresh()->content;
        $this->assertStringContainsString('{{input.document_date_long}}', $assignment);
        $this->assertStringContainsString('{{buyer.full_name}}', $assignment);

        $contractorAgreement = $templates['3R-01-Independent Contractor Agreement']->fresh()->content;
        $this->assertStringContainsString('Contractor Name</strong>', $contractorAgreement);
        $this->assertStringContainsString('Business Name / Entity', $contractorAgreement);
        $this->assertStringContainsString('{{contractor.business_name}}', $contractorAgreement);
        $this->assertStringContainsString('{{contractor.mailing_address}}', $contractorAgreement);
        $this->assertStringContainsString('width: 44%;', $contractorAgreement);

        $bidPackage = $templates['3R-02-Contractor Bid Package']->fresh()->content;
        $this->assertStringContainsString('<strong>Business Name:</strong> {{contractor.business_name}}', $bidPackage);
        $this->assertStringContainsString('<strong>Contact Name:</strong> {{contractor.name}}', $bidPackage);
        $this->assertStringContainsString('{{contractor.license_number}}', $bidPackage);

        $scope = $templates['3R-03-Scope of Work']->fresh()->content;
        $this->assertStringContainsString('{{input.document_date}}', $scope);
        $this->assertSame(2, substr_count($scope, '{{input.completion_deadline}}'));
        $this->assertStringNotContainsString(', 20<span', $scope);

        $changeOrder = $templates['3R-04-Change Order Form']->fresh()->content;
        $this->assertStringNotContainsString('value="{{input.document_date}}"', $changeOrder);

        $this->assertStringContainsString('{{input.document_date}}', $templates['4C-01-Lien Waiver']->fresh()->content);
        $this->assertSame(2, substr_count($templates['4C-02-Partial Lien Waiver']->fresh()->content, '{{input.document_date}}'));
    }

    public function test_change_order_property_address_uses_a_readable_full_width_row(): void
    {
        $this->actingAsAdmin();

        $template = DocumentTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => '3R-04-Change Order Form',
            'type' => 'other',
            'input_fields' => [],
            'content' => '<table><tr>'
                .'<td><strong>Date</strong></td><td><input value="{{input.document_date}}"></td>'
                .'<td><strong>Property Address</strong></td>'
                .'<td><input type="text" style="width: 95%; border: none; font-size: 1em;" value="{{property.full_address}}"></td>'
                .'</tr></table><p style="text-align:center;">Every Move Starts with Strategy</p>',
        ]);

        $migration = require database_path('migrations/2026_08_12_000015_fit_change_order_property_address.php');
        $migration->up();
        $repairMigration = require database_path('migrations/2026_08_13_000017_repair_change_order_and_taglines.php');
        $repairMigration->up();

        $content = $template->fresh()->content;

        $this->assertStringContainsString('width: 100%;', $content);
        $this->assertStringContainsString('font-size: 1em;', $content);
        $this->assertStringNotContainsString('font-size: 0.62em;', $content);
        $this->assertStringContainsString('box-sizing: border-box;', $content);
        $this->assertSame(2, substr_count($content, 'colspan="3"'));
        $this->assertStringContainsString('{{property.full_address}}', $content);
        $this->assertStringContainsString('class="document-tagline"', $content);
    }

    public function test_independent_contractor_phone_and_email_stay_on_one_line(): void
    {
        $this->actingAsAdmin();

        $template = DocumentTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => '3R-01-Independent Contractor Agreement',
            'type' => 'other',
            'input_fields' => [],
            'content' => '<table><tr>'
                .'<td class="info-label-cell"><strong>Phone & Email</strong></td>'
                .'<td>Phone: <span class="inline-line" style="width: 26%;">{{contractor.phone}}</span> '
                .'Email: <span class="inline-line" style="width: 44%;">{{contractor.email}}</span></td>'
                .'</tr></table>',
        ]);

        $migration = require database_path('migrations/2026_08_13_000018_keep_contractor_phone_and_email_on_one_line.php');
        $migration->up();

        $content = $template->fresh()->content;

        $this->assertStringContainsString('<td style="white-space: nowrap;">', $content);
        $this->assertStringContainsString('width: 24%; white-space: nowrap;', $content);
        $this->assertStringContainsString('width: 48%; white-space: nowrap;', $content);
        $this->assertSame(1, substr_count($content, '{{contractor.phone}}'));
        $this->assertSame(1, substr_count($content, '{{contractor.email}}'));
    }

    public function test_change_order_preview_values_are_saved_in_the_generated_document(): void
    {
        $this->actingAsAdmin();
        $deal = $this->createDeal();
        $template = DocumentTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Change Order persistence test',
            'type' => 'other',
            'content' => '<input type="text" placeholder="Change Order #">'
                .'<label><input type="checkbox"> Owner Request</label>'
                .'<textarea placeholder="Work added"></textarea>',
        ]);

        $response = $this->post(route('documents.store', $deal), [
            'template_id' => $template->id,
            'preview_controls' => json_encode([
                ['index' => 0, 'tag' => 'input', 'type' => 'text', 'value' => 'CO-2026-17', 'checked' => false],
                ['index' => 1, 'tag' => 'input', 'type' => 'checkbox', 'value' => 'on', 'checked' => true],
                ['index' => 2, 'tag' => 'textarea', 'type' => 'textarea', 'value' => 'Add two kitchen cabinets', 'checked' => false],
            ], JSON_THROW_ON_ERROR),
        ]);

        $document = GeneratedDocument::latest('id')->firstOrFail();
        $response->assertRedirect(route('documents.show', $document));
        $this->assertStringContainsString('value="CO-2026-17"', $document->content);
        $this->assertMatchesRegularExpression('/type="checkbox"[^>]*checked="checked"/i', $document->content);
        $this->assertStringContainsString('Add two kitchen cabinets', $document->content);
    }

    public function test_buyer_specific_document_requires_an_explicit_deal_buyer(): void
    {
        $this->actingAsAdmin();
        $deal = $this->createDeal();
        $template = DocumentTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Buyer-specific assignment',
            'type' => 'assignment_contract',
            'merge_fields' => ['buyer.full_name'],
            'content' => '<p>{{buyer.full_name}}</p>',
        ]);

        $this->post(route('documents.store', $deal), ['template_id' => $template->id])
            ->assertSessionHasErrors('template_id');

        $buyer = Buyer::factory()->create(['tenant_id' => $this->tenant->id]);
        $deal->update(['selected_buyer_id' => $buyer->id]);

        $this->post(route('documents.store', $deal), ['template_id' => $template->id])
            ->assertRedirect();

        $this->assertStringContainsString($buyer->full_name, GeneratedDocument::latest('id')->value('content'));
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

    public function test_letter_of_intent_uses_the_selected_buyer_for_its_buyer_section(): void
    {
        $this->actingAsAdmin();
        $deal = $this->createDeal();
        $buyer = Buyer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Priya',
            'last_name' => 'Testmore',
            'company' => null,
        ]);
        $deal->update(['selected_buyer_id' => $buyer->id]);

        $template = DocumentTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => '1A-01-Letter of Intent',
            'type' => 'loi',
            'content' => '<h3>SECTION 3 — BUYER</h3><p><strong>Buyer:</strong> {{company.name}}</p>',
        ]);

        $migration = require database_path('migrations/2026_08_20_000001_use_selected_buyer_in_letter_of_intent.php');
        $migration->up();

        $template->refresh();
        $this->assertStringContainsString('{{buyer.top_match}}', $template->content);
        $this->assertContains('buyer.top_match', $template->merge_fields);

        $this->post(route('documents.store', $deal), ['template_id' => $template->id])
            ->assertRedirect();

        $content = GeneratedDocument::latest('id')->value('content');
        $this->assertStringContainsString('Priya Testmore', $content);
        $this->assertStringNotContainsString($this->tenant->name, $content);
    }
}
