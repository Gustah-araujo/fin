<?php

declare(strict_types=1);

namespace Tests\Feature\Fin;

use App\Enums\WorkspaceRole;
use App\Http\Requests\StoreFinMessageRequest;
use App\Jobs\DispatchFinAssistantJob;
use App\Models\User;
use App\Models\Workspace;
use App\Services\FinAssistantService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Validator;
use Tests\TestCase;

class StoreFinMessageRequestTest extends TestCase
{
    private User $user;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->members()->attach($this->user, ['role' => WorkspaceRole::Admin->value]);
    }

    private function validate(array $data): Validator
    {
        $request = new StoreFinMessageRequest;

        $validator = ValidatorFacade::make(
            $data,
            $request->rules(),
            $request->messages(),
        );

        $request->withValidator($validator);

        return $validator;
    }

    public function test_accepts_message_with_content_alone(): void
    {
        $validator = $this->validate(['content' => 'Analise meu extrato']);

        $this->assertTrue($validator->passes());
    }

    public function test_accepts_message_with_attachments_alone_for_every_allowed_type(): void
    {
        $documents = ['statement.pdf', 'table.csv', 'sheet.xlsx', 'legacy.xls', 'notes.txt'];

        foreach ($documents as $name) {
            $file = UploadedFile::fake()->create($name, 100, 'application/pdf');

            $this->assertTrue($this->validate(['attachments' => [$file]])->passes());
        }

        foreach (['png', 'jpg', 'jpeg', 'webp'] as $extension) {
            $image = UploadedFile::fake()->image("receipt.{$extension}");

            $this->assertTrue($this->validate(['attachments' => [$image]])->passes());
        }
    }

    public function test_accepts_message_with_content_and_up_to_five_attachments(): void
    {
        $files = [
            UploadedFile::fake()->create('statement.pdf', 100),
            UploadedFile::fake()->create('table.csv', 100),
            UploadedFile::fake()->image('receipt.png'),
            UploadedFile::fake()->image('photo.jpg'),
            UploadedFile::fake()->create('notes.txt', 100),
        ];

        $validator = $this->validate(['content' => 'Analise os anexos', 'attachments' => $files]);

        $this->assertTrue($validator->passes());
    }

    public function test_rejects_attachment_with_disallowed_extension(): void
    {
        foreach (['virus.exe', 'page.html'] as $name) {
            $file = UploadedFile::fake()->create($name, 100);

            $validator = $this->validate(['content' => 'Veja o anexo', 'attachments' => [$file]]);

            $this->assertTrue($validator->fails());
            $this->assertTrue($validator->errors()->has('attachments.0'));
        }
    }

    public function test_rejects_attachment_larger_than_10mb(): void
    {
        $file = UploadedFile::fake()->create('huge.pdf', 10241);

        $validator = $this->validate(['attachments' => [$file]]);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('attachments.0'));
    }

    public function test_rejects_more_than_five_attachments(): void
    {
        $files = [
            UploadedFile::fake()->create('a.pdf', 100),
            UploadedFile::fake()->create('b.pdf', 100),
            UploadedFile::fake()->create('c.pdf', 100),
            UploadedFile::fake()->create('d.pdf', 100),
            UploadedFile::fake()->create('e.pdf', 100),
            UploadedFile::fake()->create('f.pdf', 100),
        ];

        $validator = $this->validate(['attachments' => $files]);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('attachments'));
    }

    public function test_rejects_message_without_content_and_attachments(): void
    {
        foreach ([['content' => ''], ['content' => '   '], []] as $data) {
            $validator = $this->validate($data);

            $this->assertTrue($validator->fails());
            $this->assertTrue($validator->errors()->has('content'));
        }
    }

    public function test_content_only_post_still_creates_message(): void
    {
        Queue::fake();

        $service = app(FinAssistantService::class);
        $conversation = $service->createConversation($this->workspace, $this->user, 'Nova conversa');

        $response = $this->actingAs($this->user)
            ->postJson(route('fin.conversations.messages.store', [$this->workspace, $conversation]), ['content' => 'Olá']);

        $response->assertStatus(201);

        Queue::assertPushed(DispatchFinAssistantJob::class);
    }
}
