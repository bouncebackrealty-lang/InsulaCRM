<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->restoreLetterOfIntentLayout();
        $this->restoreSellerDisclosureLayout();
    }

    public function down(): void
    {
        // Keep the compatible document layouts for existing tenants.
    }

    private function restoreLetterOfIntentLayout(): void
    {
        DB::table('document_templates')
            ->where('name', '1A-01-Letter of Intent')
            ->orderBy('id')
            ->each(function (object $template): void {
                $original = (string) ($template->content ?? '');
                $content = $this->restoreInlineClosingAttorneyField($original);
                $content = $this->removeClosingAttorneyStyles($content);

                if ($content === $original) {
                    return;
                }

                DB::table('document_templates')
                    ->where('id', $template->id)
                    ->update([
                        'content' => $content,
                        'updated_at' => now(),
                    ]);
            });
    }

    private function restoreSellerDisclosureLayout(): void
    {
        DB::table('document_templates')
            ->where('name', '1A-03-Seller Disclosure')
            ->orderBy('id')
            ->each(function (object $template): void {
                $original = (string) ($template->content ?? '');
                $content = $this->restoreSignatureLineHeight($original);
                $content = $this->tightenFooter($content);

                if ($content === $original) {
                    return;
                }

                DB::table('document_templates')
                    ->where('id', $template->id)
                    ->update([
                        'content' => $content,
                        'updated_at' => now(),
                    ]);
            });
    }

    private function restoreInlineClosingAttorneyField(string $content): string
    {
        return preg_replace_callback(
            '/<div\b[^>]*class=["\'][^"\']*\bdocument-field\b[^"\']*["\'][^>]*>\s*<strong\b[^>]*>\s*Closing Attorney\s*\/\s*Title Company\s*:\s*<\/strong>\s*<span\b[^>]*class=["\'][^"\']*\bdocument-field-value\b[^"\']*["\'][^>]*>(.*?)<\/span>\s*<\/div>/is',
            static fn (array $match): string => '<p><strong>Closing Attorney / Title Company:</strong> <span class="inline-line" style="width: 50%;">' . trim($match[1]) . '</span></p>',
            $content,
            1,
        ) ?? $content;
    }

    private function removeClosingAttorneyStyles(string $content): string
    {
        return preg_replace(
            '/\s*\.document-field\s*\{[^}]*\}\s*\.document-field-label\s*\{[^}]*\}\s*\.document-field-value\s*\{[^}]*\}/is',
            '',
            $content,
            1,
        ) ?? $content;
    }

    private function restoreSignatureLineHeight(string $content): string
    {
        return preg_replace(
            '/(\.sig-line\s*\{[^}]*?)\s*min-height:\s*18px;\s*line-height:\s*18px;(?=[^}]*\})/is',
            '$1 height: 1px;',
            $content,
            1,
        ) ?? $content;
    }

    private function tightenFooter(string $content): string
    {
        return preg_replace(
            '/(\.footer\s*\{[^}]*?)\s*margin-top:\s*40px;/is',
            '$1 margin-top: 10px; page-break-inside: avoid; break-inside: avoid;',
            $content,
            1,
        ) ?? $content;
    }
};
