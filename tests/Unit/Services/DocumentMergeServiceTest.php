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
}
