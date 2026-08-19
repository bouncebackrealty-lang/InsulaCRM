<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{

    public function up(): void
    {
        $this->repairBidPackage();
        $this->repairChangeOrder();
    }

    public function down(): void
    {
        // Retain the corrected merge fields for existing tenant templates.
    }

    private function repairBidPackage(): void
    {
        DB::table('document_templates')
            ->where('name', '3R-02-Contractor Bid Package')
            ->orderBy('id')
            ->each(function ($template) {
                $content = $template->content;
                $mappings = [
                    'Property Address' => '{{property.full_address}}',
                    'City / Zip' => '{{property.city}}, {{property.state}} {{property.zip_code}}',
                    'Beds / Baths / Sq Ft' => '{{property.bedrooms}} / {{property.bathrooms}} / {{property.square_footage}}',
                    'Year Built' => '{{property.year_built}}',
                    'Target ARV' => '{{property.after_repair_value}}',
                ];

                foreach ($mappings as $label => $value) {
                    $content = $this->replaceAdjacentTableCell($content, $label, $value);
                }

                $content = preg_replace(
                    '/(<strong>Contractor Company Name:<\/strong>\s*)_+/i',
                    '$1{{contractor.name}}',
                    $content,
                );
                $content = preg_replace(
                    '/(<strong>Contact Name:<\/strong>\s*)_+/i',
                    '$1{{contractor.name}}',
                    $content,
                );
                $content = preg_replace(
                    '/(<strong>Phone\s*\/\s*Email:<\/strong>\s*)_+/i',
                    '$1{{contractor.phone}} / {{contractor.email}}',
                    $content,
                );

                $this->saveTemplate($template->id, $content, [
                    'property.full_address', 'property.city', 'property.state', 'property.zip_code',
                    'property.bedrooms', 'property.bathrooms', 'property.square_footage',
                    'property.year_built', 'property.after_repair_value', 'contractor.name',
                    'contractor.phone', 'contractor.email',
                ]);
            });
    }

    private function repairChangeOrder(): void
    {
        DB::table('document_templates')
            ->where('name', '3R-04-Change Order Form')
            ->orderBy('id')
            ->each(function ($template) {
                $content = $template->content;
                $mappings = [
                    'Date' => 'today',
                    'Property Address' => 'property.full_address',
                    'Contractor' => 'contractor.name',
                    'Original Contract Date' => 'deal.contract_date',
                    'Original Contract $' => 'deal.contract_price',
                    'Original Contract Amount' => 'deal.contract_price',
                ];

                foreach ($mappings as $label => $field) {
                    $content = $this->setValueOnAdjacentInput($content, $label, $field);
                }

                // Change-specific values must start empty rather than copying
                // the purchase contract price from an earlier generic mapping.
                foreach (['Additional Cost', 'Credit / Deduction', 'Net Change'] as $label) {
                    $content = $this->removeValueFromAdjacentInput($content, $label);
                }

                $this->saveTemplate($template->id, $content, [
                    'today', 'property.full_address', 'contractor.name',
                    'deal.contract_date', 'deal.contract_price',
                ]);
            });
    }

    private function replaceAdjacentTableCell(string $content, string $label, string $value): string
    {
        $quotedLabel = preg_quote($label, '/');

        return preg_replace(
            '/(<td\b[^>]*>\s*(?:<strong>)?' . $quotedLabel . '(?:<\/strong>)?\s*<\/td>\s*<td\b[^>]*>)\s*\$?_+\s*(<\/td>)/i',
            '$1' . $value . '$2',
            $content,
        );
    }

    private function setValueOnAdjacentInput(string $content, string $label, string $field): string
    {
        $quotedLabel = preg_quote($label, '/');

        return preg_replace_callback(
            '/(<td\b[^>]*>\s*(?:<strong>)?' . $quotedLabel . '(?:<\/strong>)?\s*<\/td>\s*<td\b[^>]*>\s*<input\b)([^>]*)(>)/i',
            function (array $match) use ($field) {
                $attributes = preg_replace('/\svalue="[^"]*"/i', '', $match[2]);

                return $match[1] . $attributes . ' value="{{' . $field . '}}"' . $match[3];
            },
            $content,
            1,
        );
    }

    private function removeValueFromAdjacentInput(string $content, string $label): string
    {
        $quotedLabel = preg_quote($label, '/');

        return preg_replace(
            '/(<td\b[^>]*>\s*(?:<strong>)?' . $quotedLabel . '(?:<\/strong>)?\s*<\/td>\s*<td\b[^>]*>\s*<input\b[^>]*?)\s+value="\{\{deal\.contract_price\}\}"/i',
            '$1',
            $content,
            1,
        );
    }

    private function saveTemplate(int $templateId, string $content, array $mergeFields): void
    {
        DB::table('document_templates')->where('id', $templateId)->update([
            'content' => $content,
            'merge_fields' => json_encode($mergeFields),
            'updated_at' => now(),
        ]);
    }
};
