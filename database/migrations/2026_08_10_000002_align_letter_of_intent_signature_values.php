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
                $content = $this->fixSignatureLineLayout((string) ($template->content ?? ''));

                if ($content === (string) ($template->content ?? '')) {
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
        // Keep the corrected signature-line layout for existing tenants.
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
};
