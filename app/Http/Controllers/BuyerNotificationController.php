<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Buyer;
use App\Models\Deal;
use App\Services\BrevoTransactionalEmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

class BuyerNotificationController extends Controller
{
    public function create(Request $request, Deal $deal)
    {
        $this->authorize('notifyBuyer', $deal);

        $deal->load([
            'lead.property',
            'buyerMatches.buyer',
        ]);

        $buyers = Buyer::where('tenant_id', $deal->tenant_id)
            ->orderBy('company')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $selectedBuyerId = $request->integer('buyer');

        return view('deals.notify-buyers', compact('deal', 'buyers', 'selectedBuyerId'));
    }

    public function store(Request $request, Deal $deal, BrevoTransactionalEmailService $brevo): RedirectResponse
    {
        $this->authorize('notifyBuyer', $deal);

        $validated = $request->validate([
            'recipient_target' => ['required', Rule::in(['matched', 'manual', 'brevo_city', 'brevo_deal_type', 'brevo_city_and_deal_type'])],
            'buyer_ids' => ['nullable', 'array'],
            'buyer_ids.*' => ['integer'],
            'confirm_send' => ['accepted'],
        ]);

        try {
            [$recipients, $crmBuyerIds, $targetLabel] = $this->recipientsForRequest($deal, $validated, $brevo);

            $result = $brevo->sendDealNotification($deal, $recipients);
            $sentAt = now();

            $deal->update([
                'buyers_notified_at' => $sentAt,
                'buyers_notified_count' => $result['recipient_count'],
                'buyer_notification_status' => 'sent',
            ]);

            if ($crmBuyerIds->isNotEmpty()) {
                $deal->buyerMatches()
                    ->whereIn('buyer_id', $crmBuyerIds)
                    ->update([
                        'notified_at' => $sentAt,
                        'last_contacted_at' => $sentAt,
                        'outreach_status' => 'sent',
                        'status' => 'contacted',
                    ]);
            }

            Activity::create([
                'tenant_id' => $deal->tenant_id,
                'lead_id' => $deal->lead_id,
                'deal_id' => $deal->id,
                'agent_id' => auth()->id(),
                'type' => 'email',
                'subject' => 'Buyer notification sent',
                'body' => 'Sent to ' . $result['recipient_count'] . ' recipient(s) using ' . $targetLabel . ' targeting.',
                'logged_at' => $sentAt,
            ]);

            AuditLog::log('deal.buyers_notified', $deal, null, [
                'recipient_count' => $result['recipient_count'],
                'target' => $validated['recipient_target'],
                'brevo_message_id' => $result['message_id'],
            ]);
            return redirect()->route('deals.show', $deal)
                ->with('success', 'Buyer notification sent to ' . $result['recipient_count'] . ' recipient(s).');
        } catch (RuntimeException|ConnectionException $exception) {
            $deal->update(['buyer_notification_status' => 'failed']);

            return back()
                ->withInput()
                ->with('error', 'The notification was not sent. ' . $exception->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $validated
     * @return array{0: Collection<int, array{email: string, name: string}>, 1: Collection<int, int>, 2: string}
     */
    private function recipientsForRequest(Deal $deal, array $validated, BrevoTransactionalEmailService $brevo): array
    {
        return match ($validated['recipient_target']) {
            'matched' => $this->matchedRecipients($deal),
            'manual' => $this->manualRecipients($deal, collect($validated['buyer_ids'] ?? [])),
            'brevo_city' => [$brevo->recipientsForDealFilter($deal, 'city'), collect(), 'City audience'],
            'brevo_deal_type' => [$brevo->recipientsForDealFilter($deal, 'deal_type'), collect(), 'Deal Type audience'],
            'brevo_city_and_deal_type' => [$brevo->recipientsForDealFilter($deal, 'city_and_deal_type'), collect(), 'City + Deal Type audience'],
        };
    }

    /**
     * @return array{0: Collection<int, array{email: string, name: string}>, 1: Collection<int, int>, 2: string}
     */
    private function matchedRecipients(Deal $deal): array
    {
        $matches = $deal->buyerMatches()
            ->with('buyer')
            ->get()
            ->filter(fn ($match) => filter_var($match->buyer?->email, FILTER_VALIDATE_EMAIL));

        if ($matches->isEmpty()) {
            throw new RuntimeException('There are no CRM-matched buyers with a usable email address for this deal.');
        }

        return [
            $matches->map(fn ($match) => $this->buyerRecipient($match->buyer))->values(),
            $matches->pluck('buyer_id')->map(fn ($id) => (int) $id)->values(),
            'CRM matched buyers',
        ];
    }

    /**
     * @param Collection<int, mixed> $requestedBuyerIds
     * @return array{0: Collection<int, array{email: string, name: string}>, 1: Collection<int, int>, 2: string}
     */
    private function manualRecipients(Deal $deal, Collection $requestedBuyerIds): array
    {
        $buyerIds = $requestedBuyerIds
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($buyerIds->isEmpty()) {
            throw new RuntimeException('Select at least one buyer from the CRM Buyer Database.');
        }

        $buyers = Buyer::where('tenant_id', $deal->tenant_id)
            ->whereIn('id', $buyerIds)
            ->get()
            ->filter(fn (Buyer $buyer) => filter_var($buyer->email, FILTER_VALIDATE_EMAIL));

        if ($buyers->isEmpty()) {
            throw new RuntimeException('The selected buyers do not have usable email addresses.');
        }

        return [
            $buyers->map(fn (Buyer $buyer) => $this->buyerRecipient($buyer))->values(),
            $buyers->pluck('id')->map(fn ($id) => (int) $id)->values(),
            'manual CRM Buyer Database',
        ];
    }

    /**
     * @return array{email: string, name: string}
     */
    private function buyerRecipient(Buyer $buyer): array
    {
        return [
            'email' => $buyer->email,
            'name' => trim($buyer->full_name),
        ];
    }
}
