<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Process;

class HeadlessPdfService
{
    /**
     * Render complete print HTML through Chrome/Chromium so generated PDFs use
     * the same layout engine as the CRM's browser print view.
     */
    public function render(string $html): string
    {
        $temporaryDirectory = storage_path('app/tmp/pdf-' . Str::uuid());
        $htmlPath = $temporaryDirectory . DIRECTORY_SEPARATOR . 'document.html';
        $pdfPath = $temporaryDirectory . DIRECTORY_SEPARATOR . 'document.pdf';
        $browserHomeDirectory = $temporaryDirectory . DIRECTORY_SEPARATOR . 'browser-home';
        $browserProfileDirectory = $temporaryDirectory . DIRECTORY_SEPARATOR . 'browser-profile';

        foreach ([$temporaryDirectory, $browserHomeDirectory, $browserProfileDirectory] as $directory) {
            File::ensureDirectoryExists($directory);
        }

        try {
            File::put($htmlPath, $html);

            $process = new Process([
                $this->browserPath(),
                '--headless=new',
                '--disable-gpu',
                '--user-data-dir=' . $browserProfileDirectory,
                '--no-first-run',
                '--no-default-browser-check',
                '--no-pdf-header-footer',
                '--print-to-pdf=' . $pdfPath,
                $this->fileUrl($htmlPath),
            ]);

            $process->setTimeout(config('pdf.browser_timeout'));
            $process->setEnv([
                // PHP-FPM can run without a usable HOME directory. Modern Chrome
                // aborts its Crashpad handler in that case, so keep all browser
                // state inside this request's temporary directory.
                'HOME' => $browserHomeDirectory,
                'XDG_CONFIG_HOME' => $browserHomeDirectory . DIRECTORY_SEPARATOR . 'config',
                'XDG_CACHE_HOME' => $browserHomeDirectory . DIRECTORY_SEPARATOR . 'cache',
            ]);

            try {
                $process->run();
            } catch (ProcessSignaledException $exception) {
                $errorOutput = trim($process->getErrorOutput());

                throw new RuntimeException(
                    'The PDF renderer stopped unexpectedly.' . ($errorOutput !== '' ? ' ' . $errorOutput : ''),
                    previous: $exception,
                );
            }

            if (! $process->isSuccessful()) {
                throw new RuntimeException('The PDF renderer could not generate the document. ' . trim($process->getErrorOutput()));
            }

            if (! is_file($pdfPath) || filesize($pdfPath) === 0) {
                throw new RuntimeException('The PDF renderer did not create a PDF file.');
            }

            $pdf = File::get($pdfPath);

            if (! str_starts_with($pdf, '%PDF')) {
                throw new RuntimeException('The PDF renderer returned an invalid PDF file.');
            }

            return $pdf;
        } finally {
            File::deleteDirectory($temporaryDirectory);
        }
    }

    private function browserPath(): string
    {
        $configuredPath = config('pdf.browser_path');

        if (is_string($configuredPath) && $configuredPath !== '' && is_file($configuredPath)) {
            return $configuredPath;
        }

        foreach ($this->browserCandidates() as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            'A Chrome or Chromium executable is required for PDF downloads. '
            . 'Install Chrome/Chromium on this server and set PDF_BROWSER_PATH in the environment.'
        );
    }

    /**
     * @return array<int, string>
     */
    private function browserCandidates(): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return [
                'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
                'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
                'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
                'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            ];
        }

        return [
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
        ];
    }

    private function fileUrl(string $path): string
    {
        return 'file:///' . str_replace('\\', '/', $path);
    }
}
