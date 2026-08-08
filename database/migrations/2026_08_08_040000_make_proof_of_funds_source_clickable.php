<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('document_templates')->where('name', 'like', '%Proof of Funds%')->orderBy('id')->each(function (object $template) {
            $fields = json_decode($template->input_fields ?: '[]', true) ?: [];
            foreach ($fields as &$field) {
                if (($field['key'] ?? null) === 'source_of_funds') {
                    $field['type'] = 'radio';
                    $field['options'] = ['Cash on Hand', 'Hard Money / Private Lender', 'Bank Financing', 'Investor / Partner Funds', 'Other'];
                }
            }
            DB::table('document_templates')->where('id', $template->id)->update(['input_fields' => json_encode($fields), 'updated_at' => now()]);
        });
    }

    public function down(): void {}
};
