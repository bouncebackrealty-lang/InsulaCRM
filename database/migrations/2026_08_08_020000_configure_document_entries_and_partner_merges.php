<?php

use App\Models\DocumentTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $templates = DocumentTemplate::withoutGlobalScopes()->get();

        foreach ($templates as $template) {
            $name = strtolower($template->name);
            $fields = [
                ['key' => 'printed_name', 'label' => 'Printed Name (Bounce Back Realty)', 'type' => 'text'],
                ['key' => 'document_date', 'label' => 'Document Date', 'type' => 'date'],
            ];
            $entries = '';

            if (str_contains($name, 'letter of intent')) {
                $fields[] = ['key' => 'closing_attorney', 'label' => 'Closing Attorney / Title Company', 'type' => 'text'];
                $entries .= $this->entry('Closing Attorney / Title Company', '{{title_company.closing_attorney}} {{title_company.name}}');
            }
            if (str_contains($name, 'purchase agreement')) {
                $entries .= $this->entry('Title Company', '{{title_company.name}}') . $this->entry('Title Company Address', '{{title_company.full_address}}');
            }
            if (str_contains($name, 'independent contractor')) {
                $fields[] = ['key' => 'contractor_ein_ssn', 'label' => 'Contractor EIN / SSN', 'type' => 'text'];
                $entries .= $this->entry('Contractor EIN / SSN', '{{input.contractor_ein_ssn}}');
                // Some versions of this template used a static "20" before a four-digit year.
                $template->content = preg_replace('/\b20\s*(\{\{(?:today_year|deal\.closing_year)\}\})/', '$1', $template->content);
                $template->content = preg_replace('/20(\s*<span\b[^>]*>\s*\{\{(?:today_year|deal\.closing_year)\}\}\s*<\/span>)/i', '$1', $template->content);
            }
            if (str_contains($name, 'assignment')) {
                $entries .= $this->entry('Assignee Address', '{{buyer.full_address}}')
                    . $this->entry('Title Company / Closing Attorney', '{{title_company.name}} {{title_company.closing_attorney}}');
            }
            if (str_contains($name, 'final lien waiver')) {
                $fields[] = ['key' => 'final_payment_amount', 'label' => 'Final Payment Amount', 'type' => 'text'];
                $fields[] = ['key' => 'total_paid_to_date', 'label' => 'Total Paid to Date', 'type' => 'text'];
                $entries .= $this->entry('Final Payment Amount', '{{input.final_payment_amount}}') . $this->entry('Total Paid to Date', '{{input.total_paid_to_date}}');
            }
            if (str_contains($name, 'partial lien waiver')) {
                $fields[] = ['key' => 'payment_amount_received', 'label' => 'Payment Amount Received', 'type' => 'text'];
                $fields[] = ['key' => 'payment_number', 'label' => 'Payment Number', 'type' => 'text'];
                $entries .= $this->entry('Payment Amount Received', '{{input.payment_amount_received}}') . $this->entry('Payment Number', '{{input.payment_number}}');
            }
            if (str_contains($name, 'scope of work')) {
                $fields[] = ['key' => 'completion_deadline', 'label' => 'Completion Deadline', 'type' => 'date'];
                $entries .= $this->entry('Completion Deadline', '{{input.completion_deadline}}');
            }
            if (str_contains($name, 'proof of funds')) {
                $fields[] = ['key' => 'source_of_funds', 'label' => 'Source of Funds', 'type' => 'radio', 'options' => ['Cash on Hand', 'Hard Money / Private Lender', 'Bank Financing', 'Investor / Partner Funds', 'Other']];
                $template->content = str_replace('{{company.name}}', '{{lender.name}}', $template->content);
                $entries .= $this->entry('Lender / Source Name', '{{lender.name}}') . $this->entry('Source of Funds', '{{input.source_of_funds}}');
            }

            if ($entries !== '' && !str_contains($template->content, 'data-document-entry-fields')) {
                $template->content .= '<div data-document-entry-fields="true" style="margin-top:24px; page-break-inside:avoid; border-top:1px solid #bbb; padding-top:10px;">'
                    . '<p><strong>DOCUMENT DETAILS</strong></p>' . $entries
                    . $this->entry('Printed Name', '{{input.printed_name}}')
                    . $this->entry('Date', '{{input.document_date}}')
                    . '</div>';
            }

            if (str_contains($name, 'emd release addendum')) {
                $template->type = 'addendum';
            }
            preg_match_all('/\{\{([a-z_.]+)\}\}/', $template->content, $matches);
            $template->merge_fields = array_values(array_unique($matches[1] ?? []));
            $template->input_fields = $fields;
            $template->save();
        }
    }

    private function entry(string $label, string $value): string
    {
        return '<p style="margin:6px 0;"><strong>' . e($label) . ':</strong> <span style="display:inline-block; min-width:220px; border-bottom:1px solid #222;">' . $value . '</span></p>';
    }

    public function down(): void
    {
        DocumentTemplate::withoutGlobalScopes()->get()->each(function (DocumentTemplate $template) {
            $template->input_fields = null;
            $template->content = preg_replace('/<div data-document-entry-fields="true".*?<\/div>/s', '', $template->content);
            $template->save();
        });
    }
};
