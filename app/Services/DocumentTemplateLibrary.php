<?php

namespace App\Services;

/**
 * The standard document set supplied with the wholesale CRM.
 *
 * These definitions are used only to backfill a missing template by name. They
 * never overwrite a tenant's existing template or customisation.
 */
final class DocumentTemplateLibrary
{
    /**
     * @return array<int, array{name:string,type:string,content:string,merge_fields:array<int,string>,input_fields:array<int,array<string,mixed>>,is_default:bool}>
     */
    public static function bbrTemplates(): array
    {
        return [
            self::template(
                '1A-01-Letter of Intent',
                'loi',
                'LETTER OF INTENT',
                '<p><strong>Date:</strong> {{input.document_date}}</p>'
                .'<p>To: {{lead.full_name}}</p>'
                .self::propertySummary()
                .self::terms([
                    'Buyer' => '{{buyer.top_match}}',
                    'Purchase Price' => '{{deal.contract_price}}',
                    'Earnest Money Deposit' => '{{deal.earnest_money}}',
                    'Due Diligence Ends' => '{{deal.due_diligence_end_date}}',
                    'Closing Date' => '{{deal.closing_date}}',
                    'Title Company / Closing Attorney' => '{{title_company.name}} {{title_company.closing_attorney}}',
                ])
                .'<p>This non-binding letter states the proposed terms. A signed purchase agreement controls the transaction.</p>'
                .self::signature('Buyer / Authorized Representative', '{{buyer.top_match}}'),
            ),
            self::template(
                '1A-02-Purchase Agreement',
                'purchase_agreement',
                'PURCHASE AGREEMENT',
                '<p><strong>Agreement Date:</strong> {{input.document_date}}</p>'
                .'<p>This agreement is between {{lead.full_name}} (Seller) and {{company.name}} (Buyer).</p>'
                .self::propertySummary()
                .self::terms([
                    'Purchase Price' => '{{deal.contract_price}}',
                    'Earnest Money Deposit' => '{{deal.earnest_money}}',
                    'Inspection / Due Diligence Period' => '{{deal.inspection_period_days}} days',
                    'Closing Date' => '{{deal.closing_date}}',
                    'Title Company' => '{{title_company.name}}',
                ])
                .'<p>The parties should complete and review all required state-specific disclosures and closing documents before signing.</p>'
                .self::twoPartySignatures('Seller', '{{lead.full_name}}', 'Buyer', '{{company.name}}'),
            ),
            self::template(
                '1A-03-Seller Disclosure',
                'other',
                'SELLER PROPERTY DISCLOSURE',
                '<p><strong>Date:</strong> {{input.document_date}}</p>'
                .'<p><strong>Seller:</strong> {{lead.full_name}}</p>'
                .self::propertySummary()
                .'<p>Seller discloses the known material facts and conditions affecting the property. Attach any state-required disclosure schedules to this form.</p>'
                .'<p><strong>Known conditions / disclosures:</strong></p><div class="document-blank">&nbsp;</div>'
                .self::signature('Seller', '{{lead.full_name}}'),
            ),
            self::template(
                '1A-04-Proof of Funds',
                'other',
                'PROOF OF FUNDS',
                '<p><strong>Date:</strong> {{input.document_date}}</p>'
                .'<p>{{company.name}} confirms that funds are available for the proposed acquisition described below.</p>'
                .self::propertySummary()
                .self::terms([
                    'Proposed Purchase Price' => '{{deal.contract_price}}',
                    'Funding Source' => '{{input.source_of_funds}}',
                    'Lender / Source Name' => '{{lender.name}}',
                ])
                .self::signature('Authorized Representative', '{{company.name}}'),
                [
                    self::input('source_of_funds', 'Source of Funds'),
                ],
            ),
            self::template(
                '1A-05-Earnest Money Deposit Release Form',
                'other',
                'EARNEST MONEY DEPOSIT RELEASE',
                '<p><strong>Date:</strong> {{input.document_date}}</p>'
                .self::propertySummary()
                .'<p>The parties direct {{title_company.name}} to release the earnest money deposit of {{deal.earnest_money}} according to the written instructions below.</p>'
                .'<p><strong>Release instructions:</strong></p><div class="document-blank">&nbsp;</div>'
                .self::twoPartySignatures('Seller', '{{lead.full_name}}', 'Buyer', '{{company.name}}'),
            ),
            self::template(
                '2D-01-Assignment Contract',
                'assignment_contract',
                'ASSIGNMENT CONTRACT',
                '<p><strong>Effective Date:</strong> {{input.document_date}}</p>'
                .'<p>{{company.name}} (Assignor) assigns its contractual interest in the property below to {{buyer.top_match}} (Assignee).</p>'
                .self::propertySummary()
                .self::terms([
                    'Original Contract Price' => '{{deal.contract_price}}',
                    'Assignment Fee' => '{{deal.assignment_fee}}',
                    'Total Purchase Price' => '{{deal.total_purchase_price}}',
                    'Closing Date' => '{{deal.closing_date}}',
                ])
                .self::twoPartySignatures('Assignor', '{{company.name}}', 'Assignee', '{{buyer.top_match}}'),
            ),
            self::template(
                '3R-01-Independent Contractor Agreement',
                'other',
                'INDEPENDENT CONTRACTOR AGREEMENT',
                '<p><strong>Agreement Date:</strong> {{input.document_date}}</p>'
                .'<p>This agreement is between {{company.name}} (Owner) and {{contractor.name}} (Contractor).</p>'
                .self::propertySummary()
                .self::terms([
                    'Business Name / Entity' => '{{contractor.business_name}}',
                    'Trade / Specialty' => '{{contractor.trade}}',
                    'License Number' => '{{contractor.license_number}}',
                    'Contract Amount' => '{{deal.contract_price}}',
                    'Completion Deadline' => '{{input.completion_deadline}}',
                ])
                .'<p>Contractor will perform the agreed scope of work in a professional manner and comply with applicable laws and insurance requirements.</p>'
                .self::twoPartySignatures('Owner', '{{company.name}}', 'Contractor', '{{contractor.name}}'),
                [
                    self::input('completion_deadline', 'Completion Deadline', 'date'),
                ],
            ),
            self::template(
                '3R-02-Contractor Bid Package',
                'other',
                'CONTRACTOR BID PACKAGE',
                '<p><strong>Bid Requested:</strong> {{input.document_date}}</p>'
                .self::propertySummary()
                .self::terms([
                    'Contractor' => '{{contractor.name}}',
                    'Business Name' => '{{contractor.business_name}}',
                    'License Number' => '{{contractor.license_number}}',
                    'Contact' => '{{contractor.phone}} / {{contractor.email}}',
                ])
                .'<p>Please provide a written scope, material allowance, labor allowance, timeline, warranty, and total bid amount.</p>'
                .'<div class="document-blank document-blank-large">&nbsp;</div>'
                .self::signature('Contractor', '{{contractor.name}}'),
            ),
            self::template(
                '3R-03-Scope of Work',
                'other',
                'SCOPE OF WORK',
                '<p><strong>Date:</strong> {{input.document_date}}</p>'
                .self::propertySummary()
                .self::terms([
                    'Contractor' => '{{contractor.name}}',
                    'Trade / Specialty' => '{{contractor.trade}}',
                    'Project Completion Deadline' => '{{input.completion_deadline}}',
                ])
                .'<p><strong>Work to be completed:</strong></p><div class="document-blank document-blank-large">&nbsp;</div>'
                .self::twoPartySignatures('Owner', '{{company.name}}', 'Contractor', '{{contractor.name}}'),
                [
                    self::input('completion_deadline', 'Completion Deadline', 'date'),
                ],
            ),
            self::template(
                '3R-04-Change Order Form',
                'addendum',
                'CHANGE ORDER FORM',
                '<p><strong>Change Order Date:</strong> {{input.document_date}}</p>'
                .self::propertySummary()
                .self::terms([
                    'Contractor' => '{{contractor.name}}',
                    'Original Contract Date' => '{{deal.contract_date}}',
                    'Original Contract Amount' => '{{deal.contract_price}}',
                    'Additional Cost' => '{{input.additional_cost}}',
                    'Credit / Deduction' => '{{input.credit_deduction}}',
                    'New Completion Date' => '{{input.completion_deadline}}',
                ])
                .'<p><strong>Description of change:</strong></p><div class="document-blank document-blank-large">&nbsp;</div>'
                .self::twoPartySignatures('Owner', '{{company.name}}', 'Contractor', '{{contractor.name}}'),
                [
                    self::input('additional_cost', 'Additional Cost'),
                    self::input('credit_deduction', 'Credit / Deduction'),
                    self::input('completion_deadline', 'New Completion Date', 'date'),
                ],
            ),
            self::template(
                '4C-01-Lien Waiver',
                'other',
                'FINAL LIEN WAIVER',
                '<p><strong>Date:</strong> {{input.document_date}}</p>'
                .'<p>{{contractor.name}} acknowledges receipt of final payment and waives lien rights for the work described below, subject to applicable law.</p>'
                .self::propertySummary()
                .self::terms([
                    'Contract Amount' => '{{deal.contract_price}}',
                    'Final Payment Amount' => '{{input.final_payment_amount}}',
                    'Total Paid to Date' => '{{input.total_paid_to_date}}',
                ])
                .self::signature('Contractor', '{{contractor.name}}'),
                [
                    self::input('final_payment_amount', 'Final Payment Amount'),
                    self::input('total_paid_to_date', 'Total Paid to Date'),
                ],
            ),
            self::template(
                '4C-02-Partial Lien Waiver',
                'other',
                'PARTIAL LIEN WAIVER',
                '<p><strong>Date:</strong> {{input.document_date}}</p>'
                .'<p>{{contractor.name}} acknowledges partial payment for work at the property below and conditionally waives lien rights to the amount received.</p>'
                .self::propertySummary()
                .self::terms([
                    'Payment Amount Received' => '{{input.payment_amount_received}}',
                    'Payment Number' => '{{input.payment_number}}',
                    'Contract Amount' => '{{deal.contract_price}}',
                ])
                .self::signature('Contractor', '{{contractor.name}}'),
                [
                    self::input('payment_amount_received', 'Payment Amount Received'),
                    self::input('payment_number', 'Payment Number'),
                ],
            ),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $extraInputs
     * @return array{name:string,type:string,content:string,merge_fields:array<int,string>,input_fields:array<int,array<string,mixed>>,is_default:bool}
     */
    private static function template(string $name, string $type, string $heading, string $body, array $extraInputs = []): array
    {
        $content = self::layout($heading, $body);
        preg_match_all('/\{\{([a-z_.]+)\}\}/', $content, $matches);

        return [
            'name' => $name,
            'type' => $type,
            'content' => $content,
            'merge_fields' => array_values(array_unique($matches[1] ?? [])),
            'input_fields' => array_merge([
                self::input('document_date', 'Document Date', 'date'),
                self::input('printed_name', 'Printed Name (Bounce Back Realty)'),
            ], $extraInputs),
            'is_default' => $type === 'loi',
        ];
    }

    /** @return array{key:string,label:string,type:string} */
    private static function input(string $key, string $label, string $type = 'text'): array
    {
        return compact('key', 'label', 'type');
    }

    private static function layout(string $heading, string $body): string
    {
        return '<div style="font-family:Arial,sans-serif;max-width:800px;margin:0 auto;padding:36px;color:#1f2937;line-height:1.5;">'
            .'<style>.document-blank{min-height:54px;border:1px solid #9ca3af;padding:10px;margin:8px 0 20px}.document-blank-large{min-height:120px}.document-terms{width:100%;border-collapse:collapse;margin:18px 0}.document-terms td{border:1px solid #d1d5db;padding:9px;vertical-align:top}.document-signature{margin-top:46px;width:46%;display:inline-block;vertical-align:top}.document-signature span{display:block;border-top:1px solid #1f2937;margin-top:42px;padding-top:5px}</style>'
            .'<header style="border-bottom:3px solid #0f766e;margin-bottom:28px;padding-bottom:14px;text-align:center;">'
            .'<h1 style="font-size:22px;margin:0;">{{company.name}}</h1><p style="margin:4px 0 0;color:#4b5563;">{{company.email}} | {{company.phone}}</p></header>'
            .'<h2 style="font-size:20px;text-align:center;margin:0 0 28px;">'.$heading.'</h2>'
            .$body
            .'<p><strong>Prepared by:</strong> {{input.printed_name}}</p>'
            .'<p style="font-size:11px;color:#6b7280;margin-top:34px;">Prepared in InsulaCRM. Review with the appropriate licensed professional before execution.</p>'
            .'</div>';
    }

    private static function propertySummary(): string
    {
        return self::terms([
            'Property Address' => '{{property.full_address}}',
            'Parcel / Legal Description' => '{{property.parcel_id}}',
        ]);
    }

    /** @param array<string, string> $rows */
    private static function terms(array $rows): string
    {
        $html = '<table class="document-terms">';
        foreach ($rows as $label => $value) {
            $html .= '<tr><td style="width:42%;font-weight:bold;">'.$label.'</td><td>'.$value.'</td></tr>';
        }

        return $html.'</table>';
    }

    private static function signature(string $role, string $name): string
    {
        return '<div class="document-signature"><span>'.$name.'</span><strong>'.$role.'</strong><br><small>Signature / Date</small></div>';
    }

    private static function twoPartySignatures(string $firstRole, string $firstName, string $secondRole, string $secondName): string
    {
        return self::signature($firstRole, $firstName).self::signature($secondRole, $secondName);
    }
}
