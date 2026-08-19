<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{

    public function up(): void
    {
        DB::table('document_templates')->orderBy('id')->each(function ($template) {
            preg_match_all('/\{\{([a-z_.]+)\}\}/', $template->content ?? '', $matches);

            DB::table('document_templates')->where('id', $template->id)->update([
                'merge_fields' => json_encode(array_values(array_unique($matches[1] ?? []))),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {

    }
};
