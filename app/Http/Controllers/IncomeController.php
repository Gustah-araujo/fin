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
use App\Services\IncomeService;
use App\Services\RecurringIncomeService;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class IncomeController extends Controller
{
    public function index(Workspace $workspace): Response
    {
        $this->authorize('viewAny', [Transaction::class, $workspace]);

        $query = $workspace->transactions()
            ->with(['account', 'category', 'tags'])
            ->where('type', TransactionType::Income->value)
            ->whereNull('transfer_group_id')
            ->latest('date');

        if (request()->filled('search')) {
            $query->where('description', 'like', '%'.request()->input('search').'%');
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
        if (request()->filled('flag')) {
            match (request()->input('flag')) {
                'recurring' => $query->where('is_recurring', true),
                'installment' => $query->whereNotNull('installments_total')->where('installments_total', '>', 1),
                'simple' => $query->where('is_recurring', false)->where(function ($q) {
                    $q->whereNull('installments_total')->orWhere('installments_total', '<=', 1);
                }),
                default => null,
            };
        }

        $transactions = $query->paginate(25)->withQueryString();

        return inertia('Incomes/Index', [
            'incomes' => TransactionResource::collection($transactions),
            'accounts' => AccountResource::collection($workspace->accounts()->orderBy('name')->get()),
            'categories' => CategoryResource::collection(
                $workspace->categories()->orderBy('name')->get()
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

    public function store(StoreIncomeRequest $request, Workspace $workspace, IncomeService $incomeService, RecurringIncomeService $recurringIncomeService): RedirectResponse
    {
        $this->authorize('create', [Transaction::class, $workspace]);

        $data = $request->validated();

        if (! empty($data['is_recurring'])) {
            $recurringIncomeService->createTemplate($workspace, $request->user(), $data);
        } elseif (! empty($data['installments_total']) && (int) $data['installments_total'] > 1) {
            $incomeService->createInstallment($workspace, $request->user(), $data);
        } else {
            $incomeService->create($workspace, $request->user(), $data);
        }

        return redirect()->route('incomes.index', $workspace);
    }

    public function edit(Workspace $workspace, Transaction $income): Response
    {
        abort_if($income->workspace_id !== $workspace->id, 404);

        $this->authorize('update', [$income, $workspace]);

        $income->load(['account', 'category', 'tags']);

        return inertia('Incomes/Edit', [
            'income' => new TransactionResource($income),
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

    public function update(UpdateIncomeRequest $request, Workspace $workspace, Transaction $income, IncomeService $incomeService): RedirectResponse
    {
        abort_if($income->workspace_id !== $workspace->id, 404);

        $this->authorize('update', [$income, $workspace]);

        $incomeService->update($income, $request->validated());

        return redirect()->route('incomes.index', $workspace);
    }

    public function destroy(Workspace $workspace, Transaction $income, IncomeService $incomeService): RedirectResponse
    {
        abort_if($income->workspace_id !== $workspace->id, 404);

        $this->authorize('delete', [$income, $workspace]);

        $incomeService->deleteSingle($income);

        return redirect()->route('incomes.index', $workspace);
    }

    public function receive(Workspace $workspace, Transaction $income, IncomeService $incomeService): RedirectResponse
    {
        abort_if($income->workspace_id !== $workspace->id, 404);

        $this->authorize('update', [$income, $workspace]);

        $incomeService->receive($income);

        return redirect()->back();
    }

    public function unreceive(Workspace $workspace, Transaction $income, IncomeService $incomeService): RedirectResponse
    {
        abort_if($income->workspace_id !== $workspace->id, 404);

        $this->authorize('update', [$income, $workspace]);

        $incomeService->unreceive($income);

        return redirect()->back();
    }

    public function destroyGroup(Workspace $workspace, Transaction $income, IncomeService $incomeService): RedirectResponse
    {
        abort_if($income->workspace_id !== $workspace->id, 404);

        $this->authorize('delete', [$income, $workspace]);

        $incomeService->deleteGroup($income);

        return redirect()->route('incomes.index', $workspace);
    }
}
