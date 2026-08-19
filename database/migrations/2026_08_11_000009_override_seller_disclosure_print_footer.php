<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('document_templates')
            ->where('name', '1A-03-Seller Disclosure')
            ->orderBy('id')
            ->each(function (object $template): void {
                $original = (string) ($template->content ?? '');

                if (str_contains($original, 'seller-disclosure-print-footer-fix')) {
                    return;
                }

                $styles = <<<'CSS'
<style media="print" id="seller-disclosure-print-footer-fix">
  body .document-content .footer {
    margin-top: 0 !important;
    page-break-inside: avoid;
    break-inside: avoid;
  }
  body .document-content .footer .divider-line {
    margin: 5px 0 8px 0 !important;
  }
  body .document-content .footer .footer-text {
    margin: 4px 0 !important;
  }
</style>
CSS;

                $content = preg_replace(
                    '/<\/head>/i',
                    $styles . '</head>',
                    $original,
                    1,
                ) ?? $original;

                if ($content === $original) {
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
        // Keep the compatible print footer for existing tenants.
    }
};
