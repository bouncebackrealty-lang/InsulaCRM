<?php

namespace Tests\Feature;

use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use Tests\TestCase;

class DocumentTemplateBackfillTest extends TestCase
{
    public function test_standard_templates_are_backfilled_for_an_existing_tenant_without_templates(): void
    {
        $this->actingAsAdmin();
        $deal = $this->createDeal();

        $migration = require database_path('migrations/2026_08_20_000002_backfill_standard_document_templates.php');
        $migration->up();

        $templates = DocumentTemplate::orderBy('name')->get();

        $this->assertCount(12, $templates);
        $this->assertTrue($templates->pluck('name')->contains('1A-01-Letter of Intent'));
        $this->assertTrue($templates->pluck('name')->contains('3R-04-Change Order Form'));
        $this->assertTrue($templates->pluck('name')->contains('4C-02-Partial Lien Waiver'));
        $this->assertSame('loi', $templates->firstWhere('name', '1A-01-Letter of Intent')->type);

        $this->get(route('documents.generate', $deal))
            ->assertOk()
            ->assertSee('1A-01-Letter of Intent')
            ->assertSee('3R-04-Change Order Form');

        $proofOfFunds = $templates->firstWhere('name', '1A-04-Proof of Funds');
        $response = $this->post(route('documents.store', $deal), [
            'template_id' => $proofOfFunds->id,
            'document_inputs' => [
                'document_date' => '2026-08-20',
                'printed_name' => 'Test Company',
                'source_of_funds' => 'Cash on Hand',
            ],
        ]);

        $document = GeneratedDocument::latest('id')->firstOrFail();
        $response->assertRedirect(route('documents.show', $document));
        $this->assertSame($proofOfFunds->id, $document->template_id);
        $this->assertStringContainsString('PROOF OF FUNDS', $document->content);
        $this->assertStringContainsString('Cash on Hand', $document->content);

        $migration->up();
        $this->assertSame(12, DocumentTemplate::count());
    }
}
