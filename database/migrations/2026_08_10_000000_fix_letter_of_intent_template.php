<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('document_templates')
            ->where('name', '1A-01-Letter of Intent')
            ->orderBy('id')
            ->each(function (object $template): void {
                $content = (string) ($template->content ?? '');


                $content = $this->removeDocumentDetails($content);
                $content = $this->replaceInlineField($content, 'Closing Attorney / Title Company', '{{input.closing_attorney}}');
                $content = $this->fixSignatureLineLayout($content);

                $content = str_replace('{{buyer.top_match}}', '{{company.name}}', $content);

                $content = preg_replace_callback(
                    '/(<td\b[^>]*>(?:(?!<\/td>).)*?<strong>\s*BUYER:\s*<\/strong>(?:(?!<\/td>).)*?<\/td>)/is',
                    function (array $match): string {
                        $buyerSignature = $this->replaceSignatureField($match[1], 'Printed Name:', '{{input.printed_name}}');

                        return $this->replaceSignatureField($buyerSignature, 'Date:', '{{input.document_date}}');
                    },
                    $content,
                    1,
                ) ?? $content;

                $inputFields = json_decode($template->input_fields ?: '[]', true) ?: [];
                $this->ensureInputField($inputFields, 'printed_name', 'Printed Name (Bounce Back Realty)', 'text');
                $this->ensureInputField($inputFields, 'document_date', 'Document Date', 'date');
                $this->ensureInputField($inputFields, 'closing_attorney', 'Closing Attorney / Title Company', 'text');

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

    private function replaceInlineField(string $content, string $label, string $value): string
    {
        $quotedLabel = preg_quote($label, '/');

        return preg_replace(
            '/(<strong>\s*' . $quotedLabel . '\s*:\s*<\/strong>\s*<span\b[^>]*>).*?(<\/span>)/is',
            '$1' . $value . '$2',
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
