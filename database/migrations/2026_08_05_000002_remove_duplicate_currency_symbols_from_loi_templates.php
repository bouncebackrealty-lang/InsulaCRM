<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('document_templates')
            ->where('type', 'loi')
            ->orderBy('id')
            ->each(function (object $template): void {
                $content = (string) $template->content;


                $corrected = preg_replace(
                    [
                        '/(Offer \/ Purchase Price:<\/strong>\s*)\$(\s*<span\b[^>]*>\s*\{\{deal\.contract_price\}\})/i',
                        '/(Earnest Money Deposit:<\/strong>\s*)\$(\s*<span\b[^>]*>\s*\{\{deal\.earnest_money\}\})/i',
                        '/(Offer \/ Purchase Price:<\/strong>\s*)\$(\s*\{\{deal\.contract_price\}\})/i',
                        '/(Earnest Money Deposit:<\/strong>\s*)\$(\s*\{\{deal\.earnest_money\}\})/i',
                    ],
                    [
                        '$1$2',
                        '$1$2',
                        '$1$2',
                        '$1$2',
                    ],
                    $content
                );

                if ($corrected !== $content) {
                    DB::table('document_templates')
                        ->where('id', $template->id)
                        ->update([
                            'content' => $corrected,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {

    }
};
