<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{

    public function up(): void
    {
        foreach (DB::table('document_templates')->get() as $template) {
            $fields = $this->fieldsForTemplate($template->name);
            $content = $template->content;

            if ($fields) {
                $position = 0;
                $content = preg_replace_callback(
                    '/(<span\b[^>]*\binline-line\b[^>]*>)\s*<\/span>/i',
                    function (array $match) use (&$position, $fields) {
                        $field = $fields[$position++] ?? null;

                        return $match[1] . ($field ? '{{' . $field . '}}' : '') . '</span>';
                    },
                    $content,
                );
            }

            if ($content !== $template->content) {
                DB::table('document_templates')
                    ->where('id', $template->id)
                    ->update([
                        'content' => $content,
                        'merge_fields' => json_encode(array_values(array_unique(array_filter($fields)))),
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {

    }

    private function fieldsForTemplate(string $name): array
    {
        return match ($name) {
            '1A-02-Purchase Agreement' => [
                'lead.full_name', 'property.full_address', 'property.parcel_id',
                'deal.contract_price', 'deal.earnest_money', null, null,
                'deal.closing_month_day', 'deal.closing_year',
            ],
            '2D-01-Assignment Contract' => [
                'today_month_day', 'today_year', 'deal.contract_date',
                'buyer.company', null, 'buyer.phone', 'property.full_address',
                'property.full_address', 'property.parcel_id', 'lead.full_name',
                'deal.contract_price', 'deal.assignment_fee', 'deal.total_purchase_price',
                'deal.earnest_money', 'deal.due_diligence_end_date', null,
                'deal.closing_month_day', 'deal.closing_year',
            ],
            '3R-01-Independent Contractor Agreement' => [
                'today_month_day', 'today_year', 'contractor.name', 'contractor.trade',
                'contractor.service_area', 'contractor.phone', 'contractor.email',
                'property.full_address', 'deal.contract_price', 'deal.contract_date',
            ],
            '3R-03-Scope of Work' => [
                'today', 'property.full_address', 'contractor.name', 'contractor.trade',
                'contractor.phone', 'contractor.email',
            ],
            '4C-01-Lien Waiver' => [
                'today', 'contractor.name', 'property.full_address', 'contractor.trade',
                'deal.contract_price', 'deal.contract_price',
            ],
            '1A-05-Earnest Money Deposit Release Form' => [
                'today', 'property.full_address', 'company.name', 'lead.full_name', 'deal.contract_date',
            ],
            '1A-03-Seller Disclosure' => [
                'property.full_address', 'lead.full_name', 'today',
            ],
            '1A-04-Proof of Funds' => [
                'today', 'deal.contract_price', 'company.name', 'property.full_address', 'deal.contract_price',
            ],
            '4C-02-Partial Lien Waiver' => [
                'property.full_address', 'contractor.name', 'deal.contract_price', null, 'today', 'today',
            ],
            default => [],
        };
    }
};
