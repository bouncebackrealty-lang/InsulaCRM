<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Services\HeadlessPdfService;
use Mockery\MockInterface;
use Tests\TestCase;

class DocumentPdfDownloadTest extends TestCase
{
    public function test_authorized_user_can_download_a_fixed_letter_pdf_with_a_clean_filename(): void
    {
        $this->actingAsAdmin();
        $document = $this->createGeneratedDocument('Purchase Agreement - 123 Main St');
        $this->mock(HeadlessPdfService::class, function (MockInterface $mock) {
            $mock->shouldReceive('render')
                ->once()
                ->with(\Mockery::on(fn (string $html) => str_contains($html, 'PDF Download Test') && ! str_contains($html, 'Print / Save PDF')))
                ->andReturn('%PDF-1.7 test pdf');
        });

        $response = $this->get(route('documents.downloadPdf', $document));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader('content-disposition', 'attachment; filename=purchase-agreement-123-main-st.pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_generated_document_page_shows_a_direct_pdf_download_button(): void
    {
        $this->actingAsAdmin();
        $document = $this->createGeneratedDocument('Letter of Intent');

        $response = $this->get(route('documents.show', $document));

        $response->assertOk();
        $response->assertSee('Download PDF');
        $response->assertSee(route('documents.downloadPdf', $document));
    }

    public function test_agent_cannot_download_another_agents_document(): void
    {
        $this->createTenantWithAdmin();
        $owner = $this->createUserWithRole('agent');
        $otherAgent = $this->createUserWithRole('agent');
        $deal = $this->createDeal(['agent_id' => $owner->id]);
        $document = $this->createGeneratedDocument('Private Document', $deal);

        $response = $this->actingAs($otherAgent)->get(route('documents.downloadPdf', $document));

        $response->assertForbidden();
    }

    public function test_tenant_cannot_download_another_tenants_document(): void
    {
        $this->actingAsAdmin();
        $firstTenantAdmin = $this->adminUser;

        $this->createTenantWithAdmin([
            'slug' => 'other-company',
            'email' => 'other@example.com',
        ]);
        $otherDeal = $this->createDeal();
        $document = $this->createGeneratedDocument('Other Tenant Document', $otherDeal);

        $response = $this->actingAs($firstTenantAdmin)->get(route('documents.downloadPdf', $document));

        $response->assertNotFound();
    }

    private function createGeneratedDocument(string $name, ?Deal $deal = null): GeneratedDocument
    {
        $deal ??= $this->createDeal();
        $template = DocumentTemplate::create([
            'tenant_id' => $deal->tenant_id,
            'name' => 'PDF Test Template',
            'type' => 'other',
            'content' => '<h1>PDF Download Test</h1><p>Fixed US Letter document content.</p><table><tr><th>Field</th><th>Value</th></tr><tr><td>Status</td><td>Ready</td></tr></table>',
        ]);

        return GeneratedDocument::create([
            'tenant_id' => $deal->tenant_id,
            'deal_id' => $deal->id,
            'template_id' => $template->id,
            'user_id' => $deal->agent_id,
            'name' => $name,
            'content' => $template->content,
        ]);
    }
}
