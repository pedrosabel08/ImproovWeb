<?php

/** PDF original, sem rasterização ou merge. Reutiliza Poppler/FPDI do projeto. */
function mp_pdf_metadata(string $path): array
{
    if ((new finfo(FILEINFO_MIME_TYPE))->file($path) !== 'application/pdf') {
        throw new InvalidArgumentException('Selecione um arquivo PDF válido.');
    }
    $binary = getenv('IMPROOV_PDFINFO_BINARY') ?: (PHP_OS_FAMILY === 'Windows'
        ? __DIR__ . '/../tools/poppler/poppler-25.12.0/Library/bin/pdfinfo.exe' : 'pdfinfo');
    if (function_exists('proc_open') && (PHP_OS_FAMILY !== 'Windows' || is_file($binary))) {
        $process = @proc_open(
            [$binary, '-f', '1', '-l', '1', $path],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            null,
            ['bypass_shell' => true]
        );
        if (is_resource($process)) {
            fclose($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $output = '';
            $errors = '';
            $deadline = microtime(true) + 15;
            do {
                $output .= stream_get_contents($pipes[1]);
                $errors .= stream_get_contents($pipes[2]);
                $status = proc_get_status($process);
                if (!$status['running']) {
                    break;
                }
                if (microtime(true) > $deadline) {
                    proc_terminate($process);
                    break;
                }
                usleep(20000);
            } while (true);
            $output .= stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            if (preg_match('/^Encrypted:\s+yes/im', $output)) {
                throw new InvalidArgumentException('Envie um PDF sem senha ou criptografia.');
            }
            if (preg_match('/^Pages:\s+(\d+)/im', $output, $count)) {
                if (preg_match('/^Page(?:\s+1)?\s+size:\s+([\d.]+)\s+x\s+([\d.]+)/im', $output, $size)) {
                    preg_match('/^Page(?:\s+1)?\s+rot:\s+(\d+)/im', $output, $rotation);
                    if ((float) $size[1] > 0 && (float) $size[2] > 0) {
                        return ['pages' => (int) $count[1], 'width' => (float) $size[1], 'height' => (float) $size[2], 'rotation' => (int) ($rotation[1] ?? 0)];
                    }
                }
            }
        }
    }
    require_once __DIR__ . '/../vendor/autoload.php';
    try {
        $pdf = new \setasign\Fpdi\Fpdi();
        $pages = $pdf->setSourceFile($path);
        $size = $pdf->getTemplateSize($pdf->importPage(1));
        return ['pages' => $pages, 'width' => $size['width'] * 72 / 25.4, 'height' => $size['height'] * 72 / 25.4, 'rotation' => 0];
    } catch (InvalidArgumentException $e) {
        throw $e;
    } catch (Throwable $e) {
        throw new InvalidArgumentException('Não foi possível validar este PDF. Exporte novamente o arquivo sem senha e tente outra vez.');
    }
}

function mp_pdf_storage_directory(): string
{
    return __DIR__ . '/../uploads/referencias_arquitetonicas';
}

function mp_pdf_local_path(string $relative): string
{
    $root = realpath(mp_pdf_storage_directory());
    $file = realpath(__DIR__ . '/../' . $relative);
    if (!$root || !$file || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) {
        throw new RuntimeException('O PDF desta referência não foi encontrado.', 404);
    }
    return $file;
}

function mp_pdf_stream(string $relative): never
{
    $file = mp_pdf_local_path($relative);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="planta.pdf"');
    header('Content-Length: ' . filesize($file));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($file);
    exit;
}
