<?php

namespace App\Services;

use App\Models\Deal;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class BrevoTransactionalEmailService
{
    private const API_BASE_URL = 'https://api.brevo.com/v3';

    /**
     * Verify the supplied Brevo configuration without sending email.
     *
     * @return array<string, mixed>
     */
    public function preflight(): array
    {
        $this->ensureConfigured();

        $account = $this->get('/account')->json();
        $template = $this->get('/smtp/templates/' . config('services.brevo.transactional_template_id'))->json();
        $attributes = collect($this->get('/contacts/attributes')->json('attributes', []));
        $lists = collect($this->get('/contacts/lists', ['limit' => 50])->json('lists', []));
        $testContact = $this->get('/contacts/' . rawurlencode((string) config('services.brevo.test_contact_email')), [
            'identifierType' => 'email_id',
        ])->json();

        if (! ($template['isActive'] ?? false)) {
            throw new RuntimeException('The configured Brevo transactional template is not active.');
        }

        $attributeNames = $attributes->pluck('name')->all();
        $requiredAttributes = [
            config('services.brevo.city_attribute'),
            config('services.brevo.deal_type_attribute'),
        ];
        $missingAttributes = array_values(array_diff($requiredAttributes, $attributeNames));

        if ($missingAttributes !== []) {
            throw new RuntimeException('Brevo is missing required contact attributes: ' . implode(', ', $missingAttributes) . '.');
        }

        $testListName = config('services.brevo.test_list_name');
        $testList = $lists->first(fn (array $list) => Str::lower($list['name'] ?? '') === Str::lower((string) $testListName));

        if ($testListName && ! $testList) {
            throw new RuntimeException('The configured Brevo test list was not found.');
        }

        return [
            'account_email' => $account['email'] ?? null,
            'template_id' => $template['id'] ?? null,
            'template_name' => $template['name'] ?? null,
            'template_sender' => data_get($template, 'sender.email'),
            'template_reply_to' => $template['replyTo'] ?? null,
            'test_contact_email' => $testContact['email'] ?? null,
            'test_list_id' => $testList['id'] ?? null,
            'test_list_name' => $testList['name'] ?? null,
        ];
    }

    /**
     * @return Collection<int, array{email: string, name: string}>
     */
    public function recipientsForDealFilter(Deal $deal, string $target): Collection
    {
        $this->ensureConfigured();

        $deal->loadMissing('lead.property');
        $property = $deal->lead?->property;
        $city = trim((string) $property?->city);
        $dealType = $this->dealTypeLabel($deal);

        if (in_array($target, ['city', 'city_and_deal_type'], true) && $city === '') {
            throw new RuntimeException('This deal needs a property city before Brevo City targeting can be used.');
        }

        if (in_array($target, ['deal_type', 'city_and_deal_type'], true) && $dealType === '') {
            throw new RuntimeException('This deal needs a Deal Type before Brevo Deal Type targeting can be used.');
        }

        $filter = match ($target) {
            'city', 'city_and_deal_type' => $this->attributeEqualsFilter((string) config('services.brevo.city_attribute'), $city),
            'deal_type' => $this->attributeEqualsFilter((string) config('services.brevo.deal_type_attribute'), $dealType),
            default => throw new RuntimeException('Unsupported Brevo recipient target.'),
        };

        return $this->contacts($filter)
            ->filter(function (array $contact) use ($target, $city, $dealType): bool {
                if (($contact['emailBlacklisted'] ?? false) || ! filter_var($contact['email'] ?? null, FILTER_VALIDATE_EMAIL)) {
                    return false;
                }

                $attributes = $contact['attributes'] ?? [];

                return match ($target) {
                    'city' => $this->sameAttributeValue($attributes[config('services.brevo.city_attribute')] ?? null, $city),
                    'deal_type' => $this->sameAttributeValue($attributes[config('services.brevo.deal_type_attribute')] ?? null, $dealType),
                    'city_and_deal_type' => $this->sameAttributeValue($attributes[config('services.brevo.city_attribute')] ?? null, $city)
                        && $this->sameAttributeValue($attributes[config('services.brevo.deal_type_attribute')] ?? null, $dealType),
                };
            })
            ->map(fn (array $contact) => [
                'email' => Str::lower($contact['email']),
                'name' => trim((string) (($contact['attributes']['FIRSTNAME'] ?? '') . ' ' . ($contact['attributes']['LASTNAME'] ?? ''))),
            ])
            ->unique('email')
            ->values();
    }

    /**
     * @param Collection<int, array{email: string, name?: string}> $recipients
     * @return array{message_id: string|null, recipient_count: int}
     */
    public function sendDealNotification(Deal $deal, Collection $recipients): array
    {
        $this->ensureConfigured();

        $to = $recipients
            ->filter(fn (array $recipient) => filter_var($recipient['email'] ?? null, FILTER_VALIDATE_EMAIL))
            ->map(fn (array $recipient) => array_filter([
                'email' => Str::lower((string) $recipient['email']),
                'name' => trim((string) ($recipient['name'] ?? '')) ?: null,
            ]))
            ->unique('email')
            ->values();

        if ($to->isEmpty()) {
            throw new RuntimeException('There are no recipients with a usable email address.');
        }

        $deal->loadMissing(['tenant', 'lead.property', 'lead.photos']);

        $response = $this->post('/smtp/email', [
            'templateId' => config('services.brevo.transactional_template_id'),
            'sender' => [
                'email' => config('services.brevo.sender_email'),
                'name' => $deal->tenant?->name,
            ],
            'replyTo' => [
                'email' => config('services.brevo.reply_to_email'),
            ],
            'to' => $to->all(),
            'params' => $this->dealParameters($deal),
            'tags' => ['crm-buyer-notify', 'deal-' . $deal->id],
        ]);

        return [
            'message_id' => $response->json('messageId') ?? $response->json('messageIds.0'),
            'recipient_count' => $to->count(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function contacts(string $filter): Collection
    {
        $contacts = collect();
        $offset = 0;
        $total = null;

        do {
            $response = $this->get('/contacts', [
                'limit' => 500,
                'offset' => $offset,
                'filter' => $filter,
            ]);
            $batch = collect($response->json('contacts', []));
            $contacts = $contacts->concat($batch);
            $offset += $batch->count();
            $total = $response->json('count');
        } while ($batch->isNotEmpty() && $total !== null && $offset < $total);

        return $contacts;
    }

    /**
     * @return array<string, string|null>
     */
    private function dealParameters(Deal $deal): array
    {
        $property = $deal->lead?->property;
        $photoUrl = $deal->lead?->photos->first()?->url;
        $dealType = $this->dealTypeLabel($deal);
        $askingPrice = $property?->asking_price;
        $arv = $property?->after_repair_value;

        return [
            // These names match the client's active Brevo transactional template.
            // The template adds its own dollar sign before asking_price and arv.
            'arv' => $this->number($arv),
            'asking_price' => $this->number($askingPrice),
            'baths' => $property?->bathrooms !== null ? (string) $property->bathrooms : null,
            'beds' => $property?->bedrooms !== null ? (string) $property->bedrooms : null,
            'deal_type' => $dealType,
            'photo_url' => $photoUrl,
            'repair_level' => $this->repairLevel($deal),
            'sqft' => $property?->square_footage ? number_format((int) $property->square_footage) : null,

            // Keep these generic aliases available for a future approved template revision.
            'DEAL_TITLE' => $deal->title,
            'DEAL_TYPE' => $dealType,
            'PROPERTY_ADDRESS' => $property?->address,
            'PROPERTY_CITY' => $property?->city,
            'PROPERTY_STATE' => $property?->state,
            'PROPERTY_ZIP' => $property?->zip_code,
            'PROPERTY_FULL_ADDRESS' => $property?->full_address,
            'PROPERTY_TYPE' => $property?->property_type ? Str::headline($property->property_type) : null,
            'CONTRACT_PRICE' => $this->currency($deal->contract_price),
            'DEAL_PRICE' => $this->currency($deal->contract_price),
            'ARV' => $this->currency($arv),
            'REPAIR_ESTIMATE' => $this->currency($property?->repair_estimate),
            'MAO' => $this->currency($property?->mao),
            'CLOSING_DATE' => $deal->closing_date?->format('m/d/Y'),
            'PROPERTY_PHOTO_URL' => $photoUrl,
            'PHOTO_URL' => $photoUrl,
        ];
    }

    private function dealTypeLabel(Deal $deal): string
    {
        $propertyDealType = $deal->lead?->property?->deal_type;
        $dealType = $deal->deal_type ?: $propertyDealType;

        return Deal::DEAL_TYPES[$dealType] ?? '';
    }

    private function currency(mixed $amount): ?string
    {
        return $amount === null ? null : '$' . number_format((float) $amount, 2);
    }

    private function number(mixed $amount): ?string
    {
        return $amount === null ? null : number_format((float) $amount, 2);
    }

    private function repairLevel(Deal $deal): ?string
    {
        $condition = $deal->lead?->property?->condition;

        if (! $condition) {
            return null;
        }

        $options = CustomFieldService::getOptions('property_condition', $deal->tenant);

        return $options[$condition] ?? Str::headline($condition);
    }

    private function attributeEqualsFilter(string $attribute, string $value): string
    {
        $escapedValue = addcslashes($value, "\\\"");

        return sprintf('equals(%s,"%s")', $attribute, $escapedValue);
    }

    private function sameAttributeValue(mixed $actual, string $expected): bool
    {
        return Str::lower(trim((string) $actual)) === Str::lower(trim($expected));
    }

    private function ensureConfigured(): void
    {
        $required = [
            'api_key' => config('services.brevo.api_key'),
            'transactional_template_id' => config('services.brevo.transactional_template_id'),
            'sender_email' => config('services.brevo.sender_email'),
            'reply_to_email' => config('services.brevo.reply_to_email'),
            'test_contact_email' => config('services.brevo.test_contact_email'),
        ];

        $missing = array_keys(array_filter($required, fn ($value) => blank($value)));

        if ($missing !== []) {
            throw new RuntimeException('Brevo is missing required configuration: ' . implode(', ', $missing) . '.');
        }
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withHeaders(['api-key' => config('services.brevo.api_key')])
            ->timeout(config('services.brevo.timeout'));
    }

    /**
     * @param array<string, mixed> $query
     */
    private function get(string $path, array $query = []): Response
    {
        return $this->ensureSuccess($this->http()->get(self::API_BASE_URL . $path, $query));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function post(string $path, array $payload): Response
    {
        return $this->ensureSuccess($this->http()->post(self::API_BASE_URL . $path, $payload));
    }

    private function ensureSuccess(Response $response): Response
    {
        if (! $response->successful()) {
            throw new RuntimeException('Brevo request failed with HTTP ' . $response->status() . '.');
        }

        return $response;
    }
}
