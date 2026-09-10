<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmImportRequest;
use App\Http\Requests\UploadCsvRequest;
use App\Models\Transaction;
use App\Models\Workspace;
use App\Services\ImportService;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Response;

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
     * Parse CSV and show preview.
     */
    public function store(UploadCsvRequest $request, Workspace $workspace): Response
    {
        $this->authorize('create', [Transaction::class, $workspace]);

        $type = (string) $request->validated('type');
        $file = $request->file('file');
        $csvContent = file_get_contents($file->getRealPath());

        $parsed = $this->importService->parseCsv($csvContent, $type);

        if (empty($parsed)) {
            return inertia('Imports/Index', [
                'type' => $type,
                'preview' => [],
                'flash' => ['error' => 'Nenhuma transação encontrada no arquivo.'],
            ]);
        }

        $withDuplicates = $this->importService->detectDuplicates($workspace, $parsed, $type);

        $preview = array_map(function (array $item): array {
            return [
                'id' => Str::uuid()->toString(),
                'description' => $item['original']['description'],
                'value' => $item['original']['value'],
                'date' => $item['original']['date'],
                'type' => $item['original']['type'],
                'category_name' => $item['original']['category_name'],
                'is_duplicate' => $item['is_duplicate'],
                'confirm_duplicate' => false,
            ];
        }, $withDuplicates);

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

        return redirect()->route($route, $workspace);
    }
}
