<?php

declare(strict_types=1);

namespace App\Infrastructure\Reference;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Magasin des CSV DB2 téléchargés, distinct de `storage/app/blizzard/`.
 *
 * Le nom de fichier porte le build : deux synchronisations d'un même build écrivent le
 * même fichier, deux builds différents en laissent deux, ce qui donne son inventaire à
 * la purge du panneau d'administration.
 */
final class ReferenceStore
{
    public const DISK = 'reference';

    public function put(ReferenceTable $referenceTable, string $build, string $contents): int
    {
        $this->disk()->put($referenceTable->filename($build), $contents);

        return strlen($contents);
    }

    /**
     * @return resource
     */
    public function read(ReferenceTable $referenceTable, string $build)
    {
        $stream = $this->disk()->readStream($referenceTable->filename($build));

        if (! is_resource($stream)) {
            throw new \RuntimeException(sprintf('Fichier de référence %s illisible.', $referenceTable->filename($build)));
        }

        return $stream;
    }

    private function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }
}
