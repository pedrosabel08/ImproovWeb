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
        // Se não for gravável, tentar fallback para temp dir
        if (!is_writable($fontCacheDir)) {
            $fallback = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'improov_dompdf';
            $this->ensureDir($fallback);
            if (is_writable($fallback)) {
                $fontCacheDir = $fallback;
            } else {
                throw new RuntimeException('Dompdf font cache não gravável: ' . $fontCacheDir);
            }
        }

        $options = new Options();
        // Evita downloads externos (ex.: Google Fonts) e reduz o tempo de geração
        $options->set('isRemoteEnabled', false);
        $options->set('chroot', $contratosRoot);
        $options->set('defaultFont', 'Roboto');
        // Subsetting pode aumentar tempo; desabilitar prioriza performance
        $options->set('isFontSubsettingEnabled', false);
        $options->set('fontDir', $fontCacheDir);
        $options->set('fontCache', $fontCacheDir);
        $options->set('tempDir', $fontCacheDir);

        $dompdf = new Dompdf($options);
        $dompdf->setBasePath($templateDir);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        // Definir título do PDF (aparece no viewer)
        $tituloPdf = pathinfo($nomeArquivo, PATHINFO_FILENAME);
        $canvas = $dompdf->getCanvas();
        if ($canvas && method_exists($canvas, 'get_cpdf')) {
            $cpdf = $canvas->get_cpdf();
            if ($cpdf && method_exists($cpdf, 'setTitle')) {
                $cpdf->setTitle($tituloPdf);
            }
        }

        // Garantir que o diretório de saída seja gravável; fallback para temp dir se necessário
        if (!is_dir($this->outputDir)) {
            $this->ensureOutputDir();
        }
        if (!is_writable($this->outputDir)) {
            $fallbackOut = sys_get_temp_dir();
            if (!is_writable($fallbackOut)) {
                throw new RuntimeException('Diretório de saída de PDFs não gravável: ' . $this->outputDir);
            }
            $this->outputDir = $fallbackOut;
        }

        $filePath = $this->getAvailableFilePath($nomeArquivo);
        $bytes = $this->writeWithRetry($filePath, $dompdf->output());
        if ($bytes === false) {
            throw new RuntimeException('Falha ao gravar PDF em: ' . $filePath);
        }

        return [
            'file_name' => basename($filePath),
            'file_path' => $filePath,
        ];
    }

    private function getAvailableFilePath(string $nomeArquivo): string
    {
        $basePath = $this->outputDir . DIRECTORY_SEPARATOR . $nomeArquivo;
        if (!file_exists($basePath)) {
            return $basePath;
        }

        $info = pathinfo($basePath);
        $dir = $info['dirname'] ?? $this->outputDir;
        $name = $info['filename'] ?? 'arquivo';
        $ext = isset($info['extension']) ? ('.' . $info['extension']) : '';

        for ($i = 1; $i <= 50; $i++) {
            $candidate = $dir . DIRECTORY_SEPARATOR . $name . '_' . $i . $ext;
            if (!file_exists($candidate)) {
                return $candidate;
            }
        }

        // fallback: timestamp
        return $dir . DIRECTORY_SEPARATOR . $name . '_' . time() . $ext;
    }

    private function writeWithRetry(string $filePath, string $content)
    {
        $attempts = 3;
        for ($i = 0; $i < $attempts; $i++) {
            $bytes = @file_put_contents($filePath, $content, LOCK_EX);
            if ($bytes !== false) {
                return $bytes;
            }
            // small backoff to avoid temporary lock issues
            usleep(150000); // 150ms
        }
        return false;
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
