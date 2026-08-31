<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use InvalidArgumentException;

/**
 * Upload e redimensionamento de fotos de perfil e de aprendizes.
 *
 * Fotos de aprendizes são dado sensível de criança — ver 00-visao-geral.md.
 * Por isso este serviço aceita o disco como parâmetro: fotos de psicólogo vão
 * para o disco `public` (menu, exibição direta), fotos de aprendiz vão para o
 * disco `local` (privado), sem symlink, servidas só pela rota assinada em
 * LearnerPhotoController.
 *
 * A miniatura não tem coluna própria no banco: seu caminho é derivado do
 * caminho da foto por convenção — sufixo "-thumb.jpg" no lugar da extensão.
 * Ver thumbnailPathFor().
 */
final class ImageUploader
{
    private const MAX_BYTES = 5 * 1024 * 1024; // 5 MB

    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    private const MAX_SIDE = 800;

    private const THUMB_SIDE = 160;

    public function __construct(private readonly ImageManager $manager) {}

    /**
     * Salva a foto redimensionada e sua miniatura; remove o par anterior.
     * Devolve o caminho da foto — a miniatura está em thumbnailPathFor().
     */
    public function store(
        UploadedFile $file,
        string $disk,
        string $directory,
        ?string $previousPhotoPath = null,
    ): string {
        $this->assertValid($file);

        $original = $this->manager->decodePath($file->getPathname());

        $full = clone $original;
        $full->scaleDown(width: self::MAX_SIDE, height: self::MAX_SIDE);

        $thumb = clone $original;
        $thumb->cover(self::THUMB_SIDE, self::THUMB_SIDE);

        $slug = (string) Str::uuid();
        $photoPath = "{$directory}/{$slug}.jpg";
        $thumbnailPath = self::thumbnailPathFor($photoPath);

        Storage::disk($disk)->put($photoPath, (string) $full->encode(new JpegEncoder(quality: 85)));
        Storage::disk($disk)->put($thumbnailPath, (string) $thumb->encode(new JpegEncoder(quality: 85)));

        if ($previousPhotoPath !== null) {
            $this->delete($disk, $previousPhotoPath);
        }

        return $photoPath;
    }

    public function delete(string $disk, string $photoPath): void
    {
        Storage::disk($disk)->delete([$photoPath, self::thumbnailPathFor($photoPath)]);
    }

    public static function thumbnailPathFor(string $photoPath): string
    {
        $semExtensao = (string) preg_replace('/\.\w+$/', '', $photoPath);

        return "{$semExtensao}-thumb.jpg";
    }

    private function assertValid(UploadedFile $file): void
    {
        if (! in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            throw new InvalidArgumentException('Formato de imagem não suportado. Envie JPEG, PNG ou WebP.');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new InvalidArgumentException('A imagem excede o limite de 5 MB.');
        }
    }
}
