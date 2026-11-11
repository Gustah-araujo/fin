<?php

declare(strict_types=1);

namespace Tests\Feature\Fin;

use App\Models\Workspace;
use App\Services\FinAttachmentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\File;
use Laravel\Ai\Files\StoredDocument;
use Laravel\Ai\Files\StoredImage;
use Tests\TestCase;

class FinAttachmentServiceTest extends TestCase
{
    private Workspace $workspace;

    private FinAttachmentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->service = app(FinAttachmentService::class);

        Storage::fake('local');
    }

    public function test_store_returns_stored_image_for_image_files(): void
    {
        foreach (['png', 'jpg', 'jpeg', 'webp'] as $extension) {
            $file = UploadedFile::fake()->create("statement.{$extension}");

            $stored = $this->service->store($this->workspace, $file);

            $this->assertInstanceOf(StoredImage::class, $stored);
            $this->assertSame('stored-image', $stored->toArray()['type']);
        }
    }

    public function test_store_returns_stored_document_for_document_files(): void
    {
        foreach (['pdf', 'csv', 'xlsx', 'xls', 'txt'] as $extension) {
            $file = UploadedFile::fake()->create("statement.{$extension}");

            $stored = $this->service->store($this->workspace, $file);

            $this->assertInstanceOf(StoredDocument::class, $stored);
            $this->assertSame('stored-document', $stored->toArray()['type']);
        }
    }

    public function test_store_persists_file_on_local_disk_under_workspace_directory(): void
    {
        $file = UploadedFile::fake()->create('statement.pdf', 100);

        $stored = $this->service->store($this->workspace, $file);

        $this->assertStringStartsWith("fin/{$this->workspace->uuid}/", $stored->path);
        $this->assertTrue(Storage::disk('local')->exists($stored->path));
        $this->assertSame('local', $stored->disk);
    }

    public function test_store_preserves_original_file_name(): void
    {
        $file = UploadedFile::fake()->create('extrato-nubank.pdf', 100);

        $stored = $this->service->store($this->workspace, $file);

        $this->assertSame('extrato-nubank.pdf', $stored->name());
    }

    public function test_serialize_produces_stored_file_array_format(): void
    {
        $file = UploadedFile::fake()->create('statement.pdf', 100);
        $stored = $this->service->store($this->workspace, $file);

        $metadata = $this->service->serialize($stored);

        $this->assertSame([
            'type' => 'stored-document',
            'name' => 'statement.pdf',
            'path' => $stored->path,
            'disk' => 'local',
        ], $metadata);
    }

    public function test_serialize_then_hydrate_round_trip_preserves_metadata(): void
    {
        $pdf = UploadedFile::fake()->create('statement.pdf', 100);
        $image = UploadedFile::fake()->image('receipt.png');

        foreach ([$this->service->store($this->workspace, $pdf), $this->service->store($this->workspace, $image)] as $stored) {
            $metadata = $this->service->serialize($stored);
            $hydrated = $this->service->hydrate($metadata);

            $this->assertInstanceOf($stored::class, $hydrated);
            $this->assertInstanceOf(File::class, $hydrated);
            $this->assertSame($stored->name(), $hydrated->name());
            $this->assertSame($stored->path, $hydrated->path);
            $this->assertSame($stored->disk, $hydrated->disk);
            $this->assertSame($stored->toArray(), $hydrated->toArray());
        }
    }

    public function test_hydrate_returns_null_for_invalid_metadata(): void
    {
        $this->assertNull($this->service->hydrate([]));
        $this->assertNull($this->service->hydrate(['type' => 'unknown-kind']));
        $this->assertNull($this->service->hydrate(['type' => 'stored-image']));
        $this->assertNull($this->service->hydrate(['type' => 'stored-document']));
    }
}
