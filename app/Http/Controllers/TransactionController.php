<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Controllers\Concerns\PersistsTableState;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CreditCardBillResource;
use App\Http\Resources\CreditCardResource;
use App\Http\Resources\TagResource;
use App\Http\Resources\TransactionResource;
use App\Models\CreditCard;
use App\Models\CreditCardBill;
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

class TransactionController extends Controller
{
    use PersistsTableState;

    public function index(Workspace $workspace): Response
    {
        $this->authorize('viewAny', [Transaction::class, $workspace]);

        $state = app(TableStateService::class)->restore(
            request(), 'transactions'
        );

        return inertia('Transactions/Index', [
            'accounts' => AccountResource::collection($workspace->accounts()->orderBy('name')->get()),
            'cards' => CreditCardResource::collection($workspace->creditCards()->whereNull('deleted_at')->orderBy('name')->get()),
            'categories' => CategoryResource::collection($workspace->categories()->orderBy('name')->get()),
            'tags' => TagResource::collection($workspace->tags()->orderBy('name')->get()),
            'bills' => CreditCardBillResource::collection($workspace->creditCardBills()->with('creditCard')->get()),
            'initialState' => $state,
        ]);
    }

    public function datatable(Workspace $workspace, Request $request): JsonResponse
    {
        $this->authorize('viewAny', [Transaction::class, $workspace]);

        $state = $this->resolveTableState($request, 'transactions', $this->datatableConfig());

        $request->merge([
            'sort' => $state['sort'],
            'direction' => $state['direction'],
            ...$state['filters'],
        ]);

        $query = $workspace->transactions()
            ->with(['account', 'creditCard', 'bill', 'category', 'tags'])
            ->where('type', TransactionType::Expense);

        $filters = $state['filters'];

        // Bill filter overrides month scoping
        if (empty($filters['credit_card_bill_id'])) {
            $month = $request->input('month');
            if (is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month)) {
                [$year, $monthNum] = explode('-', $month);
                $query->whereYear('date', (int) $year)
                    ->whereMonth('date', (int) $monthNum);
            }
        }

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
            ->filter('credit_card_id', Filter::select(fn (Builder $q, string $v) => $q->where('credit_card_id', CreditCard::where('uuid', $v)->value('id'))))
            ->filter('credit_card_bill_id', Filter::select(fn (Builder $q, string $v) => $q->where('credit_card_bill_id', CreditCardBill::where('uuid', $v)->value('id'))))
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

        if (! empty($data['credit_card_id'])) {
            // Card expense path
            if ($request->boolean('is_recurring')) {
                $data['start_date'] = $data['date'];
                $data['type'] = TransactionType::Expense->value;
                $recurrenceService->createWithBuffer($workspace, $data, $request->user());

                Toast::success('Recorrência criada com sucesso.');
            } elseif (! empty($data['installments']) && $data['installments'] > 1) {
                // Card installment purchase
                $transactionService->createCardInstallment($workspace, $request->user(), $data);

                Toast::success('Despesa parcelada criada com sucesso.');
            } else {
                // Single card expense
                $transactionService->createCardExpense($workspace, $request->user(), $data);

                Toast::success('Despesa criada com sucesso.');
            }
        } else {
            // Account-based expense path
            if ($request->boolean('is_recurring')) {
                $data['start_date'] = $data['date'];
                $data['type'] = TransactionType::Expense->value;
                $recurrenceService->createWithBuffer($workspace, $data, $request->user());

                Toast::success('Recorrência criada com sucesso.');
            } else {
                $data['type'] = TransactionType::Expense->value;
                $transactionService->create($workspace, $request->user(), $data);

                Toast::success('Despesa criada com sucesso.');
            }
        }

        return redirect()->route('transactions.index', $workspace);
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

        $data = $request->validated();

        if ($transaction->credit_card_id !== null) {
            $scope = $data['scope'] ?? 'single';
            if ($scope === 'group' && $transaction->installment_group_id) {
                $transactionService->updateCardGroup($transaction, $data);
            } else {
                $transactionService->updateCardSingle($transaction, $data);
            }
        } else {
            $transactionService->update($transaction, $data);
        }

        Toast::success('Despesa atualizada com sucesso.');

        return redirect()->route('transactions.index', $workspace);
    }

    public function destroy(Workspace $workspace, Transaction $transaction, TransactionService $transactionService): RedirectResponse
    {
        abort_if($transaction->workspace_id !== $workspace->id, 404);

        $this->authorize('delete', [$transaction, $workspace]);

        if ($transaction->credit_card_id !== null && $transaction->installment_group_id) {
            $transactionService->deleteCardGroup($transaction);
        } elseif ($transaction->credit_card_id !== null) {
            $transactionService->deleteCardSingle($transaction);
        } else {
            $transactionService->archive($transaction);
        }

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
