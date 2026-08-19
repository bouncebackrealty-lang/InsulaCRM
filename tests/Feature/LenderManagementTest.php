<?php

namespace Tests\Feature;

use App\Models\DealLender;
use App\Models\Lender;
use App\Models\LenderLoanProgram;
use Tests\TestCase;

class LenderManagementTest extends TestCase
{
    private function makeLender(array $overrides = []): Lender
    {
        return Lender::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Bridge Capital',
            'company' => 'Bridge Capital Group',
            'phone' => '555-0200',
            'email' => 'loans@example.com',
            'service_area' => 'Atlanta Metro',
            'notes' => 'Hard money lender',
        ], $overrides));
    }

    private function makeProgram(Lender $lender, array $overrides = []): LenderLoanProgram
    {
        return LenderLoanProgram::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'lender_id' => $lender->id,
            'program_name' => 'Fix and Flip',
            'interest_rate' => 12.5,
            'points' => 2,
            'max_ltc' => 85,
            'max_ltv' => 70,
            'term_length' => '12 months',
            'purchase_closing_cost_percent' => 3,
            'builders_risk_insurance' => true,
            'notes' => 'Requires entity borrower',
        ], $overrides));
    }

    public function test_admin_can_view_lenders_index(): void
    {
        $this->actingAsAdmin();
        $this->makeLender();

        $response = $this->get('/lenders');

        $response->assertStatus(200);
        $response->assertSee('Bridge Capital');
    }

    public function test_admin_can_create_lender(): void
    {
        $this->actingAsAdmin();

        $response = $this->post('/lenders', [
            'name' => 'Asset Funding',
            'company' => 'Asset Funding LLC',
            'phone' => '555-0300',
            'email' => 'asset@example.com',
            'service_area' => 'Georgia',
            'notes' => 'Prefers light rehab',
        ]);

        $lender = Lender::where('name', 'Asset Funding')->first();
        $response->assertRedirect("/lenders/{$lender->id}");
        $this->assertDatabaseHas('lenders', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Asset Funding',
            'company' => 'Asset Funding LLC',
        ]);
    }

    public function test_name_is_required(): void
    {
        $this->actingAsAdmin();

        $response = $this->post('/lenders', [
            'company' => 'No Name Capital',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_admin_can_view_update_and_delete_lender(): void
    {
        $this->actingAsAdmin();
        $lender = $this->makeLender();

        $this->get("/lenders/{$lender->id}")->assertStatus(200);

        $response = $this->put("/lenders/{$lender->id}", [
            'name' => 'Bridge Capital Updated',
            'company' => 'Bridge Capital Group',
        ]);

        $response->assertRedirect("/lenders/{$lender->id}");
        $this->assertEquals('Bridge Capital Updated', $lender->fresh()->name);

        $this->delete("/lenders/{$lender->id}")->assertRedirect('/lenders');
        $this->assertDatabaseMissing('lenders', ['id' => $lender->id]);
    }

    public function test_admin_can_add_update_and_delete_multiple_loan_programs(): void
    {
        $this->actingAsAdmin();
        $lender = $this->makeLender();

        $this->post("/lenders/{$lender->id}/programs", [
            'program_name' => 'Fix and Flip',
            'interest_rate' => 12.5,
            'points' => 2,
            'max_ltc' => 85,
            'max_ltv' => 70,
            'term_length' => '12 months',
            'purchase_closing_cost_percent' => 3,
            'builders_risk_insurance' => '1',
            'notes' => 'Fast close',
        ])->assertRedirect("/lenders/{$lender->id}");

        $this->post("/lenders/{$lender->id}/programs", [
            'program_name' => 'Rental DSCR',
            'max_ltv' => 75,
        ])->assertRedirect("/lenders/{$lender->id}");

        $this->assertDatabaseCount('lender_loan_programs', 2);
        $program = LenderLoanProgram::where('program_name', 'Fix and Flip')->first();
        $this->assertEquals('85.00', $program->max_ltc);
        $this->assertEquals('70.00', $program->max_ltv);
        $this->assertTrue($program->builders_risk_insurance);

        $this->put("/lender-programs/{$program->id}", [
            'program_name' => 'Fix and Flip Plus',
            'interest_rate' => 11.75,
            'builders_risk_insurance' => '0',
            'notes' => 'Updated terms',
        ])->assertRedirect("/lenders/{$lender->id}");

        $program->refresh();
        $this->assertEquals('Fix and Flip Plus', $program->program_name);
        $this->assertEquals('11.75', $program->interest_rate);
        $this->assertEquals('Updated terms', $program->notes);
        $this->assertFalse($program->builders_risk_insurance);

        $this->delete("/lender-programs/{$program->id}")->assertRedirect("/lenders/{$lender->id}");
        $this->assertDatabaseMissing('lender_loan_programs', ['id' => $program->id]);
    }

    public function test_admin_can_attach_lender_program_to_deal_and_update_status(): void
    {
        $this->actingAsAdmin();
        $lender = $this->makeLender();
        $program = $this->makeProgram($lender);
        $deal = $this->createDeal();

        $response = $this->post("/pipeline/{$deal->id}/lenders", [
            'lender_loan_program_id' => $program->id,
            'status' => 'term_sheet_received',
            'notes' => 'Sent package',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('deal_lenders', [
            'deal_id' => $deal->id,
            'lender_id' => $lender->id,
            'lender_loan_program_id' => $program->id,
            'status' => 'term_sheet_received',
        ]);

        $dealLender = DealLender::first();
        $this->patch("/deal-lenders/{$dealLender->id}", [
            'status' => 'approved',
            'notes' => 'Approved subject to appraisal',
        ])->assertRedirect();
        $this->assertEquals('approved', $dealLender->fresh()->status);

        $show = $this->get("/pipeline/{$deal->id}");
        $show->assertStatus(200);
        $show->assertSee('Bridge Capital');
        $show->assertSee('Fix and Flip');
        $show->assertSee('LTC:');
        $show->assertSee('LTV:');
        $show->assertSee('Term:');
        $show->assertSee('Purchase Closing Cost:');
        $show->assertSee('Builder Risk:');
        $show->assertSee('Program Notes:');

        $this->delete("/deal-lenders/{$dealLender->id}")->assertRedirect();
        $this->assertDatabaseMissing('deal_lenders', ['id' => $dealLender->id]);
    }

    public function test_same_lender_program_cannot_be_attached_twice_to_same_deal(): void
    {
        $this->actingAsAdmin();
        $lender = $this->makeLender();
        $program = $this->makeProgram($lender);
        $deal = $this->createDeal();
        DealLender::create([
            'deal_id' => $deal->id,
            'lender_id' => $lender->id,
            'lender_loan_program_id' => $program->id,
        ]);

        $response = $this->post("/pipeline/{$deal->id}/lenders", [
            'lender_loan_program_id' => $program->id,
            'status' => 'inquired',
        ]);

        $response->assertSessionHasErrors('lender_loan_program_id');
    }

    public function test_foreign_tenant_lender_program_cannot_be_attached(): void
    {
        $this->actingAsAdmin();
        $foreignLender = $this->makeLender(['name' => 'Foreign Capital']);
        $foreignProgram = $this->makeProgram($foreignLender);

        $this->createTenantWithAdmin(['slug' => 'second-co', 'email' => 'admin2@test.com']);
        $this->actingAs($this->adminUser);
        $deal = $this->createDeal();

        $response = $this->post("/pipeline/{$deal->id}/lenders", [
            'lender_loan_program_id' => $foreignProgram->id,
            'status' => 'inquired',
        ]);

        $response->assertSessionHasErrors('lender_loan_program_id');
    }

    public function test_lender_is_scoped_to_tenant(): void
    {
        $this->actingAsAdmin();
        $foreign = $this->makeLender(['name' => 'Foreign Capital']);

        $this->createTenantWithAdmin(['slug' => 'second-co', 'email' => 'admin2@test.com']);
        $this->actingAs($this->adminUser);

        $this->get('/lenders')->assertDontSee('Foreign Capital');
        $this->get("/lenders/{$foreign->id}")->assertStatus(404);
    }

    public function test_acquisition_agent_can_access_lenders(): void
    {
        $this->createTenantWithAdmin();
        $this->actingAsRole('acquisition_agent');

        $this->get('/lenders')->assertStatus(200);
    }

    public function test_field_scout_cannot_access_lenders(): void
    {
        $this->createTenantWithAdmin();
        $this->actingAsRole('field_scout');

        $this->get('/lenders')->assertStatus(403);
    }
}
