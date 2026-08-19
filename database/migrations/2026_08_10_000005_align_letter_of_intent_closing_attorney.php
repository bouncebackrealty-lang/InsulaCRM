<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('document_templates')
            ->where('name', '1A-01-Letter of Intent')
            ->orderBy('id')
            ->each(function (object $template): void {
                $original = (string) ($template->content ?? '');
                $content = $this->replaceClosingAttorneyField($original);
                $content = $this->ensureClosingAttorneyStyles($content);

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
        // Keep the corrected field layout for existing tenants.
    }

    private function replaceClosingAttorneyField(string $content): string
    {
        return preg_replace_callback(
            '/<p\b[^>]*>\s*<strong>\s*Closing Attorney\s*\/\s*Title Company\s*:\s*<\/strong>\s*(<span\b[^>]*>.*?<\/span>)\s*<\/p>/is',
            function (array $match): string {
                $value = '';

                if (preg_match('/<span\b[^>]*>(.*?)<\/span>/is', $match[1], $valueMatch)) {
                    $value = trim($valueMatch[1]);
                }

                return '<div class="document-field">'
                    . '<strong class="document-field-label">Closing Attorney / Title Company:</strong>'
                    . '<span class="inline-line document-field-value">' . $value . '</span>'
                    . '</div>';
            },
            $content,
        ) ?? $content;
    }

    private function ensureClosingAttorneyStyles(string $content): string
    {
        if (str_contains($content, '.document-field {')) {
            return $content;
        }

        $styles = <<<'CSS'

  .document-field {
    display: flex;
    align-items: flex-end;
    gap: 5px;
    margin: 8px 0;
    font-size: 13px;
  }
  .document-field-label {
    flex: 0 0 auto;
    white-space: nowrap;
  }
  .document-field-value {
    display: block;
    flex: 1 1 auto;
    min-width: 0;
    width: auto !important;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: clip;
  }
CSS;

        $updated = preg_replace_callback(
            '/(\.inline-line\s*\{[^}]*\})/is',
            static fn (array $match): string => $match[1] . $styles,
            $content,
            1,
        );

        if ($updated !== null && $updated !== $content) {
            return $updated;
        }

        return preg_replace('/<\/style>/i', $styles . "\n</style>", $content, 1) ?? $content;
    }
};
