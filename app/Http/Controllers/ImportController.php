<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmImportRequest;
use App\Http\Requests\UploadFileRequest;
use App\Models\Transaction;
use App\Models\Workspace;
use App\Services\ImportService;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    public function create(Workspace $workspace, Request $request): Response
    {
        $this->authorize('create', [Transaction::class, $workspace]);

        $type = $request->routeIs('incomes.*') ? 'income' : 'expense';

        return inertia('Imports/Index', [
            'type' => $type,
        ]);
    }

    /**
     * Parse the uploaded file and show preview.
     */
    public function store(UploadFileRequest $request, Workspace $workspace): Response
    {
        $this->authorize('create', [Transaction::class, $workspace]);

        $type = (string) $request->validated('type');
        $file = $request->file('file');

        try {
            $parsed = $this->importService->parseFile($workspace, $file, $type);
        } catch (RuntimeException $e) {
            return inertia('Imports/Index', [
                'type' => $type,
                'preview' => [],
                'flash' => ['error' => 'Erro ao processar arquivo: '.$e->getMessage()],
            ]);
        }

        if (empty($parsed)) {
            return inertia('Imports/Index', [
                'type' => $type,
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
                'is_duplicate' => $item['is_duplicate'],
                'confirm_duplicate' => false,
            ];
        }, $parsed);

        return inertia('Imports/Index', [
            'type' => $type,
            'preview' => $preview,
        ]);
    }

    /**
     * Confirm and create transactions.
     */
    public function confirm(ConfirmImportRequest $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('create', [Transaction::class, $workspace]);

        $validated = $request->validated();
        $type = $validated['type'];
        $items = $validated['items'];

        $count = $this->importService->createTransactions(
            $workspace,
            $request->user(),
            $items,
            $type,
        );

        $label = $type === 'income' ? 'receitas' : 'despesas';

        Toast::success("{$count} {$label} importadas com sucesso.");

        $route = $type === 'income' ? 'incomes.index' : 'transactions.index';

        $month = ! empty($items) ? substr($items[0]['date'], 0, 7) : null;

        return $month
            ? redirect()->route($route, ['workspace' => $workspace, 'month' => $month])
            : redirect()->route($route, $workspace);
    }
}
