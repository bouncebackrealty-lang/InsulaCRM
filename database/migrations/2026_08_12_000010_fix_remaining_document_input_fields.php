<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('document_templates')
            ->whereIn('name', [
                '2D-01-Assignment Contract',
                '3R-01-Independent Contractor Agreement',
                '3R-03-Scope of Work',
                '3R-02-Contractor Bid Package',
                '4C-01-Lien Waiver',
                '1A-05-Earnest Money Deposit Release Form',
                '4C-02-Partial Lien Waiver',
                '3R-04-Change Order Form',
            ])
            ->orderBy('id')
            ->each(function (object $template): void {
                $name = (string) $template->name;
                $content = $this->removeDocumentDetails((string) ($template->content ?? ''));
                $inputFields = json_decode($template->input_fields ?: '[]', true) ?: [];

                if ($name === '2D-01-Assignment Contract') {
                    $content = $this->replaceTableCellValue($content, 'Assignee Address', '{{buyer.full_address}}');
                    $content = $this->replaceTableCellValue($content, 'Assignment Fee', '{{deal.assignment_fee}}');
                    $content = $this->replaceTableCellValue($content, 'Title Company / Closing Attorney', '{{title_company.name}} {{title_company.closing_attorney}}');
                    $content = $this->replacePartySignatureValue($content, 'ASSIGNOR', 'Printed Name:', '{{input.printed_name}}');
                    $content = $this->replacePartySignatureValue($content, 'ASSIGNOR', 'Date:', '{{input.document_date}}');
                }

                if ($name === '3R-01-Independent Contractor Agreement') {
                    $content = $this->replaceInlineFieldAfterText(
                        $content,
                        'Federal Employer Identification Number or Social Security Number is:',
                        '{{input.contractor_ein_ssn}}',
                    );
                    $content = $this->replacePartySignatureValue($content, 'COMPANY', 'Printed Name:', '{{input.printed_name}}');
                    $content = $this->replacePartySignatureValue($content, 'COMPANY', 'Date:', '{{input.document_date}}');
                }

                if ($name === '3R-03-Scope of Work') {
                    $content = $this->replaceTableCellValue($content, 'Completion Deadline', '{{input.completion_deadline}}');
                    $content = $this->replacePartySignatureValue($content, 'OWNER', 'Printed Name:', '{{input.printed_name}}');
                    $content = $this->replacePartySignatureValue($content, 'OWNER', 'Date:', '{{input.document_date}}');
                }

                if ($name === '4C-01-Lien Waiver') {
                    $content = $this->replaceTableCellValue($content, 'Final Payment Amount', '{{input.final_payment_amount}}');
                    $content = $this->replaceTableCellValue($content, 'Total Paid to Date', '{{input.total_paid_to_date}}');
                    $content = $this->replacePartySignatureValue($content, 'OWNER ACKNOWLEDGMENT', 'Printed Name:', '{{input.printed_name}}');
                    $content = $this->replacePartySignatureValue($content, 'OWNER ACKNOWLEDGMENT', 'Date:', '{{input.document_date}}');
                }

                if ($name === '1A-05-Earnest Money Deposit Release Form') {
                    $content = $this->replacePartySignatureValue($content, 'BUYER', 'Printed Name:', '{{input.printed_name}}');
                    $content = $this->replacePartySignatureValue($content, 'BUYER', 'Date:', '{{input.document_date}}');
                }

                if ($name === '4C-02-Partial Lien Waiver') {
                    $content = $this->replaceTableCellValue($content, 'Payment Amount Received', '{{input.payment_amount_received}}');
                    $content = $this->replaceInlineFieldAfterText($content, 'Payment #', '{{input.payment_number}}');
                    $content = $this->replacePartySignatureValue($content, 'CONTRACTOR', 'Printed Name:', '{{input.printed_name}}');
                    $content = $this->replacePartySignatureValue($content, 'CONTRACTOR', 'Date:', '{{input.document_date}}');
                }

                if ($name === '3R-04-Change Order Form') {
                    $content = str_replace('value="{{today}}"', 'value="{{input.document_date}}"', $content);
                    $inputFields = array_values(array_filter(
                        $inputFields,
                        static fn (array $field): bool => ($field['key'] ?? null) !== 'printed_name',
                    ));
                }

                if ($name === '3R-02-Contractor Bid Package') {
                    $inputFields = array_values(array_filter(
                        $inputFields,
                        static fn (array $field): bool => ! in_array($field['key'] ?? null, ['printed_name', 'document_date'], true),
                    ));
                }

                DB::table('document_templates')
                    ->where('id', $template->id)
                    ->update([
                        'content' => $content,
                        'merge_fields' => json_encode($this->mergeFields($content)),
                        'input_fields' => json_encode($inputFields),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void {}

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

    private function replaceInlineFieldAfterText(string $content, string $text, string $value): string
    {
        return preg_replace(
            '/('.preg_quote($text, '/').'\s*)(<span\b[^>]*\binline-line\b[^>]*>).*?(<\/span>)/is',
            '$1$2'.$value.'$3',
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
                $updated = preg_replace(
                    '/(<span\b[^>]*class=["\'][^"\']*\b(?:signature-label|stacked-label)\b[^"\']*["\'][^>]*>\s*'.$quotedLabel.'\s*<\/span>\s*)(<span\b[^>]*class=["\'][^"\']*\b(?:sig-line|stacked-line)\b[^"\']*["\'][^>]*>).*?(<\/span>)/is',
                    '$1$2'.$value.'$3',
                    $match[0],
                    1,
                );

                return $updated ?? $match[0];
            },
            $content,
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
