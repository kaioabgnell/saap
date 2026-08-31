<?php

declare(strict_types=1);

/**
 * Extrai o texto de um PDF para os testes conferirem o conteúdo gerado.
 *
 * Usa o pymupdf do venv de ferramentas (o mesmo da F1/F4) em vez de procurar
 * a string bruta no PDF: o dompdf comprime os streams de conteúdo por
 * padrão, então uma busca ingênua nos bytes não encontraria nada.
 */
function extrairTextoDoPdf(string $conteudoBinario): string
{
    $temp = tempnam(sys_get_temp_dir(), 'saap-pdf-').'.pdf';
    file_put_contents($temp, $conteudoBinario);

    $python = base_path('.venv-tools/bin/python');
    $script = <<<'PY'
import sys, pymupdf
doc = pymupdf.open(sys.argv[1])
print("\n".join(p.get_text() for p in doc))
PY;

    $scriptPath = tempnam(sys_get_temp_dir(), 'saap-pdf-script-').'.py';
    file_put_contents($scriptPath, $script);

    $comando = escapeshellarg($python).' '.escapeshellarg($scriptPath).' '.escapeshellarg($temp);
    $texto = shell_exec($comando) ?? '';

    unlink($temp);
    unlink($scriptPath);

    return $texto;
}
