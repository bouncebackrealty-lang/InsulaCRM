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

                if (str_contains($content, '{{property.full_address}}')) {
                    return;
                }

                $repaired = preg_replace(
                    [
                        '/(<p><strong>Property Address:<\\/strong>\\s*)(<span\\b[^>]*\\binline-line\\b[^>]*>)\\s*<\\/span>(<\\/p>)/i',
                        '/(<p><strong>Parcel ID \\/ Legal Description:<\\/strong>\\s*)(<span\\b[^>]*\\binline-line\\b[^>]*>)\\s*<\\/span>(<\\/p>)/i',
                        '/(<p><strong>Offer \\/ Purchase Price:<\\/strong>\\s*\\$)(<span\\b[^>]*\\binline-line\\b[^>]*>)\\s*<\\/span>(<\\/p>)/i',
                        '/(<p><strong>Earnest Money Deposit:<\\/strong>\\s*\\$)(<span\\b[^>]*\\binline-line\\b[^>]*>)\\s*<\\/span>/i',
                    ],
                    [
                        '$1$2{{property.full_address}}</span>$3',
                        '$1$2{{property.parcel_id}}</span>$3',
                        '$1$2{{deal.contract_price}}</span>$3',
                        '$1$2{{deal.earnest_money}}</span>',
                    ],
                    $content
                );

                if ($repaired !== $content) {
                    DB::table('document_templates')
                        ->where('id', $template->id)
                        ->update([
                            'content' => $repaired,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {

    }
};
