<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Ai\Files\File;
use Laravel\Ai\Files\StoredDocument;
use Laravel\Ai\Files\StoredImage;

class FinAttachmentService
{
    private const DISK = 'local';

    /**
     * Image extensions persisted as StoredImage attachments.
     */
    private const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp'];

    /**
     * Document extensions persisted as StoredDocument attachments.
     */
    private const DOCUMENT_EXTENSIONS = ['pdf', 'csv', 'xlsx', 'xls', 'txt'];

    /**
     * Persist an uploaded file on the local disk, scoped to the workspace,
     * and return the corresponding SDK file instance.
     *
     * @throws InvalidArgumentException When the file extension is not supported.
     */
    public function store(Workspace $workspace, UploadedFile $file): File
    {
        $extension = strtolower($file->getClientOriginalExtension());

        $stored = match (true) {
            in_array($extension, self::IMAGE_EXTENSIONS, true) => $this->storeAs($workspace, $file, $extension, StoredImage::class),
            in_array($extension, self::DOCUMENT_EXTENSIONS, true) => $this->storeAs($workspace, $file, $extension, StoredDocument::class),
            default => throw new InvalidArgumentException("Unsupported attachment type: {$extension}."),
        };

        return $stored->as($file->getClientOriginalName());
    }

    /**
     * Serialize an SDK file into its persisted metadata representation.
     *
     * @return array{type: string, name: string|null, path: string, disk: string}
     */
    public function serialize(File $file): array
    {
        return $file->toArray();
    }

    /**
     * Rebuild an SDK file instance from persisted metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function hydrate(array $metadata): ?File
    {
        try {
            return File::fromArray($metadata);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function storeAs(Workspace $workspace, UploadedFile $file, string $extension, string $fileClass): StoredImage|StoredDocument
    {
        $path = $file->storeAs(
            "fin/{$workspace->uuid}",
            Str::orderedUuid()->toString().'.'.$extension,
            self::DISK,
        );

        return new $fileClass($path, self::DISK);
    }
}
