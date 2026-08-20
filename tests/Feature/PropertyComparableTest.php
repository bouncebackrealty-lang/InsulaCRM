<?php

namespace Tests\Feature;

use App\Models\ComparableSale;
use App\Models\GeneratedDocument;
use Tests\TestCase;

class PropertyComparableTest extends TestCase
{
    public function test_admin_can_add_comps_and_get_70_72_and_75_percent_mao_summary(): void
    {
        $this->actingAsAdmin();
        $property = $this->createProperty([
            'repair_estimate' => 20000,
        ]);

        $response = $this->post("/properties/{$property->id}/comps", [
            'address' => '456 Oak St',
            'sale_price' => 200000,
            'sale_date' => '2026-06-01',
            'sqft' => 1500,
            'beds' => 3,
            'baths' => 2,
            'adjustments' => [
                'condition' => 10000,
            ],
        ]);

        $response->assertRedirect("/properties/{$property->id}");
        $this->assertDatabaseHas('comparable_sales', [
            'property_id' => $property->id,
            'address' => '456 Oak St',
            'sale_price' => 200000,
            'adjusted_price' => 210000,
        ]);

        $summary = $this->getJson("/properties/{$property->id}/arv-summary");

        $summary->assertOk();
        $summary->assertJson([
            'avg_arv' => 210000,
            'median_arv' => 210000,
            'comp_count' => 1,
            'mao_70' => 127000,
            'mao_72' => 131200,
            'mao_75' => 137500,
        ]);
    }

    public function test_property_page_renders_arv_worksheet_with_70_72_and_75_percent_presets(): void
    {
        $this->actingAsAdmin();
        $property = $this->createProperty([
            'repair_estimate' => 20000,
        ]);

        ComparableSale::create([
            'tenant_id' => $this->tenant->id,
            'property_id' => $property->id,
            'address' => '789 Pine St',
            'sale_price' => 200000,
            'adjusted_price' => 200000,
            'sale_date' => '2026-06-01',
        ]);

        $response = $this->get("/properties/{$property->id}");

        $response->assertStatus(200);
        $response->assertSee('ARV Worksheet');
        $response->assertSee('MAO (70% Rule)');
        $response->assertSee('MAO (72% Rule)');
        $response->assertSee('MAO (75% Rule)');
        $response->assertSee('Comparable Sales');
    }

    public function test_admin_can_delete_a_property_from_the_properties_area(): void
    {
        $this->actingAsAdmin();
        $property = $this->createProperty(['address' => '900 Cleanup Lane']);

        $this->get('/properties')
            ->assertOk()
            ->assertSee('900 Cleanup Lane')
            ->assertSee('Delete');

        $this->delete("/properties/{$property->id}")
            ->assertRedirect('/properties');

        $this->assertDatabaseMissing('properties', ['id' => $property->id]);
    }

    public function test_deleting_a_property_removes_pipeline_deals_for_the_same_lead(): void
    {
        $this->actingAsAdmin();
        $lead = $this->createLead();
        $property = $this->createProperty(['lead_id' => $lead->id]);
        $deal = $this->createDeal([
            'lead_id' => $lead->id,
            'title' => 'Pipeline card to remove',
        ]);
        $document = GeneratedDocument::create([
            'tenant_id' => $this->tenant->id,
            'deal_id' => $deal->id,
            'user_id' => $this->adminUser->id,
            'name' => 'Document attached to deleted deal',
            'content' => '<p>Test</p>',
        ]);

        $this->delete("/properties/{$property->id}")
            ->assertRedirect('/properties');

        $this->assertDatabaseMissing('properties', ['id' => $property->id]);
        $this->assertDatabaseMissing('deals', ['id' => $deal->id]);
        $this->assertDatabaseMissing('generated_documents', ['id' => $document->id]);
        $this->get('/pipeline')
            ->assertOk()
            ->assertDontSee('Pipeline card to remove');
    }
}
