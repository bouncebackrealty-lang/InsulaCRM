<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('document_templates')
            ->where('name', '4C-02-Partial Lien Waiver')
            ->orderBy('id')
            ->each(function (object $template): void {
                $content = (string) ($template->content ?? '');
                $content = preg_replace(
                    '/(Payment\s*#\s*)(<span\b[^>]*\binline-line\b[^>]*>).*?(<\/span>)/is',
                    '$1$2{{input.payment_number}}$3',
                    $content,
                    1,
                ) ?? $content;

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

    public function down(): void {}
};
