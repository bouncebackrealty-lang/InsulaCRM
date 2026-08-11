<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('document_templates')
            ->where('name', '1A-03-Seller Disclosure')
            ->orderBy('id')
            ->each(function (object $template): void {
                $original = (string) ($template->content ?? '');
                $content = $this->fitFooter($original);

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

    public function down(): void
    {
        // Keep the compatible footer layout for existing tenants.
    }

    private function fitFooter(string $content): string
    {
        $content = preg_replace(
            '/(\.footer\s*\{[^}]*?)\s*margin-top:\s*10px;/is',
            '$1 margin-top: 0; page-break-inside: avoid; break-inside: avoid;',
            $content,
            1,
        ) ?? $content;

        $content = preg_replace(
            '/(\.footer\s+\.divider-line\s*\{[^}]*?)(\})/is',
            '$1 margin: 5px 0 8px 0;$2',
            $content,
            1,
        ) ?? $content;

        $content = preg_replace(
            '/(\.footer\s+\.footer-text\s*\{[^}]*?)(\})/is',
            '$1 margin: 4px 0;$2',
            $content,
            1,
        ) ?? $content;

        return $content;
    }
};
