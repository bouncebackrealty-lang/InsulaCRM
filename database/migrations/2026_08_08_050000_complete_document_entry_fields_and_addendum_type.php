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

            $ensureField = static function (string $key, string $label, string $type = 'text') use ($fields): void {
                if (! $fields->contains('key', $key)) {
                    $fields->push(['key' => $key, 'label' => $label, 'type' => $type]);
                }
            };


            $ensureField('printed_name', 'Printed Name (Bounce Back Realty)');
            $ensureField('document_date', 'Document Date', 'date');

            $entries = '';
            if (str_contains($name, 'lien waiver') && !str_contains($name, 'partial')) {
                $ensureField('final_payment_amount', 'Final Payment Amount');
                $ensureField('total_paid_to_date', 'Total Paid to Date');
                $entries .= $this->entry('Final Payment Amount', '{{input.final_payment_amount}}')
                    . $this->entry('Total Paid to Date', '{{input.total_paid_to_date}}');
            }


            if (str_contains($name, 'earnest money deposit release')) {
                $template->type = 'addendum';
            }

            if ($entries !== '' && !str_contains($template->content, 'data-document-entry-fields')) {
                $template->content .= $this->entryBlock($entries);
            }

            $template->input_fields = $fields->values()->all();
            preg_match_all('/\{\{([a-z_.]+)\}\}/', $template->content, $matches);
            $template->merge_fields = array_values(array_unique($matches[1] ?? []));
            $template->save();
        });
    }

    private function entryBlock(string $entries): string
    {
        return '<div data-document-entry-fields="true" style="margin-top:24px; page-break-inside:avoid; border-top:1px solid #bbb; padding-top:10px;">'
            . '<p><strong>DOCUMENT DETAILS</strong></p>' . $entries
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
        DocumentTemplate::withoutGlobalScopes()
            ->where('name', 'like', '%Earnest Money Deposit Release%')
            ->update(['type' => 'other']);
    }
};
