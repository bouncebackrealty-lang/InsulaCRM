<?php

namespace Tests\Unit\Services;

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
            '4521 Mill Creek Road, Atlanta, GA 30318|ATL-30318-4521|' . Fmt::currency(185000) . '|' . Fmt::currency(1850),
            $rendered
        );
    }
}
