<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('document_templates')
            ->where('name', '1A-04-Proof of Funds')
            ->orderBy('id')
            ->each(function (object $template): void {
                $content = (string) ($template->content ?? '');
                $content = $this->removeDocumentDetails($content);
                $content = $this->replaceSourceOfFundsCheckboxes($content);
                $content = $this->replacePrintedName($content);
                $content = $this->replaceSignatureField($content, 'Date:', '{{input.document_date}}');
                $content = $this->fixSignatureLineLayout($content);

                $inputFields = json_decode($template->input_fields ?: '[]', true) ?: [];
                $this->ensureInputField($inputFields, 'printed_name', 'Printed Name (Bounce Back Realty)', 'text');
                $this->ensureInputField($inputFields, 'document_date', 'Document Date', 'date');
                $this->ensureInputField($inputFields, 'source_of_funds', 'Source of Funds', 'radio');

                preg_match_all('/\{\{([a-z_.]+)\}\}/', $content, $matches);

                DB::table('document_templates')
                    ->where('id', $template->id)
                    ->update([
                        'content' => $content,
                        'merge_fields' => json_encode(array_values(array_unique($matches[1] ?? []))),
                        'input_fields' => json_encode(array_values($inputFields)),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        // Keep the corrected template content for existing tenants.
    }

    private function removeDocumentDetails(string $content): string
    {
        return preg_replace(
            '/<div\b(?=[^>]*\bdata-document-entry-fields\s*=\s*["\']true["\'])[^>]*>.*?<\/div>/is',
            '',
            $content,
        ) ?? $content;
    }

    private function replaceSourceOfFundsCheckboxes(string $content): string
    {
        return str_replace(
            [
                '☐ Cash on Hand',
                '☐ Hard Money / Private Lender',
                '☐ Investor Partner',
                '☐ Combination',
            ],
            [
                '{{input.source_of_funds_cash_on_hand}} Cash on Hand',
                '{{input.source_of_funds_hard_money}} Hard Money / Private Lender',
                '{{input.source_of_funds_investor_partner}} Investor Partner',
                '{{input.source_of_funds_combination}} Combination',
            ],
            $content,
        );
    }

    private function replacePrintedName(string $content): string
    {
        return preg_replace_callback(
            '/(<span\b[^>]*class=["\'][^"\']*\bsignature-label\b[^"\']*["\'][^>]*>\s*Printed Name:\s*<\/span>\s*)(<span\b[^>]*>).*?(<\/span>)/is',
            static fn (array $match): string => $match[1] . $match[2] . '{{input.printed_name}}' . $match[3],
            $content,
            1,
        ) ?? $content;
    }

    private function replaceSignatureField(string $content, string $label, string $value): string
    {
        $quotedLabel = preg_quote($label, '/');

        return preg_replace(
            '/(<span\b[^>]*class=["\'][^"\']*\bsignature-label\b[^"\']*["\'][^>]*>\s*' . $quotedLabel . '\s*<\/span>\s*<span\b[^>]*class=["\'][^"\']*\bsig-line\b[^"\']*["\'][^>]*>).*?(<\/span>)/is',
            '$1' . $value . '$2',
            $content,
            1,
        ) ?? $content;
    }

    private function fixSignatureLineLayout(string $content): string
    {
        return preg_replace(
            '/(\.sig-line\s*\{[^}]*?)\s*height:\s*1px;(?=[^}]*\})/is',
            '$1 min-height: 18px; line-height: 18px;',
            $content,
            1,
        ) ?? $content;
    }

    private function ensureInputField(array &$fields, string $key, string $label, string $type): void
    {
        foreach ($fields as $field) {
            if (($field['key'] ?? null) === $key) {
                return;
            }
        }

        $fields[] = ['key' => $key, 'label' => $label, 'type' => $type];
    }
};
