<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->correctLetterOfIntent();
        $this->correctPurchaseAndAssignmentDates();
        $this->correctAssignmentAddress();
        $this->correctContractorAgreement();
        $this->correctScopeOfWork();
        $this->clearUnsupportedPaymentAmounts();
        $this->syncMergeFieldIndexes();
    }

    public function down(): void
    {
        // These corrections repair existing templates. They are intentionally retained.
    }

    private function correctLetterOfIntent(): void
    {
        $this->updateTemplate('1A-01-Letter of Intent', function (string $content): string {
            return preg_replace(
                '/(<p>\s*<strong>Date:<\/strong>\s*<span\b[^>]*>).*?(<\/span>\s*<\/p>)/is',
                '$1{{today}}$2',
                $content,
                1,
            );
        });
    }

    private function correctPurchaseAndAssignmentDates(): void
    {
        foreach (['1A-02-Purchase Agreement', '2D-01-Assignment Contract'] as $templateName) {
            $this->updateTemplate($templateName, function (string $content): string {
                return preg_replace(
                    '/20(\s*<span\b[^>]*>\s*\{\{(?:today_year|deal\.closing_year)\}\}\s*<\/span>)/i',
                    '$1',
                    $content,
                );
            });
        }
    }

    private function correctAssignmentAddress(): void
    {
        $this->updateTemplate('2D-01-Assignment Contract', function (string $content): string {
            return $this->replaceTableCellValue(
                $content,
                'City / State / Zip',
                '{{property.city}}, {{property.state}} {{property.zip_code}}',
            );
        });
    }

    private function correctContractorAgreement(): void
    {
        $this->updateTemplate('3R-01-Independent Contractor Agreement', function (string $content): string {
            $content = $this->replaceTableCellValue($content, 'Trade / Service Specialty', '{{contractor.trade}}');

            // The CRM does not store a contractor EIN/SSN. Leaving the line blank is
            // safer than incorrectly using a deal amount as a tax identifier.
            return preg_replace(
                '/(Federal Employer Identification Number or Social Security Number is:\s*<span\b[^>]*>).*?(<\/span>)/is',
                '$1$2',
                $content,
                1,
            );
        });
    }

    private function correctScopeOfWork(): void
    {
        $this->updateTemplate('3R-03-Scope of Work', function (string $content): string {
            $content = $this->replaceTableCellValue($content, 'Property Address', '{{property.full_address}}');
            $content = $this->replaceTableCellValue($content, 'Project Name', '{{property.address}}');
            $content = $this->replaceTableCellValue($content, 'Contractor Name / Entity', '{{contractor.name}}');

            // The old conversion put contractor values in date fields. Agreement and
            // completion dates must not be fabricated from contractor information.
            $content = $this->replaceTableCellContents($content, 'Agreement Date', '<span class="inline-line" style="width: 200px;">{{today}}</span>');

            return $this->replaceTableCellContents($content, 'Completion Deadline', '<span class="inline-line" style="width: 200px;"></span>');
        });
    }

    private function clearUnsupportedPaymentAmounts(): void
    {
        $this->updateTemplate('4C-01-Lien Waiver', function (string $content): string {
            $content = $this->replaceTableCellValue($content, 'Final Payment Amount', '');

            return $this->replaceTableCellValue($content, 'Total Paid to Date', '');
        });

        $this->updateTemplate('4C-02-Partial Lien Waiver', function (string $content): string {
            return $this->replaceTableCellValue($content, 'Payment Amount Received', '');
        });
    }

    private function updateTemplate(string $name, callable $callback): void
    {
        DB::table('document_templates')
            ->where('name', $name)
            ->orderBy('id')
            ->each(function ($template) use ($callback) {
                $content = $callback($template->content);

                if ($content !== $template->content) {
                    DB::table('document_templates')
                        ->where('id', $template->id)
                        ->update([
                            'content' => $content,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    private function replaceTableCellValue(string $content, string $label, string $value): string
    {
        $quotedLabel = preg_quote($label, '/');

        return preg_replace_callback(
            '/(<td\b[^>]*>\s*(?:<strong>)?' . $quotedLabel . '(?:<\/strong>)?\s*<\/td>\s*<td\b[^>]*>)(.*?)(<\/td>)/is',
            function (array $match) use ($value) {
                $inner = preg_replace(
                    '/(<span\b[^>]*>).*?(<\/span>)/is',
                    '$1' . $value . '$2',
                    $match[2],
                    1,
                );

                return $match[1] . $inner . $match[3];
            },
            $content,
            1,
        );
    }

    private function replaceTableCellContents(string $content, string $label, string $contents): string
    {
        $quotedLabel = preg_quote($label, '/');

        return preg_replace(
            '/(<td\b[^>]*>\s*(?:<strong>)?' . $quotedLabel . '(?:<\/strong>)?\s*<\/td>\s*<td\b[^>]*>).*?(<\/td>)/is',
            '$1' . $contents . '$2',
            $content,
            1,
        );
    }

    private function syncMergeFieldIndexes(): void
    {
        DB::table('document_templates')->orderBy('id')->each(function ($template) {
            preg_match_all('/\{\{([a-z_.]+)\}\}/', $template->content ?? '', $matches);

            DB::table('document_templates')->where('id', $template->id)->update([
                'merge_fields' => json_encode(array_values(array_unique($matches[1] ?? []))),
                'updated_at' => now(),
            ]);
        });
    }
};
