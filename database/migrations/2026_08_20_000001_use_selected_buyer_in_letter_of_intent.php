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
                $content = (string) $template->content;

                // This is the contract-party field in Section 3. Keep the
                // company merge fields elsewhere in the document untouched.
                $updated = preg_replace(
                    '/(<p\b[^>]*>\s*<strong>\s*Buyer\s*:\s*<\/strong>\s*)\{\{company\.name\}\}(\s*<\/p>)/is',
                    '$1{{buyer.top_match}}$2',
                    $content,
                    1,
                ) ?? $content;

                if ($updated === $content) {
                    return;
                }

                preg_match_all('/\{\{([a-z_.]+)\}\}/', $updated, $matches);

                DB::table('document_templates')
                    ->where('id', $template->id)
                    ->update([
                        'content' => $updated,
                        'merge_fields' => json_encode(array_values(array_unique($matches[1] ?? []))),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        // Generated documents are immutable snapshots; leave them intact.
    }
};
