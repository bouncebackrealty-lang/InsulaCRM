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
            ->each(function ($template): void {
                $content = (string) ($template->content ?? '');

                if (! str_contains($content, '{{buyer.top_match}}')) {
                    $content = str_replace(
                        '<p><strong>Buyer:</strong> BOUNCE BACK REALTY and/or its assigns</p>',
                        '<p><strong>Buyer:</strong> {{buyer.top_match}}</p>',
                        $content,
                    );
                }

                if ($content === $template->content) {
                    return;
                }

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
        DB::table('document_templates')
            ->where('name', '1A-01-Letter of Intent')
            ->orderBy('id')
            ->each(function ($template): void {
                $content = str_replace(
                    '{{buyer.top_match}}',
                    'BOUNCE BACK REALTY and/or its assigns',
                    (string) ($template->content ?? ''),
                );

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
};
