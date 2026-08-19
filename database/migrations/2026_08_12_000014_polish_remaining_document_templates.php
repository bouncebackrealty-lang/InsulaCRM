<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('document_templates')
            ->whereIn('name', [
                '1A-03-Seller Disclosure',
                '1A-05-Earnest Money Deposit Release Form',
                '2D-01-Assignment Contract',
                '3R-01-Independent Contractor Agreement',
                '3R-02-Contractor Bid Package',
                '3R-03-Scope of Work',
                '3R-04-Change Order Form',
                '4C-01-Lien Waiver',
                '4C-02-Partial Lien Waiver',
            ])
            ->orderBy('id')
            ->each(function (object $template): void {
                $name = (string) $template->name;
                $content = $this->removeDocumentDetails((string) ($template->content ?? ''));

                if ($name === '1A-03-Seller Disclosure') {
                    $content = $this->replaceTableCellValue($content, 'Date', '{{input.document_date}}');
                    $content = $this->fixSignatureLineLayout($content);
                    $content = $this->tightenSignatureSpacing($content);
                }

                if ($name === '1A-05-Earnest Money Deposit Release Form') {
                    $content = $this->replaceTableCellValue($content, 'Date', '{{input.document_date}}');
                    $content = $this->replacePartySignatureValue($content, 'SELLER', 'Printed Name:', '{{lead.full_name}}');
                    $content = $this->replacePartySignatureValue($content, 'SELLER', 'Date:', '{{input.document_date}}');
                    $content = $this->fixSignatureLineLayout($content);
                }

                if ($name === '2D-01-Assignment Contract') {
                    $content = $this->replaceGeneratedLongDate($content);
                    $content = $this->replacePartySignatureValue($content, 'ASSIGNEE', 'Printed Name:', '{{buyer.full_name}}');
                    $content = $this->replacePartySignatureValue($content, 'ASSIGNEE', 'Date:', '{{input.document_date}}');
                    $content = $this->fixSignatureLineLayout($content);
                }

                if ($name === '3R-01-Independent Contractor Agreement') {
                    $content = $this->replaceGeneratedLongDate($content);
                    $content = str_replace('Contractor Name / Business Name', 'Contractor Name', $content);
                    $content = str_replace('Business Entity Type', 'Business Name / Entity', $content);
                    $content = $this->replaceTableCellValue($content, 'Contractor Name', '{{contractor.name}}');
                    $content = $this->replaceTableCellValue($content, 'Business Name / Entity', '{{contractor.business_name}}');
                    $content = $this->replaceTableCellValue($content, 'Principal Office / Mailing Address', '{{contractor.mailing_address}}');
                    $content = $this->widenContractorEmailLine($content);
                    $content = $this->fixSignatureLineLayout($content);
                }

                if ($name === '3R-02-Contractor Bid Package') {
                    $content = preg_replace(
                        '/<strong>\s*Contractor Company Name:\s*<\/strong>\s*(?:\{\{contractor\.name\}\}|_+)/i',
                        '<strong>Business Name:</strong> {{contractor.business_name}}',
                        $content,
                        1,
                    ) ?? $content;
                    $content = preg_replace(
                        '/<strong>\s*License Number:\s*<\/strong>\s*(?:\{\{[^}]+\}\}|_+)/i',
                        '<strong>License Number:</strong> {{contractor.license_number}}',
                        $content,
                        1,
                    ) ?? $content;
                }

                if ($name === '3R-03-Scope of Work') {
                    $content = $this->replaceTableCellValue($content, 'Agreement Date', '{{input.document_date}}');
                    $content = $this->replaceProjectCompletionDeadline($content);
                    $content = $this->fixSignatureLineLayout($content);
                }

                if ($name === '3R-04-Change Order Form') {
                    $content = $this->clearNewCompletionDate($content);
                }

                if ($name === '4C-01-Lien Waiver') {
                    $content = $this->replaceTableCellValue($content, 'Date', '{{input.document_date}}');
                }

                if ($name === '4C-02-Partial Lien Waiver') {
                    $content = str_replace('{{today}}', '{{input.document_date}}', $content);
                }

                DB::table('document_templates')
                    ->where('id', $template->id)
                    ->update([
                        'content' => $content,
                        'merge_fields' => json_encode($this->mergeFields($content)),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        // Preserve corrected generated-document layouts for existing tenants.
    }

    private function removeDocumentDetails(string $content): string
    {
        return preg_replace(
            '/<div\b(?=[^>]*\bdata-document-entry-fields\s*=\s*["\']true["\'])[^>]*>.*?<\/div>/is',
            '',
            $content,
        ) ?? $content;
    }

    private function replaceTableCellValue(string $content, string $label, string $value): string
    {
        $quotedLabel = preg_quote($label, '/');

        return preg_replace_callback(
            '/(<td\b[^>]*>\s*(?:<strong>)?'.$quotedLabel.'(?:<\/strong>)?\s*<\/td>\s*<td\b[^>]*>)(.*?)(<\/td>)/is',
            static function (array $match) use ($value): string {
                $inner = preg_replace(
                    '/(<span\b[^>]*\binline-line\b[^>]*>).*?(<\/span>)/is',
                    '$1'.$value.'$2',
                    $match[2],
                    1,
                ) ?? $match[2];

                return $match[1].$inner.$match[3];
            },
            $content,
            1,
        ) ?? $content;
    }

    private function replacePartySignatureValue(string $content, string $party, string $label, string $value): string
    {
        return preg_replace_callback(
            '/<td\b[^>]*class=["\'][^"\']*\bsignature-cell\b[^"\']*["\'][^>]*>.*?<\/td>/is',
            function (array $match) use ($party, $label, $value): string {
                if (stripos($match[0], $party) === false) {
                    return $match[0];
                }

                $quotedLabel = preg_quote($label, '/');

                return preg_replace(
                    '/(<span\b[^>]*class=["\'][^"\']*\b(?:signature-label|stacked-label)\b[^"\']*["\'][^>]*>\s*'.$quotedLabel.'\s*<\/span>\s*)(<span\b[^>]*class=["\'][^"\']*\b(?:sig-line|stacked-line)\b[^"\']*["\'][^>]*>).*?(<\/span>)/is',
                    '$1$2'.$value.'$3',
                    $match[0],
                    1,
                ) ?? $match[0];
            },
            $content,
        ) ?? $content;
    }

    private function fixSignatureLineLayout(string $content): string
    {
        return preg_replace_callback(
            '/\.sig-line\s*\{([^}]*)\}/is',
            static function (array $match): string {
                $rules = preg_replace('/(?:min-)?height\s*:\s*[^;]+;?/i', '', $match[1]) ?? $match[1];
                $rules = preg_replace('/line-height\s*:\s*[^;]+;?/i', '', $rules) ?? $rules;
                $rules = trim($rules);

                return '.sig-line { '.$rules.' min-height: 18px; line-height: 18px; box-sizing: border-box; }';
            },
            $content,
            1,
        ) ?? $content;
    }

    private function tightenSignatureSpacing(string $content): string
    {
        return preg_replace(
            '/(\.signature-field\s*\{[^}]*?)margin-top\s*:\s*\d+px;/is',
            '$1margin-top: 8px;',
            $content,
            1,
        ) ?? $content;
    }

    private function replaceGeneratedLongDate(string $content): string
    {
        return preg_replace(
            '/<span\b([^>]*\binline-line\b[^>]*)>\s*\{\{today_month_day\}\}\s*<\/span>\s*,\s*<span\b[^>]*\binline-line\b[^>]*>\s*\{\{today_year\}\}\s*<\/span>/is',
            '<span$1>{{input.document_date_long}}</span>',
            $content,
            1,
        ) ?? $content;
    }

    private function widenContractorEmailLine(string $content): string
    {
        $content = str_replace(
            'Phone: <span class="inline-line" style="width: 35%;">',
            'Phone: <span class="inline-line" style="width: 26%;">',
            $content,
        );

        return str_replace(
            'Email: <span class="inline-line" style="width: 35%;">',
            'Email: <span class="inline-line" style="width: 44%;">',
            $content,
        );
    }

    private function replaceProjectCompletionDeadline(string $content): string
    {
        return preg_replace(
            '/(<strong>\s*Project Completion Deadline:\s*<\/strong>\s*)<span\b[^>]*\binline-line\b[^>]*>.*?<\/span>\s*,\s*20<span\b[^>]*\binline-line\b[^>]*>.*?<\/span>/is',
            '$1<span class="inline-line" style="width: 240px;">{{input.completion_deadline}}</span>',
            $content,
            1,
        ) ?? $content;
    }

    private function clearNewCompletionDate(string $content): string
    {
        return preg_replace_callback(
            '/(<td\b[^>]*>\s*<strong>\s*New Completion Date\s*<\/strong>\s*<\/td>\s*<td\b[^>]*>)(.*?)(<\/td>)/is',
            static function (array $match): string {
                $field = preg_replace(
                    '/\s+value=["\']\{\{input\.document_date\}\}["\']/i',
                    '',
                    $match[2],
                    1,
                ) ?? $match[2];

                return $match[1].$field.$match[3];
            },
            $content,
            1,
        ) ?? $content;
    }

    /**
     * @return array<int, string>
     */
    private function mergeFields(string $content): array
    {
        preg_match_all('/\{\{([a-z_.]+)\}\}/', $content, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }
};
