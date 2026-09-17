<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\AutoEncoder;
use Intervention\Image\ImageManager;
use InvalidArgumentException;

/**
 * Upload da logo da clínica, exibida no cabeçalho do relatório em PDF.
 *
 * Não reaproveita o `ImageUploader` de propósito: aquele força JPEG e recorta
 * a miniatura em quadrado, o que é certo para uma foto de rosto e errado para
 * uma logo — um PNG com fundo transparente sairia com fundo sólido, e uma
 * marca larga (wordmark) sairia cortada. Aqui o `AutoEncoder` devolve o
 * mesmo formato do arquivo enviado, preservando a transparência.
 *
 * Disco `public`: não é dado sensível de criança, é material de marca que o
 * próprio psicólogo escolhe — mesmo regime da foto de perfil.
 */
final class ClinicLogoUploader
{
    private const MAX_BYTES = 2 * 1024 * 1024; // 2 MB — é logotipo, não fotografia

    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    // O cabeçalho do PDF desenha a logo com no máximo 175px de largura, o que
    // a 300 dpi pede ~550px de origem. 720 dá folga para a impressão sem
    // inchar o laudo: a imagem vai embutida em base64 no payload de todo
    // laudo emitido — ver BuildReportPayload::logoEmBase64().
    private const MAX_SIDE = 720;

    public function __construct(private readonly ImageManager $manager) {}

    /** Salva a logo redimensionada, no formato original; remove a anterior. */
    public function store(UploadedFile $file, ?string $previousLogoPath = null): string
    {
        $this->assertValid($file);

        $imagem = $this->manager->decodePath($file->getPathname());
        $imagem->scaleDown(width: self::MAX_SIDE, height: self::MAX_SIDE);

        $extensao = match ($imagem->origin()->mediaType()) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $path = sprintf('logos-clinica/%s.%s', Str::uuid(), $extensao);

        Storage::disk('public')->put($path, (string) $imagem->encode(new AutoEncoder));

        if ($previousLogoPath !== null) {
            Storage::disk('public')->delete($previousLogoPath);
        }

        return $path;
    }

    public function delete(string $logoPath): void
    {
        Storage::disk('public')->delete($logoPath);
    }

    private function assertValid(UploadedFile $file): void
    {
        if (! in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            throw new InvalidArgumentException('Formato de imagem não suportado. Envie JPEG, PNG ou WebP.');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new InvalidArgumentException('A imagem excede o limite de 2 MB.');
        }
    }
}
