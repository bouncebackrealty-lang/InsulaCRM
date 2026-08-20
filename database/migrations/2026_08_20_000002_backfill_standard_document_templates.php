<?php

use App\Services\DocumentTemplateLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $definitions = DocumentTemplateLibrary::bbrTemplates();
        $names = array_column($definitions, 'name');


        $sourceTemplates = DB::table('document_templates')
            ->whereIn('name', $names)
            ->orderBy('id')
            ->get()
            ->keyBy('name');

        DB::table('tenants')->orderBy('id')->each(function (object $tenant) use ($definitions, $sourceTemplates): void {
            $existingNames = DB::table('document_templates')
                ->where('tenant_id', $tenant->id)
                ->pluck('name')
                ->all();

            foreach ($definitions as $definition) {
                if (in_array($definition['name'], $existingNames, true)) {
                    continue;
                }

                $source = $sourceTemplates->get($definition['name']);
                $template = $source
                    ? [
                        'name' => $source->name,
                        'type' => $source->type,
                        'content' => $source->content,
                        'merge_fields' => $source->merge_fields,
                        'input_fields' => $source->input_fields,
                        'is_default' => $source->is_default,
                    ]
                    : $definition;

                DB::table('document_templates')->insert([
                    'tenant_id' => $tenant->id,
                    'name' => $template['name'],
                    'type' => $template['type'],
                    'content' => $template['content'],
                    'merge_fields' => is_string($template['merge_fields'])
                        ? $template['merge_fields']
                        : json_encode($template['merge_fields']),
                    'input_fields' => is_string($template['input_fields'] ?? null)
                        ? $template['input_fields']
                        : json_encode($template['input_fields'] ?? []),
                    'is_default' => $template['is_default'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {

    }
};
