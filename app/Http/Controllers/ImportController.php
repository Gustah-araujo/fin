<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmImportRequest;
use App\Http\Requests\UploadFileRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\TagResource;
use App\Models\Transaction;
use App\Models\Workspace;
use App\Services\ImportService;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use RuntimeException;

class ImportController extends Controller
{
    public function __construct(
        private readonly ImportService $importService,
    ) {}

    /**
     * Show the upload form.
     */
    public function create(Workspace $workspace): Response
    {
        $this->authorize('create', [Transaction::class, $workspace]);

        return inertia('Imports/Index', [
            'categories' => CategoryResource::collection(
                $workspace->categories()->orderBy('name')->get()
            ),
            'tags' => TagResource::collection(
                $workspace->tags()->orderBy('name')->get()
            ),
        ]);
    }

    /**
     * Parse the uploaded file and show preview.
     */
    public function store(UploadFileRequest $request, Workspace $workspace): Response
    {
        $this->authorize('create', [Transaction::class, $workspace]);

        $file = $request->file('file');

        try {
            $parsed = $this->importService->parseFile($workspace, $file);
        } catch (RuntimeException $e) {
            return inertia('Imports/Index', [
                'preview' => [],
                'flash' => ['error' => 'Erro ao processar arquivo: '.$e->getMessage()],
            ]);
        }

        if (empty($parsed)) {
            return inertia('Imports/Index', [
                'preview' => [],
                'flash' => ['error' => 'Nenhuma transação encontrada no arquivo.'],
            ]);
        }

        $preview = array_map(function (array $item): array {
            return [
                'id' => $item['uuid'],
                'description' => $item['description'],
                'value' => $item['value'],
                'date' => $item['date'],
                'type' => $item['type'],
                'category_name' => $item['category_name'],
                'category_uuid' => $item['category_uuid'],
                'tags' => $item['tags'],
                'tag_names' => $item['tag_names'] ?? [],
                'is_duplicate' => $item['is_duplicate'],
                'confirm_duplicate' => false,
            ];
        }, $parsed);

        return inertia('Imports/Index', [
            'preview' => $preview,
        ]);
    }

    /**
     * Confirm and create transactions.
     */
    public function confirm(ConfirmImportRequest $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('create', [Transaction::class, $workspace]);

        $items = $request->validated('items');

        $count = $this->importService->createTransactions(
            $workspace,
            $request->user(),
            $items,
        );

        Toast::success("{$count} transações importadas com sucesso.");

        return redirect()->route('dashboard', $workspace);
    }
}
