<?php

namespace Tests\Unit\Services;

use App\Models\Contractor;
use App\Services\DocumentMergeService;
use Fmt;
use Tests\TestCase;

class DocumentMergeServiceTest extends TestCase
{
    public function test_loi_property_and_offer_merge_fields_render_from_the_current_deal(): void
    {
        $this->actingAsAdmin();
        $deal = $this->createDeal([
            'contract_price' => 185000,
            'earnest_money' => 1850,
        ]);
        $this->createProperty([
            'lead_id' => $deal->lead_id,
            'address' => '4521 Mill Creek Road',
            'city' => 'Atlanta',
            'state' => 'GA',
            'zip_code' => '30318',
            'parcel_id' => 'ATL-30318-4521',
        ]);

        $rendered = app(DocumentMergeService::class)->merge(
            '{{property.full_address}}|{{property.parcel_id}}|{{deal.contract_price}}|{{deal.earnest_money}}',
            $deal
        );

        $this->assertSame(
            '4521 Mill Creek Road, Atlanta, GA 30318|ATL-30318-4521|'.Fmt::currency(185000).'|'.Fmt::currency(1850),
            $rendered
        );
    }

    public function test_document_inputs_and_contractor_identity_fields_render_in_document_format(): void
    {
        $this->actingAsAdmin();
        $deal = $this->createDeal();
        $contractor = Contractor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'James Bond',
            'business_name' => 'Bond Drywall LLC',
            'phone' => '555-777-8888',
            'email' => 'james@example.com',
            'mailing_address' => '10 Contractor Way, Atlanta, GA 30318',
            'license_number' => 'GA-459010',
            'specialty' => ['drywall'],
            'priority' => 'medium',
            'status' => 'hired',
        ]);

        $rendered = app(DocumentMergeService::class)->merge(
            '{{input.document_date}}|{{input.document_date_long}}|{{input.completion_deadline}}|'
            .'{{input.final_payment_amount}}|{{input.total_paid_to_date}}|'
            .'{{contractor.name}}|{{contractor.business_name}}|{{contractor.mailing_address}}|{{contractor.license_number}}',
            $deal,
            $contractor,
            [
                'document_date' => '2026-08-10',
                'completion_deadline' => '2026-08-30',
                'final_payment_amount' => '4500',
                'total_paid_to_date' => '$12,000',
            ],
        );

        $this->assertSame(
            '08/10/2026|August 10, 2026|08/30/2026|4,500.00|12,000.00|'
            .'James Bond|Bond Drywall LLC|10 Contractor Way, Atlanta, GA 30318|GA-459010',
            $rendered,
        );
    }

    public function test_live_preview_control_values_are_persisted_safely_in_document_order(): void
    {
        $html = <<<'HTML'
<input type="text" placeholder="Change Order #">
<input type="text" value="0.00" placeholder="Additional Cost">
<label><input type="checkbox"> Owner Request</label>
<textarea placeholder="Work added"></textarea>
<select><option value="pending">Pending</option><option value="approved">Approved</option></select>
HTML;

        $rendered = app(DocumentMergeService::class)->applyPreviewControlValues($html, [
            ['index' => 0, 'tag' => 'input', 'type' => 'text', 'value' => 'CO-17 & "Final"', 'checked' => false],
            ['index' => 1, 'tag' => 'input', 'type' => 'text', 'value' => '$4,500.00', 'checked' => false],
            ['index' => 2, 'tag' => 'input', 'type' => 'checkbox', 'value' => 'on', 'checked' => true],
            ['index' => 3, 'tag' => 'textarea', 'type' => 'textarea', 'value' => '<script>alert(1)</script> Add cabinets', 'checked' => false],
            ['index' => 4, 'tag' => 'select', 'type' => 'select-one', 'value' => 'approved', 'checked' => false],
        ]);

        $this->assertStringContainsString('value="CO-17 &amp; &quot;Final&quot;"', $rendered);
        $this->assertStringContainsString('value="$4,500.00"', $rendered);
        $this->assertMatchesRegularExpression('/type="checkbox"[^>]*checked="checked"/i', $rendered);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; Add cabinets', $rendered);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $rendered);
        $this->assertMatchesRegularExpression('/<option value="approved" selected="selected">Approved<\/option>/i', $rendered);
    }
}
