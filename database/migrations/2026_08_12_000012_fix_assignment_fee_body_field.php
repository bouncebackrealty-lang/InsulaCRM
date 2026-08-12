<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('document_templates')
            ->where('name', '2D-01-Assignment Contract')
            ->orderBy('id')
            ->each(function (object $template): void {
                $content = (string) ($template->content ?? '');
                $content = preg_replace_callback(
                    '/(<td\b[^>]*>\s*(?:<strong>)?Assignment Fee(?:<\/strong>)?\s*<\/td>\s*<td\b[^>]*>)(.*?)(<\/td>)/is',
                    static function (array $match): string {
                        $inner = preg_replace(
                            '/(<span\b[^>]*\binline-line\b[^>]*>).*?(<\/span>)/is',
                            '$1{{deal.assignment_fee}}$2',
                            $match[2],
                            1,
                        ) ?? $match[2];

                        return $match[1].$inner.$match[3];
                    },
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
