<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('document_templates')
            ->where('name', '3R-01-Independent Contractor Agreement')
            ->orderBy('id')
            ->each(function (object $template): void {
                $content = (string) ($template->content ?? '');
                $updated = preg_replace(
                    '/(<tr>\s*<td\b[^>]*>\s*<strong>\s*Phone\s*(?:&|&amp;)\s*Email\s*<\/strong>\s*<\/td>\s*)<td\b[^>]*>.*?<\/td>(\s*<\/tr>)/is',
                    '$1<td style="white-space: nowrap;">'
                        .'Phone: <span class="inline-line" style="width: 24%; white-space: nowrap;">{{contractor.phone}}</span> '
                        .'Email: <span class="inline-line" style="width: 48%; white-space: nowrap;">{{contractor.email}}</span>'
                        .'</td>$2',
                    $content,
                    1,
                ) ?? $content;

                if ($updated === $content) {
                    return;
                }

                DB::table('document_templates')
                    ->where('id', $template->id)
                    ->update([
                        'content' => $updated,
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {

    }
};
