<?php

namespace Tests\Unit\Services;

use App\Models\LeadPhoto;
use App\Services\BrevoTransactionalEmailService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BrevoTransactionalEmailServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.brevo', [
            'api_key' => 'test-api-key',
            'transactional_template_id' => 1,
            'sender_email' => 'bouncebackrealty@gmail.com',
            'reply_to_email' => 'bouncebackrealty@gmail.com',
            'city_attribute' => 'CITY',
            'deal_type_attribute' => 'DEAL_TYPE',
            'test_contact_email' => 'bouncebackrealty@gmail.com',
            'test_list_name' => 'BBR Buyers Test',
            'timeout' => 20,
        ]);
    }

    public function test_preflight_verifies_the_brevo_account_configuration_without_sending_email(): void
    {
        Http::fake([
            'api.brevo.com/v3/account' => Http::response(['email' => 'bouncebackrealty@gmail.com']),
            'api.brevo.com/v3/smtp/templates/1' => Http::response([
                'id' => 1,
                'name' => 'Buyer Deal Notification',
                'isActive' => true,
                'sender' => ['email' => 'bouncebackrealty@gmail.com'],
                'replyTo' => 'bouncebackrealty@gmail.com',
            ]),
            'api.brevo.com/v3/contacts/attributes' => Http::response([
                'attributes' => [['name' => 'CITY'], ['name' => 'DEAL_TYPE']],
            ]),
            'api.brevo.com/v3/contacts/lists*' => Http::response([
                'lists' => [['id' => 7, 'name' => 'BBR Buyers Test']],
            ]),
            'api.brevo.com/v3/contacts/bouncebackrealty%40gmail.com*' => Http::response([
                'email' => 'bouncebackrealty@gmail.com',
            ]),
        ]);

        $result = app(BrevoTransactionalEmailService::class)->preflight();

        $this->assertSame(1, $result['template_id']);
        $this->assertSame(7, $result['test_list_id']);
        Http::assertNotSent(fn (Request $request) => str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/smtp/email'));
    }

    public function test_it_sends_the_transactional_template_with_current_deal_details_and_photo_url(): void
    {
        $this->actingAsAdmin();
        $deal = $this->createDeal(['deal_type' => 'wholesale', 'contract_price' => 185000]);
        $property = $this->createProperty([
            'lead_id' => $deal->lead_id,
            'address' => '4521 Mill Creek Road',
            'city' => 'Atlanta',
            'state' => 'GA',
            'zip_code' => '30318',
            'asking_price' => 195000,
            'after_repair_value' => 275000,
            'repair_estimate' => 45000,
            'bedrooms' => 3,
            'bathrooms' => 2,
            'square_footage' => 1450,
            'condition' => 'fair',
        ]);
        LeadPhoto::create([
            'tenant_id' => $deal->tenant_id,
            'lead_id' => $deal->lead_id,
            'uploaded_by' => $this->adminUser->id,
            'filename' => 'property.jpg',
            'original_name' => 'property.jpg',
            'path' => 'lead-photos/' . $deal->lead_id . '/property.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 512,
        ]);

        Http::fake([
            'api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'brevo-message-123'], 201),
        ]);

        $result = app(BrevoTransactionalEmailService::class)->sendDealNotification($deal, collect([
            ['email' => 'buyer@example.com', 'name' => 'Buyer One'],
            ['email' => 'BUYER@example.com', 'name' => 'Duplicate Buyer'],
            ['email' => 'not-an-email'],
        ]));

        $this->assertSame('brevo-message-123', $result['message_id']);
        $this->assertSame(1, $result['recipient_count']);

        Http::assertSent(function (Request $request) {
            $data = $request->data();

            return $request->url() === 'https://api.brevo.com/v3/smtp/email'
                && $request->hasHeader('api-key', 'test-api-key')
                && $data['templateId'] === 1
                && $data['sender']['email'] === 'bouncebackrealty@gmail.com'
                && $data['replyTo']['email'] === 'bouncebackrealty@gmail.com'
                && $data['to'] === [['email' => 'buyer@example.com', 'name' => 'Buyer One']]
                && $data['params']['asking_price'] === '195,000.00'
                && $data['params']['arv'] === '275,000.00'
                && $data['params']['beds'] === '3'
                && $data['params']['baths'] === '2'
                && $data['params']['sqft'] === '1,450'
                && $data['params']['repair_level'] === 'Fair'
                && $data['params']['deal_type'] === 'Wholesale'
                && $data['params']['photo_url'] === url('storage/lead-photos/' . $this->getDealLeadId() . '/property.jpg')
                && $data['params']['DEAL_TYPE'] === 'Wholesale'
                && $data['params']['CONTRACT_PRICE'] === '$185,000.00'
                && $data['params']['ARV'] === '$275,000.00'
                && $data['params']['PROPERTY_PHOTO_URL'] === url('storage/lead-photos/' . $this->getDealLeadId() . '/property.jpg');
        });
    }

    public function test_it_filters_brevo_contacts_by_the_deal_city_and_type(): void
    {
        $this->actingAsAdmin();
        $deal = $this->createDeal(['deal_type' => 'wholesale']);
        $this->createProperty([
            'lead_id' => $deal->lead_id,
            'city' => 'Atlanta',
        ]);

        Http::fake([
            'api.brevo.com/v3/contacts*' => Http::response([
                'count' => 3,
                'contacts' => [
                    ['email' => 'atlanta-wholesale@example.com', 'attributes' => ['CITY' => 'Atlanta', 'DEAL_TYPE' => 'Wholesale']],
                    ['email' => 'atlanta-rental@example.com', 'attributes' => ['CITY' => 'Atlanta', 'DEAL_TYPE' => 'Rental']],
                    ['email' => 'blocked@example.com', 'emailBlacklisted' => true, 'attributes' => ['CITY' => 'Atlanta', 'DEAL_TYPE' => 'Wholesale']],
                ],
            ]),
        ]);

        $recipients = app(BrevoTransactionalEmailService::class)->recipientsForDealFilter($deal, 'city_and_deal_type');

        $this->assertSame([['email' => 'atlanta-wholesale@example.com', 'name' => '']], $recipients->all());
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/v3/contacts')
            && ($request->data()['filter'] ?? null) === 'equals(CITY,"Atlanta")');
    }

    private function getDealLeadId(): int
    {
        return (int) \App\Models\Deal::firstOrFail()->lead_id;
    }
}
