<?php

namespace Tests\Unit\Models;

use Tests\TestCase;

class PropertyTest extends TestCase
{
    public function test_full_address_attribute(): void
    {
        $this->actingAsAdmin();
        $property = $this->createProperty([
            'address' => '123 Main Street',
            'city' => 'Springfield',
            'state' => 'IL',
            'zip_code' => '62701',
        ]);

        $this->assertStringContainsString('123 Main Street', $property->full_address);
        $this->assertStringContainsString('Springfield', $property->full_address);
    }

    public function test_mao_computed_attribute(): void
    {
        $this->actingAsAdmin();
        $property = $this->createProperty([
            'after_repair_value' => 200000,
            'repair_estimate' => 30000,
        ]);

        // MAO = (ARV * 0.70) - Repair = (200000 * 0.70) - 30000 = 110000
        $this->assertEquals(110000, $property->mao);
    }

    public function test_mao_computed_attribute_uses_selected_percentage(): void
    {
        $this->actingAsAdmin();
        $property = $this->createProperty([
            'after_repair_value' => 340000,
            'repair_estimate' => 60500,
            'mao_percentage' => 72,
        ]);

        $this->assertEquals(184300, $property->mao);

        $property->update(['mao_percentage' => 75]);
        $this->assertEquals(194500, $property->fresh()->mao);
    }

    public function test_assignment_fee_computed_attribute(): void
    {
        $this->actingAsAdmin();
        $property = $this->createProperty([
            'after_repair_value' => 340000,
            'repair_estimate' => 60500,
            'our_offer' => 175000,
        ]);

        // MAO = (340000 * 0.70) - 60500 = 177500; Assignment Fee = 177500 - 175000.
        $this->assertEquals(2500, $property->assignment_fee);
    }

    public function test_assignment_fee_uses_selected_mao_percentage(): void
    {
        $this->actingAsAdmin();
        $property = $this->createProperty([
            'after_repair_value' => 340000,
            'repair_estimate' => 60500,
            'our_offer' => 175000,
            'mao_percentage' => 75,
        ]);

        $this->assertEquals(19500, $property->assignment_fee);
    }

    public function test_distress_markers_cast_to_array(): void
    {
        $this->actingAsAdmin();
        $property = $this->createProperty([
            'distress_markers' => ['tax_delinquent', 'probate'],
        ]);

        $property = $property->fresh();
        $this->assertIsArray($property->distress_markers);
        $this->assertContains('tax_delinquent', $property->distress_markers);
        $this->assertContains('probate', $property->distress_markers);
    }

    public function test_property_belongs_to_lead(): void
    {
        $this->actingAsAdmin();
        $lead = $this->createLead();
        $property = $this->createProperty(['lead_id' => $lead->id]);

        $this->assertEquals($lead->id, $property->lead->id);
    }
}
