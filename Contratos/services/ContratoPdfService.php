<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class ContratoPdfService
{
    private string $outputDir;
    private string $templatePath;

    public function __construct(string $outputDir, ?string $templatePath = null)
    {
        $this->outputDir = rtrim($outputDir, '/\\');
        $this->templatePath = $templatePath ?: (__DIR__ . '/../templates/contrato_modelo.html');
    }

    public function gerarPdf(string $nomeArquivo, array $placeholders): array
    {
        $this->ensureOutputDir();

        $template = file_exists($this->templatePath)
            ? file_get_contents($this->templatePath)
            : '';

        if ($template === '') {
            throw new RuntimeException('Template do contrato não encontrado.');
        }

        $html = $this->applyTemplate($template, $placeholders);

        $contratosRoot = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
        $templateDir = realpath(dirname($this->templatePath)) ?: dirname($this->templatePath);
        $fontCacheDir = $contratosRoot . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'dompdf';
        $this->ensureDir($fontCacheDir);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('chroot', $contratosRoot);
        $options->set('defaultFont', 'Roboto');
        $options->set('isFontSubsettingEnabled', true);
        $options->set('fontDir', $fontCacheDir);
        $options->set('fontCache', $fontCacheDir);
        $options->set('tempDir', $fontCacheDir);

        $dompdf = new Dompdf($options);
        $dompdf->setBasePath($templateDir);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        $filePath = $this->outputDir . DIRECTORY_SEPARATOR . $nomeArquivo;
        file_put_contents($filePath, $dompdf->output());

        return [
            'file_name' => $nomeArquivo,
            'file_path' => $filePath,
        ];
    }

    private function ensureOutputDir(): void
    {
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0775, true);
        }
    }

    private function ensureDir(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }

    private function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function applyTemplate(string $template, array $placeholders): string
    {
        $replacements = [];
        foreach ($placeholders as $key => $value) {
            $replacements['{{' . $key . '}}'] = (string)$value;
        }
        return strtr($template, $replacements);
    }
}
