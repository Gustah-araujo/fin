<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\TagResource;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Models\Workspace;
use App\Services\Datatable\DatatableConfig;
use App\Services\Datatable\DatatableService;
use App\Services\Datatable\Filter;
use App\Services\RecurrenceService;
use App\Services\TransactionService;
use App\Support\Toast;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class TransactionController extends Controller
{
    public function index(Workspace $workspace): Response
    {
        $this->authorize('viewAny', [Transaction::class, $workspace]);

        return inertia('Transactions/Index', [
            'accounts' => AccountResource::collection($workspace->accounts()->orderBy('name')->get()),
            'categories' => CategoryResource::collection($workspace->categories()->orderBy('name')->get()),
            'tags' => TagResource::collection($workspace->tags()->orderBy('name')->get()),
        ]);
    }

    public function datatable(Workspace $workspace, Request $request): JsonResponse
    {
        $this->authorize('viewAny', [Transaction::class, $workspace]);

        $query = $workspace->transactions()
            ->where('type', TransactionType::Expense)
            ->with(['account', 'category', 'tags']);

        return app(DatatableService::class)->paginate($query, $request, $this->datatableConfig());
    }

    private function datatableConfig(): DatatableConfig
    {
        return DatatableConfig::make(TransactionResource::class)
            ->filter('description', Filter::text('description'))
            ->filter('value', Filter::numberRange('value'))
            ->filter('date', Filter::dateRange('date'))
            ->filter('category', Filter::relation('category', 'uuid'))
            ->filter('account', Filter::relation('account', 'uuid'))
            ->filter('status', Filter::select(fn (Builder $q, string $v) => $v === 'paid' ? $q->whereNotNull('paid_at') : $q->whereNull('paid_at')))
            ->sortable(['date', 'value', 'description'])
            ->defaultSort('date', 'desc')
            ->perPage(25);
    }

    public function create(Workspace $workspace): Response
    {
        $this->authorize('create', [Transaction::class, $workspace]);

        return inertia('Transactions/Create', [
            'accounts' => AccountResource::collection($workspace->accounts()->orderBy('name')->get()),
            'categories' => CategoryResource::collection(
                $workspace->categories()
                    ->whereIn('type', [TransactionType::Expense->value, TransactionType::Both->value])
                    ->orderBy('name')
                    ->get()
            ),
            'tags' => TagResource::collection($workspace->tags()->orderBy('name')->get()),
        ]);
    }

    public function store(
        StoreTransactionRequest $request,
        Workspace $workspace,
        TransactionService $transactionService,
        RecurrenceService $recurrenceService,
    ): RedirectResponse {
        $this->authorize('create', [Transaction::class, $workspace]);

        $data = $request->validated();

        if ($request->boolean('is_recurring')) {
            $data['type'] = TransactionType::Expense->value;
            $data['start_date'] = $data['date'];

            if (Carbon::parse($data['date'])->lte(Carbon::today())) {
                $recurrenceService->createWithFirstInstance($workspace, $data, $request->user());
            } else {
                $recurrenceService->create($workspace, $data, $request->user());
            }

            Toast::success('Recorrência criada com sucesso.');

            return redirect()->route('transactions.index', $workspace);
        } else {
            $data['type'] = TransactionType::Expense->value;
            $transactionService->create($workspace, $request->user(), $data);

            Toast::success('Despesa criada com sucesso.');

            return redirect()->route('transactions.index', $workspace);
        }
    }

    public function edit(Workspace $workspace, Transaction $transaction): Response
    {
        abort_if($transaction->workspace_id !== $workspace->id, 404);

        $this->authorize('update', [$transaction, $workspace]);

        $transaction->load(['account', 'category', 'tags']);

        return inertia('Transactions/Edit', [
            'transaction' => new TransactionResource($transaction),
            'accounts' => AccountResource::collection($workspace->accounts()->orderBy('name')->get()),
            'categories' => CategoryResource::collection(
                $workspace->categories()
                    ->whereIn('type', [TransactionType::Expense->value, TransactionType::Both->value])
                    ->orderBy('name')
                    ->get()
            ),
            'tags' => TagResource::collection($workspace->tags()->orderBy('name')->get()),
        ]);
    }

    public function update(UpdateTransactionRequest $request, Workspace $workspace, Transaction $transaction, TransactionService $transactionService): RedirectResponse
    {
        abort_if($transaction->workspace_id !== $workspace->id, 404);

        $this->authorize('update', [$transaction, $workspace]);

        $transactionService->update($transaction, $request->validated());

        Toast::success('Despesa atualizada com sucesso.');

        return redirect()->route('transactions.index', $workspace);
    }

    public function destroy(Workspace $workspace, Transaction $transaction, TransactionService $transactionService): RedirectResponse
    {
        abort_if($transaction->workspace_id !== $workspace->id, 404);

        $this->authorize('delete', [$transaction, $workspace]);

        $transactionService->archive($transaction);

        Toast::success('Despesa arquivada com sucesso.');

        return redirect()->route('transactions.index', $workspace);
    }

    public function pay(Workspace $workspace, Transaction $transaction, TransactionService $transactionService): RedirectResponse
    {
        abort_if($transaction->workspace_id !== $workspace->id, 404);

        $this->authorize('update', [$transaction, $workspace]);

        $transactionService->pay($transaction);

        Toast::success('Despesa marcada como paga.');

        return redirect()->back();
    }

    public function unpay(Workspace $workspace, Transaction $transaction, TransactionService $transactionService): RedirectResponse
    {
        abort_if($transaction->workspace_id !== $workspace->id, 404);

        $this->authorize('update', [$transaction, $workspace]);

        $transactionService->unpay($transaction);

        Toast::success('Despesa marcada como não paga.');

        return redirect()->back();
    }
}
