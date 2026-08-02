<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Buyer;
use App\Models\DealBuyerMatch;
use App\Services\BrevoTransactionalEmailService;
use Illuminate\Support\Collection;
use Mockery\MockInterface;
use Tests\TestCase;

class BuyerNotificationTest extends TestCase
{
    public function test_notify_buyers_page_shows_all_agreed_targeting_options_and_live_crm_buyers(): void
    {
        $this->actingAsAdmin();
        $deal = $this->createDeal(['deal_type' => 'wholesale']);
        $this->createProperty([
            'lead_id' => $deal->lead_id,
            'city' => 'Atlanta',
        ]);
        $matchedBuyer = Buyer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'matched@example.com',
        ]);
        $manualBuyer = Buyer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company' => 'New CRM Buyer',
            'email' => 'new-buyer@example.com',
        ]);
        Buyer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company' => 'No Email Buyer',
            'email' => null,
        ]);
        DealBuyerMatch::create([
            'deal_id' => $deal->id,
            'buyer_id' => $matchedBuyer->id,
            'match_score' => 90,
        ]);

        $response = $this->get(route('deals.notifyBuyers.create', $deal));

        $response->assertOk();
        $response->assertSee('CRM-matched buyers');
        $response->assertSee('Manual CRM Buyer Database selection');
        $response->assertSee('Select All');
        $response->assertSee('City audience');
        $response->assertSee('Deal Type audience');
        $response->assertSee('City + Deal Type audience');
        $response->assertSee('New CRM Buyer');
        $response->assertSee('No usable email - excluded');
        $response->assertSee('Transactional template');
    }

    public function test_manual_notification_sends_selected_live_crm_buyers_and_records_status(): void
    {
        $this->actingAsAdmin();
        $deal = $this->createDeal();
        $selectedBuyer = Buyer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Selected',
            'last_name' => 'Buyer',
            'email' => 'selected@example.com',
        ]);
        $noEmailBuyer = Buyer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => null,
        ]);
        $match = DealBuyerMatch::create([
            'deal_id' => $deal->id,
            'buyer_id' => $selectedBuyer->id,
            'match_score' => 70,
        ]);

        $this->mock(BrevoTransactionalEmailService::class, function (MockInterface $mock) use ($deal) {
            $mock->shouldReceive('sendDealNotification')
                ->once()
                ->withArgs(function ($passedDeal, $recipients) use ($deal) {
                    return $passedDeal->is($deal)
                        && $recipients instanceof Collection
                        && $recipients->all() === [[
                            'email' => 'selected@example.com',
                            'name' => 'Selected Buyer',
                        ]];
                })
                ->andReturn(['message_id' => 'message-123', 'recipient_count' => 1]);
        });

        $response = $this->post(route('deals.notifyBuyers.store', $deal), [
            'recipient_target' => 'manual',
            'buyer_ids' => [$selectedBuyer->id, $noEmailBuyer->id],
            'confirm_send' => '1',
        ]);

        $response->assertRedirect(route('deals.show', $deal));
        $response->assertSessionHas('success', 'Buyer notification sent to 1 recipient(s).');
        $deal->refresh();
        $this->assertSame('sent', $deal->buyer_notification_status);
        $this->assertSame(1, $deal->buyers_notified_count);
        $this->assertNotNull($deal->buyers_notified_at);
        $this->assertSame('sent', $match->fresh()->outreach_status);
        $this->assertSame('contacted', $match->fresh()->status);
        $this->assertNotNull($match->fresh()->notified_at);
        $this->assertDatabaseHas('activities', [
            'deal_id' => $deal->id,
            'type' => 'email',
            'subject' => 'Buyer notification sent',
        ]);
    }

    public function test_brevo_city_and_deal_type_target_is_sent_and_recorded_without_creating_new_crm_matches(): void
    {
        $this->actingAsAdmin();
        $deal = $this->createDeal(['deal_type' => 'wholesale']);
        $this->createProperty([
            'lead_id' => $deal->lead_id,
            'city' => 'Atlanta',
        ]);

        $this->mock(BrevoTransactionalEmailService::class, function (MockInterface $mock) use ($deal) {
            $mock->shouldReceive('recipientsForDealFilter')
                ->once()
                ->withArgs(fn ($passedDeal, $target) => $passedDeal->is($deal) && $target === 'city_and_deal_type')
                ->andReturn(collect([['email' => 'brevo-buyer@example.com', 'name' => 'Brevo Buyer']]));
            $mock->shouldReceive('sendDealNotification')
                ->once()
                ->withArgs(fn ($passedDeal, $recipients) => $passedDeal->is($deal) && $recipients->count() === 1)
                ->andReturn(['message_id' => 'message-456', 'recipient_count' => 1]);
        });

        $response = $this->post(route('deals.notifyBuyers.store', $deal), [
            'recipient_target' => 'brevo_city_and_deal_type',
            'confirm_send' => '1',
        ]);

        $response->assertRedirect(route('deals.show', $deal));
        $deal->refresh();
        $this->assertSame('sent', $deal->buyer_notification_status);
        $this->assertSame(1, $deal->buyers_notified_count);
        $this->assertSame(0, $deal->buyerMatches()->count());
    }

    public function test_manual_selection_cannot_send_another_tenants_buyer(): void
    {
        $this->actingAsAdmin();
        $deal = $this->createDeal();
        $firstTenantAdmin = $this->adminUser;

        $otherTenantAdmin = $this->createTenantWithAdmin([
            'slug' => 'other-company',
            'email' => 'other@example.com',
        ]);
        $otherBuyer = Buyer::factory()->create([
            'tenant_id' => $otherTenantAdmin->tenant_id,
            'email' => 'other-buyer@example.com',
        ]);

        $this->actingAs($firstTenantAdmin);
        $response = $this->from(route('deals.notifyBuyers.create', $deal))
            ->post(route('deals.notifyBuyers.store', $deal), [
                'recipient_target' => 'manual',
                'buyer_ids' => [$otherBuyer->id],
                'confirm_send' => '1',
            ]);

        $response->assertRedirect(route('deals.notifyBuyers.create', $deal));
        $response->assertSessionHas('error');
        $this->assertNull($deal->fresh()->buyers_notified_at);
    }
}
