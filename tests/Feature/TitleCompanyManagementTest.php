<?php

namespace Tests\Feature;

use App\Models\TitleCompany;
use Tests\TestCase;

class TitleCompanyManagementTest extends TestCase
{
    public function test_admin_can_create_a_title_company_and_select_it_once_on_a_deal(): void
    {
        $this->actingAsAdmin();

        $this->post(route('title-companies.store'), [
            'name' => 'Peachtree Title',
            'closing_attorney' => 'Avery Stone',
            'address' => '10 Main St',
            'city' => 'Atlanta',
            'state' => 'GA',
            'zip_code' => '30303',
            'email' => 'closings@peachtree.test',
        ])->assertRedirect();

        $titleCompany = TitleCompany::where('name', 'Peachtree Title')->firstOrFail();
        $this->assertSame('10 Main St, Atlanta, GA 30303', $titleCompany->full_address);

        $deal = $this->createDeal();
        $this->putJson(route('deals.update', $deal), [
            'title_company_id' => $titleCompany->id,
        ])->assertOk()->assertJsonPath('deal.title_company_id', $titleCompany->id);

        $this->assertSame($titleCompany->id, $deal->fresh()->title_company_id);
        $this->get(route('deals.show', $deal))
            ->assertOk()
            ->assertSee('Title Company / Closing Attorney')
            ->assertSee('Peachtree Title')
            ->assertSee('Avery Stone');
    }
}
