<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('document_templates')
            ->where('name', '3R-04-Change Order Form')
            ->orderBy('id')
            ->each(function (object $template): void {
                $content = preg_replace_callback(
                    '/(<td\b[^>]*>\s*<strong>\s*Property Address\s*<\/strong>\s*<\/td>\s*<td\b[^>]*>)(.*?)(<\/td>)/is',
                    static function (array $match): string {
                        $field = preg_replace_callback(
                            '/style=["\']([^"\']*)["\']/i',
                            static function (array $styleMatch): string {
                                $style = preg_replace('/width\s*:\s*[^;]+;?/i', '', $styleMatch[1]) ?? $styleMatch[1];
                                $style = preg_replace('/font-size\s*:\s*[^;]+;?/i', '', $style) ?? $style;
                                $style = trim($style);

                                return 'style="width: 100%; font-size: 0.62em; letter-spacing: -0.1px; box-sizing: border-box; '.$style.'"';
                            },
                            $match[2],
                            1,
                        ) ?? $match[2];

                        return $match[1].$field.$match[3];
                    },
                    (string) ($template->content ?? ''),
                    1,
                ) ?? (string) ($template->content ?? '');

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
        // Preserve the printable address layout for existing tenant templates.
    }
};
