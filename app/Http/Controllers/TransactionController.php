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
use App\Services\TransactionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class TransactionController extends Controller
{
    public function index(Workspace $workspace): Response
    {
        $this->authorize('viewAny', [Transaction::class, $workspace]);

        $query = $workspace->transactions()
            ->with(['account', 'category', 'tags'])
            ->latest('date');

        if (request()->filled('search')) {
            $query->where('description', 'like', '%' . request()->input('search') . '%');
        }
        if (request()->filled('category')) {
            $query->where('category_id', request()->input('category'));
        }
        if (request()->filled('account')) {
            $query->where('account_id', request()->input('account'));
        }
        if (request()->filled('from_date')) {
            $query->whereDate('date', '>=', request()->input('from_date'));
        }
        if (request()->filled('to_date')) {
            $query->whereDate('date', '<=', request()->input('to_date'));
        }
        if (request()->filled('status')) {
            match (request()->input('status')) {
                'paid' => $query->whereNotNull('paid_at'),
                'unpaid' => $query->whereNull('paid_at'),
                default => null,
            };
        }

        $transactions = $query->paginate(25)->withQueryString();

        return inertia('Transactions/Index', [
            'transactions' => TransactionResource::collection($transactions),
            'accounts' => AccountResource::collection($workspace->accounts()->orderBy('name')->get()),
            'categories' => CategoryResource::collection($workspace->categories()->orderBy('name')->get()),
            'tags' => TagResource::collection($workspace->tags()->orderBy('name')->get()),
        ]);
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

    public function store(StoreTransactionRequest $request, Workspace $workspace, TransactionService $transactionService): RedirectResponse
    {
        $this->authorize('create', [Transaction::class, $workspace]);

        $transactionService->create($workspace, $request->user(), $request->validated());

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

        $transactionService->update($transaction, $request->validated());

        return redirect()->route('transactions.index', $workspace);
    }

    public function destroy(Workspace $workspace, Transaction $transaction, TransactionService $transactionService): RedirectResponse
    {
        abort_if($transaction->workspace_id !== $workspace->id, 404);

        $this->authorize('delete', [$transaction, $workspace]);

        $transactionService->archive($transaction);

        return redirect()->route('transactions.index', $workspace);
    }

    public function pay(Workspace $workspace, Transaction $transaction, TransactionService $transactionService): RedirectResponse
    {
        abort_if($transaction->workspace_id !== $workspace->id, 404);

        $this->authorize('update', [$transaction, $workspace]);

        $transactionService->pay($transaction);

        return redirect()->back();
    }

    public function unpay(Workspace $workspace, Transaction $transaction, TransactionService $transactionService): RedirectResponse
    {
        abort_if($transaction->workspace_id !== $workspace->id, 404);

        $this->authorize('update', [$transaction, $workspace]);

        $transactionService->unpay($transaction);

        return redirect()->back();
    }
}
