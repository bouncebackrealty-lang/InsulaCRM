<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('document_templates')->where('name', '3R-01-Independent Contractor Agreement')->orderBy('id')->each(function (object $template) {
            $content = preg_replace('/\b20\s*(\{\{(?:today_year|deal\.closing_year)\}\})/', '$1', $template->content);
            $content = preg_replace('/20(\s*<span\b[^>]*>\s*\{\{(?:today_year|deal\.closing_year)\}\}\s*<\/span>)/i', '$1', $content);
            DB::table('document_templates')->where('id', $template->id)->update(['content' => $content, 'updated_at' => now()]);
        });
    }

    public function down(): void {}
};
