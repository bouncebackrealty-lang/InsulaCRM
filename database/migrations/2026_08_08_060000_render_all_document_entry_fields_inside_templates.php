<?php

use App\Models\DocumentTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{

    public function up(): void
    {
        DocumentTemplate::withoutGlobalScopes()->get()->each(function (DocumentTemplate $template) {
            $name = strtolower($template->name);
            $fields = collect($template->input_fields ?? []);

            $ensure = static function (string $key, string $label, string $type = 'text', ?array $options = null) use ($fields): void {
                if ($fields->contains('key', $key)) {
                    return;
                }

                $field = ['key' => $key, 'label' => $label, 'type' => $type];
                if ($options !== null) {
                    $field['options'] = $options;
                }
                $fields->push($field);
            };

            $ensure('printed_name', 'Printed Name (Bounce Back Realty)');
            $ensure('document_date', 'Document Date', 'date');

            $details = '';
            if (str_contains($name, 'letter of intent')) {
                $ensure('closing_attorney', 'Closing Attorney / Title Company');
                $details .= $this->entry('Closing Attorney / Title Company', '{{input.closing_attorney}}');
            }
            if (str_contains($name, 'purchase agreement')) {
                $details .= $this->entry('Title Company', '{{title_company.name}}')
                    . $this->entry('Title Company Address', '{{title_company.full_address}}');
            }
            if (str_contains($name, 'independent contractor')) {
                $ensure('contractor_ein_ssn', 'Contractor EIN / SSN');
                $details .= $this->entry('Contractor EIN / SSN', '{{input.contractor_ein_ssn}}');
            }
            if (str_contains($name, 'assignment')) {
                $details .= $this->entry('Assignee Address', '{{buyer.full_address}}')
                    . $this->entry('Title Company / Closing Attorney', '{{title_company.name}} {{title_company.closing_attorney}}');
            }
            if (str_contains($name, 'lien waiver') && ! str_contains($name, 'partial')) {
                $ensure('final_payment_amount', 'Final Payment Amount');
                $ensure('total_paid_to_date', 'Total Paid to Date');
                $details .= $this->entry('Final Payment Amount', '{{input.final_payment_amount}}')
                    . $this->entry('Total Paid to Date', '{{input.total_paid_to_date}}');
            }
            if (str_contains($name, 'partial lien waiver')) {
                $ensure('payment_amount_received', 'Payment Amount Received');
                $ensure('payment_number', 'Payment Number');
                $details .= $this->entry('Payment Amount Received', '{{input.payment_amount_received}}')
                    . $this->entry('Payment Number', '{{input.payment_number}}');
            }
            if (str_contains($name, 'scope of work')) {
                $ensure('completion_deadline', 'Completion Deadline', 'date');
                $details .= $this->entry('Completion Deadline', '{{input.completion_deadline}}');
            }
            if (str_contains($name, 'proof of funds')) {
                $ensure('source_of_funds', 'Source of Funds', 'radio', [
                    'Cash on Hand', 'Hard Money / Private Lender', 'Bank Financing', 'Investor / Partner Funds', 'Other',
                ]);
                $details .= $this->entry('Lender / Source Name', '{{lender.name}}')
                    . $this->entry('Source of Funds', '{{input.source_of_funds}}');
            }

            $content = preg_replace('/<div\s+data-document-entry-fields="true"[^>]*>.*?<\/div>/is', '', $template->content) ?? $template->content;
            $entryBlock = $this->entryBlock($details);

            if (stripos($content, '</body>') !== false) {
                $content = preg_replace('/<\/body>/i', $entryBlock . '</body>', $content, 1) ?? $content . $entryBlock;
            } else {
                $content .= $entryBlock;
            }


            $template->content = $content;
            $template->input_fields = $fields->values()->all();
            preg_match_all('/\{\{([a-z_.]+)\}\}/', $content, $matches);
            $template->merge_fields = array_values(array_unique($matches[1] ?? []));
            $template->save();
        });
    }

    private function entryBlock(string $details): string
    {
        return '<div data-document-entry-fields="true" style="margin-top:24px; page-break-inside:avoid; border-top:1px solid #bbb; padding-top:10px;">'
            . '<p><strong>DOCUMENT DETAILS</strong></p>'
            . $details
            . $this->entry('Printed Name', '{{input.printed_name}}')
            . $this->entry('Date', '{{input.document_date}}')
            . '</div>';
    }

    private function entry(string $label, string $value): string
    {
        return '<p style="margin:6px 0;"><strong>' . e($label) . ':</strong> <span style="display:inline-block; min-width:220px; border-bottom:1px solid #222;">' . $value . '</span></p>';
    }

    public function down(): void
    {

    }
};
