<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\StoreIncomeRequest;
use App\Http\Requests\UpdateIncomeRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\TagResource;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Models\Workspace;
use App\Services\RecurrenceService;
use App\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class IncomeController extends Controller
{
    public function index(Workspace $workspace): Response
    {
        $this->authorize('viewAny', [Transaction::class, $workspace]);

        $query = $workspace->transactions()
            ->where('type', TransactionType::Income)
            ->with(['account', 'category', 'tags', 'recurrence'])
            ->latest('date')
            ->when(request()->filled('search'), fn ($q) => $q->where('description', 'like', '%'.request()->input('search').'%'))
            ->when(request()->filled('category'), fn ($q) => $q->where('category_id', request()->input('category')))
            ->when(request()->filled('account'), fn ($q) => $q->where('account_id', request()->input('account')))
            ->when(request()->filled('from_date'), fn ($q) => $q->whereDate('date', '>=', request()->input('from_date')))
            ->when(request()->filled('to_date'), fn ($q) => $q->whereDate('date', '<=', request()->input('to_date')))
            ->when(request()->input('status') === 'paid', fn ($q) => $q->whereNotNull('paid_at'))
            ->when(request()->input('status') === 'unpaid', fn ($q) => $q->whereNull('paid_at'))
            ->when(request()->input('origin') === 'recurring', fn ($q) => $q->whereNotNull('recurrence_id'))
            ->when(request()->input('origin') === 'single', fn ($q) => $q->whereNull('recurrence_id'))
            ->when(request()->filled('recurrence'), fn ($q) => $q->whereHas('recurrence', fn ($r) => $r->where('uuid', request()->input('recurrence'))));

        $incomes = $query->paginate(25)->withQueryString();

        return inertia('Incomes/Index', [
            'incomes' => TransactionResource::collection($incomes),
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

            if (Carbon::parse($data['date'])->lte(Carbon::today())) {
                $recurrenceService->createWithFirstInstance($workspace, $data, $request->user());
            } else {
                $recurrenceService->create($workspace, $data, $request->user());
            }
        } else {
            $data['type'] = TransactionType::Income->value;
            $transactionService->create($workspace, $request->user(), $data);
        }

        return redirect()->route('incomes.index', $workspace);
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

        return redirect()->route('incomes.index', $workspace);
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
