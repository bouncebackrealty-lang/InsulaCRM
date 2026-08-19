<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('document_templates')
            ->orderBy('id')
            ->each(function (object $template): void {
                $content = (string) ($template->content ?? '');
                $updated = $this->markDirectTagline($content);

                if ($template->name === '3R-04-Change Order Form') {
                    $updated = $this->repairChangeOrderProjectFields($updated);
                }

                if ($template->name === '1A-03-Seller Disclosure') {
                    $updated = $this->markSellerDisclosure($updated);
                }

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

    private function repairChangeOrderProjectFields(string $content): string
    {
        $replacement = <<<'HTML'
  <tr>
    <td style="width: 25%; background-color: #f2f2f2;"><strong>Date</strong></td>
    <td colspan="3"><input type="text" style="width: 220px; max-width: 100%; border: none; font-size: 1em; box-sizing: border-box;" placeholder="MM/DD/YYYY" value="{{input.document_date}}"></td>
  </tr>
  <tr>
    <td style="width: 25%; background-color: #f2f2f2;"><strong>Property Address</strong></td>
    <td colspan="3"><input type="text" style="display: block; width: 100%; min-width: 0; border: none; font-size: 1em; letter-spacing: normal; box-sizing: border-box;" placeholder="Full Address" value="{{property.full_address}}"></td>
  </tr>
HTML;

        $updated = preg_replace(
            '/<tr>\s*<td\b[^>]*>\s*<strong>\s*Date\s*<\/strong>\s*<\/td>.*?<strong>\s*Property Address\s*<\/strong>.*?<\/tr>/is',
            $replacement,
            $content,
            1,
        ) ?? $content;

        if ($updated !== $content) {
            return $updated;
        }


        return preg_replace_callback(
            '/(<strong>\s*Property Address\s*<\/strong>\s*<\/td>\s*<td\b[^>]*>\s*)(<input\b[^>]*>)/is',
            function (array $match): string {
                $input = preg_replace('/\sstyle=("[^"]*"|\'[^\']*\')/i', '', $match[2], 1) ?? $match[2];
                $input = preg_replace(
                    '/\s*\/?>(?=\s*$)/',
                    ' style="display: block; width: 100%; min-width: 0; border: none; font-size: 1em; letter-spacing: normal; box-sizing: border-box;">',
                    $input,
                    1,
                ) ?? $input;

                return $match[1].$input;
            },
            $content,
            1,
        ) ?? $content;
    }

    private function markDirectTagline(string $content): string
    {
        return preg_replace_callback(
            '/<p\b[^>]*>\s*(?:<[^>]+>\s*)*Every\s+Move\s+Starts\s+with\s+Strategy(?:\s*(?:™|&trade;))?(?:\s*<\/[^>]+>)*\s*<\/p>/is',
            function (array $match): string {
                if (preg_match('/\bclass\s*=\s*(["\'])(.*?)\1/is', $match[0], $classMatch)) {
                    if (preg_match('/(?:^|\s)document-tagline(?:\s|$)/', $classMatch[2])) {
                        return $match[0];
                    }

                    return preg_replace(
                        '/\bclass\s*=\s*(["\'])(.*?)\1/is',
                        'class=$1$2 document-tagline$1',
                        $match[0],
                        1,
                    ) ?? $match[0];
                }

                return preg_replace('/^<p\b/i', '<p class="document-tagline"', $match[0], 1) ?? $match[0];
            },
            $content,
        ) ?? $content;
    }

    private function markSellerDisclosure(string $content): string
    {
        return preg_replace_callback(
            '/<div\b([^>]*\bclass\s*=\s*(["\'])([^"\']*\bdocument-container\b[^"\']*)\2[^>]*)>/i',
            static function (array $match): string {
                if (preg_match('/(?:^|\s)seller-disclosure-document(?:\s|$)/', $match[3])) {
                    return $match[0];
                }

                $attributes = preg_replace(
                    '/\bclass\s*=\s*(["\'])([^"\']*)\1/i',
                    'class=$1$2 seller-disclosure-document$1',
                    $match[1],
                    1,
                ) ?? $match[1];

                return '<div'.$attributes.'>';
            },
            $content,
            1,
        ) ?? $content;
    }
};
