<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Deal;
use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Services\DocumentMergeService;
use App\Services\HeadlessPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DocumentGeneratorController extends Controller
{
    protected DocumentMergeService $mergeService;

    protected HeadlessPdfService $pdfService;

    public function __construct(DocumentMergeService $mergeService, HeadlessPdfService $pdfService)
    {
        $this->mergeService = $mergeService;
        $this->pdfService = $pdfService;
    }

    /**
     * Show form to select template and preview with real deal data.
     */
    public function create(Deal $deal)
    {
        $deal->loadMissing(['lead.property', 'tenant', 'selectedBuyer', 'buyerMatches.buyer', 'contractors.contractor', 'lenders.lender', 'titleCompany']);

        $templates = DocumentTemplate::orderBy('name')->get();

        $generatedDocuments = GeneratedDocument::where('deal_id', $deal->id)
            ->with(['template', 'user'])
            ->latest()
            ->get();

        $contractors = $deal->contractors
            ->map(fn ($dealContractor) => $dealContractor->contractor)
            ->filter()
            ->values();

        return view('documents.generate', compact('deal', 'templates', 'generatedDocuments', 'contractors'));
    }

    /**
     * AJAX: Preview a template merged with real deal data.
     */
    public function previewWithDeal(Request $request, Deal $deal)
    {
        $request->validate([
            'template_id' => 'required|exists:document_templates,id',
            'contractor_id' => 'nullable|integer',
        ]);

        $template = DocumentTemplate::findOrFail($request->template_id);
        $deal->loadMissing(['lead.property', 'tenant', 'selectedBuyer', 'buyerMatches.buyer', 'contractors.contractor']);

        $rendered = $this->mergeService->merge(
            $template->content,
            $deal,
            $this->selectedContractor($request, $deal, $template),
            $this->documentInputs($request, $template),
        );

        return response()->json([
            'html' => $rendered,
        ]);
    }

    /**
     * Generate a document from template + deal data.
     */
    public function store(Request $request, Deal $deal)
    {
        $request->validate([
            'template_id' => 'required|exists:document_templates,id',
            'name' => 'nullable|string|max:255',
            'contractor_id' => 'nullable|integer',
            'preview_controls' => 'nullable|string|max:131072',
        ]);

        $template = DocumentTemplate::findOrFail($request->template_id);
        $deal->loadMissing(['lead.property', 'tenant', 'selectedBuyer', 'buyerMatches.buyer', 'contractors.contractor']);

        $requiresSelectedBuyer = collect($template->merge_fields ?? [])->contains(
            static fn ($field): bool => str_starts_with((string) $field, 'buyer.')
        ) || str_contains((string) $template->content, '{{buyer.');

        if ($requiresSelectedBuyer && ! $deal->selectedBuyer) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'template_id' => __('Select the Buyer for This Deal before generating this buyer-specific document.'),
            ]);
        }

        $rendered = $this->mergeService->merge(
            $template->content,
            $deal,
            $this->selectedContractor($request, $deal, $template),
            $this->documentInputs($request, $template),
        );

        $rendered = $this->mergeService->applyPreviewControlValues(
            $rendered,
            $this->previewControls($request),
        );

        $rendered = $this->sanitizeDocumentHtml($rendered);

        $documentName = $request->input('name')
            ?: $template->name . ' - ' . ($deal->lead->full_name ?? $deal->title);

        $document = GeneratedDocument::create([
            'tenant_id' => auth()->user()->tenant_id,
            'deal_id' => $deal->id,
            'template_id' => $template->id,
            'user_id' => auth()->id(),
            'name' => $documentName,
            'content' => $rendered,
        ]);

        AuditLog::log('document.generated', $document, null, [
            'template' => $template->name,
            'deal' => $deal->title,
        ]);

        return redirect()->route('documents.show', $document)
            ->with('success', __('Document generated successfully.'));
    }

    /**
     * Display the generated document.
     */
    public function show(GeneratedDocument $document)
    {
        $this->authorizeDocument($document);

        $document->loadMissing(['deal', 'template', 'user']);

        return view('documents.show', compact('document'));
    }

    /**
     * Edit a generated document before it is downloaded or sent.
     */
    public function edit(GeneratedDocument $document)
    {
        $this->authorizeDocument($document);

        return view('documents.edit', compact('document'));
    }

    /**
     * Save a one-off revision to a generated document snapshot.
     */
    public function update(Request $request, GeneratedDocument $document)
    {
        $this->authorizeDocument($document);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        // Keep audit records small. The generated snapshot itself retains the
        // full document content, while the audit only needs to record that it
        // was revised.
        $before = ['name' => $document->name];
        $document->update([
            'name' => $data['name'],
            'content' => $this->sanitizeDocumentHtml($data['content']),
        ]);

        AuditLog::log('document.updated', $document, $before, [
            'name' => $document->name,
            'content_updated' => true,
        ]);

        return redirect()->route('documents.show', $document)
            ->with('success', __('Document changes saved.'));
    }

    /**
     * Print-optimized view for browser print-to-PDF.
     */
    public function print(GeneratedDocument $document)
    {
        $this->authorizeDocument($document);

        $document->loadMissing(['deal.tenant']);

        $companyName = $document->deal?->tenant?->name
            ?? auth()->user()->tenant->name
            ?? '';

        return view('documents.print', [
            'content' => $document->content,
            'documentName' => $document->name,
            'companyName' => $companyName,
        ]);
    }

    /**
     * Download a fixed US Letter PDF from the saved generated document.
     */
    public function downloadPdf(GeneratedDocument $document)
    {
        $this->authorizeDocument($document);

        $document->loadMissing(['deal.tenant']);

        $companyName = $document->deal?->tenant?->name
            ?? auth()->user()->tenant->name
            ?? '';

        $html = view('documents.print', [
            'content' => $document->content,
            'documentName' => $document->name,
            'companyName' => $companyName,
            'showControls' => false,
            'autoPrint' => false,
        ])->render();

        $filename = $this->pdfFilename($document->name);

        return response($this->pdfService->render($html), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename=' . $filename,
        ]);
    }

    /**
     * Delete a generated document.
     */
    public function destroy(GeneratedDocument $document)
    {
        $this->authorizeDocument($document);

        $dealId = $document->deal_id;
        $name = $document->name;

        $document->delete();

        AuditLog::log('document.deleted', null, ['name' => $name]);

        return redirect()->route('documents.generate', $dealId)
            ->with('success', __('Document deleted successfully.'));
    }

    private function authorizeDocument(GeneratedDocument $document): void
    {
        abort_unless($document->tenant_id === auth()->user()->tenant_id, 403);

        $document->loadMissing('deal');
        abort_unless($document->deal, 404);

        $this->authorize('view', $document->deal);
    }

    private function pdfFilename(string $documentName): string
    {
        $filename = Str::slug(Str::of($documentName)->replace('.pdf', '')->toString());

        return ($filename !== '' ? $filename : 'generated-document') . '.pdf';
    }

    /**
     * Generate an investor packet for a deal with property, ARV, comps, and economics.
     */
    public function investorPacket(Deal $deal)
    {
        $this->authorize('view', $deal);

        $deal->load(['lead.property']);
        $property = $deal->lead?->property;

        // Build investor packet HTML
        $html = '<div class="investor-packet">';
        $html .= '<h1 style="text-align:center; margin-bottom:20px;">Investor Packet</h1>';

        // Property Overview
        $html .= '<h2>Property Overview</h2>';
        $html .= '<table style="width:100%; border-collapse:collapse; margin-bottom:20px;">';
        $html .= '<tr><td style="padding:8px; border:1px solid #ddd; font-weight:bold;">Address</td><td style="padding:8px; border:1px solid #ddd;">' . e($property->address ?? 'N/A') . ', ' . e($property->city ?? '') . ', ' . e($property->state ?? '') . ' ' . e($property->zip_code ?? '') . '</td></tr>';
        $html .= '<tr><td style="padding:8px; border:1px solid #ddd; font-weight:bold;">Property Type</td><td style="padding:8px; border:1px solid #ddd;">' . e(ucwords(str_replace('_', ' ', $property->property_type ?? 'N/A'))) . '</td></tr>';
        $html .= '<tr><td style="padding:8px; border:1px solid #ddd; font-weight:bold;">Beds / Baths</td><td style="padding:8px; border:1px solid #ddd;">' . e($property->bedrooms ?? 'N/A') . ' / ' . e($property->bathrooms ?? 'N/A') . '</td></tr>';
        $html .= '<tr><td style="padding:8px; border:1px solid #ddd; font-weight:bold;">Square Footage</td><td style="padding:8px; border:1px solid #ddd;">' . ($property->square_footage ? number_format($property->square_footage) : 'N/A') . '</td></tr>';
        $html .= '<tr><td style="padding:8px; border:1px solid #ddd; font-weight:bold;">Year Built</td><td style="padding:8px; border:1px solid #ddd;">' . e($property->year_built ?? 'N/A') . '</td></tr>';
        $html .= '<tr><td style="padding:8px; border:1px solid #ddd; font-weight:bold;">Lot Size</td><td style="padding:8px; border:1px solid #ddd;">' . ($property->lot_size ? \Fmt::area($property->lot_size) : 'N/A') . '</td></tr>';
        $html .= '</table>';

        // ARV Summary
        if ($property) {
            $comps = \App\Models\ComparableSale::where('property_id', $property->id)->get();
            if ($comps->isNotEmpty()) {
                $adjustedPrices = $comps->pluck('adjusted_price')->filter(fn ($price) => $price !== null && $price !== '');
                $arvAvg = $adjustedPrices->avg();
                $arvMedian = $adjustedPrices->median();

                $html .= '<h2>ARV Summary</h2>';
                $html .= '<table style="width:100%; border-collapse:collapse; margin-bottom:20px;">';
                $html .= '<tr><td style="padding:8px; border:1px solid #ddd; font-weight:bold;">Average ARV</td><td style="padding:8px; border:1px solid #ddd;">' . \Fmt::currency($arvAvg) . '</td></tr>';
                $html .= '<tr><td style="padding:8px; border:1px solid #ddd; font-weight:bold;">Median ARV</td><td style="padding:8px; border:1px solid #ddd;">' . \Fmt::currency($arvMedian) . '</td></tr>';
                if ($property->after_repair_value) {
                    $html .= '<tr><td style="padding:8px; border:1px solid #ddd; font-weight:bold;">Stated ARV</td><td style="padding:8px; border:1px solid #ddd;">' . \Fmt::currency($property->after_repair_value) . '</td></tr>';
                }
                $html .= '</table>';

                // Comp table
                $html .= '<h2>Comparable Sales</h2>';
                $html .= '<table style="width:100%; border-collapse:collapse; margin-bottom:20px;">';
                $html .= '<tr style="background:#f5f5f5;"><th style="padding:8px; border:1px solid #ddd;">Address</th><th style="padding:8px; border:1px solid #ddd;">Sold Price</th><th style="padding:8px; border:1px solid #ddd;">Sq Ft</th><th style="padding:8px; border:1px solid #ddd;">$/Sq Ft</th><th style="padding:8px; border:1px solid #ddd;">Sold Date</th></tr>';
                foreach ($comps as $comp) {
                    $ppsf = ($comp->sale_price && $comp->sqft) ? round($comp->sale_price / $comp->sqft, 2) : 'N/A';
                    $html .= '<tr>';
                    $html .= '<td style="padding:8px; border:1px solid #ddd;">' . e($comp->address ?? 'N/A') . '</td>';
                    $html .= '<td style="padding:8px; border:1px solid #ddd;">' . ($comp->sale_price ? \Fmt::currency($comp->sale_price) : 'N/A') . '</td>';
                    $html .= '<td style="padding:8px; border:1px solid #ddd;">' . ($comp->sqft ? number_format($comp->sqft) : 'N/A') . '</td>';
                    $html .= '<td style="padding:8px; border:1px solid #ddd;">' . (is_numeric($ppsf) ? \Fmt::currency($ppsf) : 'N/A') . '</td>';
                    $html .= '<td style="padding:8px; border:1px solid #ddd;">' . ($comp->sale_date ? \Fmt::date($comp->sale_date) : 'N/A') . '</td>';
                    $html .= '</tr>';
                }
                $html .= '</table>';
            }
        }

        // Economics
        $html .= '<h2>Deal Economics</h2>';
        $html .= '<table style="width:100%; border-collapse:collapse; margin-bottom:20px;">';
        $html .= '<tr><td style="padding:8px; border:1px solid #ddd; font-weight:bold;">Contract Price</td><td style="padding:8px; border:1px solid #ddd;">' . ($deal->contract_price ? \Fmt::currency($deal->contract_price) : 'N/A') . '</td></tr>';
        if ($property && $property->repair_estimate) {
            $html .= '<tr><td style="padding:8px; border:1px solid #ddd; font-weight:bold;">Estimated Repairs</td><td style="padding:8px; border:1px solid #ddd;">' . \Fmt::currency($property->repair_estimate) . '</td></tr>';
        }
        if ($deal->assignment_fee) {
            $html .= '<tr><td style="padding:8px; border:1px solid #ddd; font-weight:bold;">Assignment Fee</td><td style="padding:8px; border:1px solid #ddd;">' . \Fmt::currency($deal->assignment_fee) . '</td></tr>';
        }
        if ($property && $property->after_repair_value && $deal->contract_price && $property->repair_estimate) {
            $spread = $property->after_repair_value - $deal->contract_price - $property->repair_estimate - ($deal->assignment_fee ?? 0);
            $html .= '<tr><td style="padding:8px; border:1px solid #ddd; font-weight:bold;">Investor Spread</td><td style="padding:8px; border:1px solid #ddd;">' . \Fmt::currency($spread) . '</td></tr>';
        }
        $html .= '</table>';

        $html .= '</div>';

        $printHtml = app(\App\Services\DocumentMergeService::class)->generatePrintHtml($html, 'Investor Packet — ' . ($property->address ?? $deal->title), auth()->user()->tenant->name);

        return response($printHtml);
    }

    private function selectedContractor(Request $request, Deal $deal, ?DocumentTemplate $template = null): ?\App\Models\Contractor
    {
        if (in_array($template?->type, ['loi', 'purchase_agreement'], true)) {
            return null;
        }

        if (! $request->filled('contractor_id')) {
            $attachedContractors = $deal->contractors
                ->map(fn ($dealContractor) => $dealContractor->contractor)
                ->filter()
                ->values();

            return $attachedContractors->count() === 1
                ? $attachedContractors->first()
                : null;
        }

        $contractor = $deal->contractors
            ->firstWhere('contractor_id', (int) $request->input('contractor_id'))
            ?->contractor;

        abort_unless($contractor && $contractor->tenant_id === auth()->user()->tenant_id, 422);

        return $contractor;
    }

    private function sanitizeDocumentHtml(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);

        return preg_replace('/\bon\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
    }


    private function documentInputs(Request $request, DocumentTemplate $template): array
    {
        $configured = collect($template->input_fields ?? [])->pluck('key')->filter()->all();
        $submitted = (array) $request->input('document_inputs', []);

        return array_filter(
            array_intersect_key($submitted, array_flip($configured)),
            static fn ($value) => $value !== null && $value !== ''
        );
    }

    /**
     * Normalize values captured from controls typed into the live preview.
     * Values are bounded here before being safely escaped into generated HTML
     * by DocumentMergeService.
     *
     * @return array<int, array{index:int, tag:string, type:string, value:string, checked:bool}>
     */
    private function previewControls(Request $request): array
    {
        $raw = $request->input('preview_controls');
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        $controls = [];
        foreach (array_slice($decoded, 0, 100) as $control) {
            if (! is_array($control)) {
                continue;
            }

            $index = filter_var($control['index'] ?? null, FILTER_VALIDATE_INT);
            $tag = strtolower((string) ($control['tag'] ?? ''));
            if ($index === false || $index < 0 || ! in_array($tag, ['input', 'textarea', 'select'], true)) {
                continue;
            }

            $value = $control['value'] ?? '';
            if (! is_scalar($value) && $value !== null) {
                continue;
            }

            $controls[] = [
                'index' => $index,
                'tag' => $tag,
                'type' => substr(strtolower((string) ($control['type'] ?? 'text')), 0, 30),
                'value' => mb_substr((string) $value, 0, 10000),
                'checked' => (bool) ($control['checked'] ?? false),
            ];
        }

        return $controls;
    }
}
