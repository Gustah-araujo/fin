<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Controllers\Concerns\PersistsTableState;
use App\Http\Requests\StoreIncomeRequest;
use App\Http\Requests\UpdateIncomeRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\TagResource;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Models\Workspace;
use App\Services\Datatable\DatatableConfig;
use App\Services\Datatable\DatatableService;
use App\Services\Datatable\Filter;
use App\Services\Datatable\TableStateService;
use App\Services\RecurrenceService;
use App\Services\TransactionService;
use App\Support\Toast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class IncomeController extends Controller
{
    use PersistsTableState;

    public function index(Workspace $workspace): Response
    {
        $this->authorize('viewAny', [Transaction::class, $workspace]);

        $state = app(TableStateService::class)->restore(
            request(), 'incomes'
        );

        return inertia('Incomes/Index', [
            'accounts' => AccountResource::collection($workspace->accounts()->orderBy('name')->get()),
            'categories' => CategoryResource::collection(
                $workspace->categories()
                    ->whereIn('type', [TransactionType::Income->value, TransactionType::Both->value])
                    ->orderBy('name')
                    ->get()
            ),
            'tags' => TagResource::collection($workspace->tags()->orderBy('name')->get()),
            'initialState' => $state,
        ]);
    }

    public function datatable(Workspace $workspace, Request $request): JsonResponse
    {
        $this->authorize('viewAny', [Transaction::class, $workspace]);

        $state = $this->resolveTableState($request, 'incomes', $this->datatableConfig());

        $request->merge([
            'sort' => $state['sort'],
            'direction' => $state['direction'],
            ...$state['filters'],
        ]);

        $query = $workspace->transactions()
            ->where('type', TransactionType::Income)
            ->with(['account', 'category', 'tags', 'recurrence']);

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
            ->filter('origin', Filter::select(fn (Builder $q, string $v) => $v === 'recurring' ? $q->whereNotNull('recurrence_id') : $q->whereNull('recurrence_id')))
            ->filter('recurrence', Filter::relation('recurrence', 'uuid'))
            ->sortable(['date', 'value', 'description'])
            ->defaultSort('date', 'desc')
            ->perPage(25);
    }

    public function create(Workspace $workspace): Response
    {
        $this->authorize('create', [Transaction::class, $workspace]);

        return inertia('Incomes/Create', [
            'accounts' => AccountResource::collection($workspace->accounts()->orderBy('name')->get()),
            'categories' => CategoryResource::collection(
                $workspace->categories()
                    ->whereIn('type', [TransactionType::Income->value, TransactionType::Both->value])
                    ->orderBy('name')
                    ->get()
            ),
            'tags' => TagResource::collection($workspace->tags()->orderBy('name')->get()),
        ]);
    }

    public function store(StoreIncomeRequest $request, Workspace $workspace, TransactionService $transactionService, RecurrenceService $recurrenceService): RedirectResponse
    {
        $this->authorize('create', [Transaction::class, $workspace]);

        $data = $request->validated();

        if ($request->boolean('is_recurring')) {
            $data['start_date'] = $data['date'];
            $data['type'] = TransactionType::Income->value;
            $recurrenceService->createWithBuffer($workspace, $data, $request->user());

            Toast::success('Recorrência criada com sucesso.');

            return redirect()->route('incomes.index', $workspace);
        } else {
            $data['type'] = TransactionType::Income->value;
            $transactionService->create($workspace, $request->user(), $data);

            Toast::success('Receita criada com sucesso.');

            return redirect()->route('incomes.index', $workspace);
        }
    }

    public function edit(Workspace $workspace, Transaction $transaction): Response
    {
        abort_if($transaction->workspace_id !== $workspace->id, 404);

        $this->authorize('update', [$transaction, $workspace]);

        $transaction->load(['account', 'category', 'tags', 'recurrence']);

        return inertia('Incomes/Edit', [
            'transaction' => new TransactionResource($transaction),
            'accounts' => AccountResource::collection($workspace->accounts()->orderBy('name')->get()),
            'categories' => CategoryResource::collection(
                $workspace->categories()
                    ->whereIn('type', [TransactionType::Income->value, TransactionType::Both->value])
                    ->orderBy('name')
                    ->get()
            ),
            'tags' => TagResource::collection($workspace->tags()->orderBy('name')->get()),
        ]);
    }

    public function update(UpdateIncomeRequest $request, Workspace $workspace, Transaction $transaction, TransactionService $transactionService, RecurrenceService $recurrenceService): RedirectResponse
    {
        abort_if($transaction->workspace_id !== $workspace->id, 404);

        $this->authorize('update', [$transaction, $workspace]);

        $data = $request->validated();
        $scope = $data['scope'] ?? 'single';
        unset($data['scope']);

        if ($scope === 'future' && $transaction->recurrence_id !== null) {
            $recurrenceService->updateThisAndFuture($transaction, $data, $request->user());
        } else {
            $transactionService->update($transaction, $data);
        }

        Toast::success('Receita atualizada com sucesso.');

        return redirect()->route('incomes.index', $workspace);
    }

    public function destroy(Request $request, Workspace $workspace, Transaction $transaction, TransactionService $transactionService, RecurrenceService $recurrenceService): RedirectResponse
    {
        abort_if($transaction->workspace_id !== $workspace->id, 404);

        $this->authorize('delete', [$transaction, $workspace]);

        $scope = $request->input('scope', 'single');

        if ($scope === 'future' && $transaction->recurrence_id !== null) {
            $recurrenceService->deleteThisAndFuture($transaction);
        } else {
            $transactionService->archive($transaction);
        }

        Toast::success('Receita arquivada com sucesso.');

        return redirect()->route('incomes.index', $workspace);
    }

    public function pay(Workspace $workspace, Transaction $transaction, TransactionService $transactionService): RedirectResponse
    {
        abort_if($transaction->workspace_id !== $workspace->id, 404);

        $this->authorize('update', [$transaction, $workspace]);

        $transactionService->pay($transaction);

        Toast::success('Receita confirmada.');

        return redirect()->back();
    }

    public function unpay(Workspace $workspace, Transaction $transaction, TransactionService $transactionService): RedirectResponse
    {
        abort_if($transaction->workspace_id !== $workspace->id, 404);

        $this->authorize('update', [$transaction, $workspace]);

        $transactionService->unpay($transaction);

        Toast::success('Receita desconfirmada.');

        return redirect()->back();
    }
}
