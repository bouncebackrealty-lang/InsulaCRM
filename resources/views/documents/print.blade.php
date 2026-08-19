<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $documentName ?? __('Document') }}</title>
    <style>
        /* ── Base Typography ──────────────────────── */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Georgia, 'Times New Roman', Times, serif;
            font-size: 14px;
            line-height: 1.6;
            color: #1a1a1a;
            background: #fff;
            padding: 0;
        }

        /* ── Screen-only controls ─────────────────── */
        .print-controls {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 9999;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .print-controls .btn {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 4px;
            text-decoration: none;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border: 1px solid transparent;
        }

        .print-controls .btn-primary {
            background: #0054a6;
            color: #fff;
            border-color: #0054a6;
        }

        .print-controls .btn-primary:hover {
            background: #004085;
        }

        .print-controls .btn-secondary {
            background: #e9ecef;
            color: #495057;
            border-color: #dee2e6;
        }

        .print-controls .btn-secondary:hover {
            background: #dee2e6;
        }

        .print-controls .doc-title {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 14px;
            color: #495057;
        }

        /* ── Document Container ──────────────────── */
        .document-container {
            max-width: 8.5in;
            margin: 70px auto 40px;
            padding: 1in;
            background: #fff;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            min-height: 11in;
        }

        /* ── Content Styles ──────────────────────── */
        .document-content h1 {
            font-size: 22px;
            margin-bottom: 10px;
        }

        .document-content h2 {
            font-size: 18px;
            margin-bottom: 8px;
            margin-top: 20px;
        }

        .document-content h3 {
            font-size: 16px;
            margin-bottom: 6px;
            margin-top: 16px;
        }

        .document-content p {
            margin-bottom: 10px;
        }

        .document-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        .document-content table td,
        .document-content table th {
            padding: 8px;
            text-align: left;
        }

        .document-content ul, .document-content ol {
            margin: 10px 0;
            padding-left: 30px;
        }

        .document-content li {
            margin-bottom: 4px;
        }

        .document-content hr {
            border: none;
            border-top: 1px solid #ccc;
            margin: 20px 0;
        }

        /* ── Print Styles ────────────────────────── */
        /* Keep direct PDF downloads consistently sized on US Letter paper. */
        @page {
            size: letter;
            margin: 0.55in;
        }

        @media print {
            .print-controls {
                display: none !important;
            }

            body {
                padding: 0;
                background: #fff;
            }

            .document-container {
                margin: 0;
                padding: 0;
                box-shadow: none;
                max-width: none;
                min-height: auto;
            }

            /* Avoid breaking inside important elements */
            h1, h2, h3, h4 {
                page-break-after: avoid;
            }

            table, figure, img {
                page-break-inside: avoid;
            }

            p {
                orphans: 3;
                widows: 3;
            }
        }
    </style>
</head>
<body>
    @if ($showControls ?? true)
        {{-- Screen-only toolbar --}}
        <div class="print-controls">
            <span class="doc-title">{{ $documentName ?? __('Document') }}</span>
            <div>
                <button class="btn btn-secondary" onclick="window.close()">{{ __('Close') }}</button>
                <button class="btn btn-primary" onclick="window.print()">{{ __('Print / Save PDF') }}</button>
            </div>
        </div>
    @endif

    <div class="document-container">
        <div class="document-content">
            {!! $content !!}
        </div>
    </div>

    {{--
        Saved templates can include their own screen-oriented styles. These PDF-only
        overrides run after that content so direct downloads have efficient, consistent
        Letter-page spacing without altering the on-screen document view.
    --}}
    <style media="print">
        .document-content > .document-container {
            max-width: none !important;
            min-height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .document-content .header {
            margin-bottom: 12px !important;
        }

        .document-content .logo-box {
            padding: 10px 20px !important;
        }

        .document-content .logo {
            max-width: 54px !important;
        }

        .document-content .company-title {
            font-size: 23px !important;
        }

        .document-content .doc-title {
            margin-top: 10px !important;
        }

        .document-content .divider-line {
            margin: 10px 0 16px !important;
        }

        .document-content h1,
        .document-content h2,
        .document-content h3,
        .document-content h4 {
            margin-top: 14px !important;
            margin-bottom: 6px !important;
        }

        .document-content p {
            margin: 5px 0 !important;
            line-height: 1.4 !important;
        }

        .document-content table {
            margin: 6px 0 !important;
        }

        .document-content table td,
        .document-content table th {
            padding: 5px !important;
        }

        .document-content .signature-table {
            margin-top: 18px !important;
        }

        .document-content .signature-field {
            margin-top: 12px !important;
        }

        html body .document-content .footer,
        html body .document-content .document-tagline {
            position: static !important;
            clear: both;
            margin-top: clamp(22px, 3vw, 30px) !important;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        html body .document-content .seller-disclosure-document .signature-table {
            margin-top: 10px !important;
        }

        html body .document-content .seller-disclosure-document .footer {
            margin-top: 10px !important;
        }
    </style>

    @if ($autoPrint ?? true)
        <script>
            // Auto-trigger print dialog after a brief delay for rendering
            window.addEventListener('load', function() {
                setTimeout(function() {
                    window.print();
                }, 500);
            });
        </script>
    @endif
</body>
</html>
