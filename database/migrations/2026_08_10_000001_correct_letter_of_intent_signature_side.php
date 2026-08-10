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
                $content = preg_replace_callback(
                    '/(<td\b[^>]*class=["\'][^"\']*\bsignature-cell\b[^"\']*["\'][^>]*>.*?<\/td>)/is',
                    function (array $match): string {
                        $cell = $match[1];
                        $isBuyer = preg_match('/<strong>\s*BUYER:\s*<\/strong>/i', $cell) === 1;

                        $cell = $this->replaceSignatureField(
                            $cell,
                            'Printed Name:',
                            $isBuyer ? '{{input.printed_name}}' : '',
                        );

                        return $this->replaceSignatureField(
                            $cell,
                            'Date:',
                            $isBuyer ? '{{input.document_date}}' : '',
                        );
                    },
                    (string) ($template->content ?? ''),
                ) ?? (string) ($template->content ?? '');

                preg_match_all('/\{\{([a-z_.]+)\}\}/', $content, $matches);

                DB::table('document_templates')
                    ->where('id', $template->id)
                    ->update([
                        'content' => $content,
                        'merge_fields' => json_encode(array_values(array_unique($matches[1] ?? []))),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        // Keep the corrected signature placement for existing tenants.
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
};
