<?php

use App\Services\OriginalDocumentTemplateLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $templates = OriginalDocumentTemplateLibrary::templates();


        DB::table('tenants')
            ->where('name', 'Bounce Back Realty')
            ->orderBy('id')
            ->each(function (object $tenant) use ($templates): void {
                foreach ($templates as $template) {
                    $data = [
                        'type' => $template['type'],
                        'content' => $template['content'],
                        'merge_fields' => json_encode($template['merge_fields'], JSON_THROW_ON_ERROR),
                        'input_fields' => json_encode($template['input_fields'], JSON_THROW_ON_ERROR),
                        'is_default' => $template['is_default'],
                        'updated_at' => now(),
                    ];

                    $existing = DB::table('document_templates')
                        ->where('tenant_id', $tenant->id)
                        ->where('name', $template['name'])
                        ->exists();

                    if ($existing) {
                        DB::table('document_templates')
                            ->where('tenant_id', $tenant->id)
                            ->where('name', $template['name'])
                            ->update($data);

                        continue;
                    }

                    DB::table('document_templates')->insert([
                        ...$data,
                        'tenant_id' => $tenant->id,
                        'name' => $template['name'],
                        'created_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {

    }
};
