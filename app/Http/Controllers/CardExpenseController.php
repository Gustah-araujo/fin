<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\StoreCardExpenseRequest;
use App\Http\Requests\UpdateCardExpenseRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CreditCardResource;
use App\Http\Resources\TagResource;
use App\Http\Resources\TransactionResource;
use App\Models\CreditCard;
use App\Models\Transaction;
use App\Models\Workspace;
use App\Services\CardExpenseService;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class CardExpenseController extends Controller
{
    public function create(Workspace $workspace, CreditCard $card): Response
    {
        abort_if($card->workspace_id !== $workspace->id, 404);
        $this->authorize('create', [Transaction::class, $workspace]);

        return inertia('CardExpenses/Create', [
            'card' => new CreditCardResource($card),
            'categories' => CategoryResource::collection(
                $workspace->categories()
                    ->whereIn('type', [TransactionType::Expense->value, TransactionType::Both->value])
                    ->orderBy('name')
                    ->get()
            ),
            'tags' => TagResource::collection($workspace->tags()->orderBy('name')->get()),
        ]);
    }

    public function store(StoreCardExpenseRequest $request, Workspace $workspace, CreditCard $card, CardExpenseService $service): RedirectResponse
    {
        abort_if($card->workspace_id !== $workspace->id, 404);
        $this->authorize('create', [Transaction::class, $workspace]);

        $installments = (int) ($request->validated()['installments'] ?? 1);

        if ($installments > 1) {
            $service->createInstallment($workspace, $request->user(), $card, $request->validated());
        } else {
            $service->createSingle($workspace, $request->user(), $card, $request->validated());
        }

        return redirect()->route('cards.show', [$workspace, $card]);
    }

    public function edit(Workspace $workspace, CreditCard $card, Transaction $transaction): Response
    {
        abort_if($card->workspace_id !== $workspace->id, 404);
        abort_if($transaction->workspace_id !== $workspace->id, 404);
        abort_if($transaction->credit_card_id !== $card->id, 404);
        $this->authorize('update', [$transaction, $workspace]);

        $transaction->load(['category', 'tags']);

        return inertia('CardExpenses/Edit', [
            'card' => new CreditCardResource($card),
            'transaction' => new TransactionResource($transaction),
            'categories' => CategoryResource::collection(
                $workspace->categories()
                    ->whereIn('type', [TransactionType::Expense->value, TransactionType::Both->value])
                    ->orderBy('name')
                    ->get()
            ),
            'tags' => TagResource::collection($workspace->tags()->orderBy('name')->get()),
        ]);
    }

    public function update(UpdateCardExpenseRequest $request, Workspace $workspace, CreditCard $card, Transaction $transaction, CardExpenseService $service): RedirectResponse
    {
        abort_if($card->workspace_id !== $workspace->id, 404);
        abort_if($transaction->workspace_id !== $workspace->id, 404);
        abort_if($transaction->credit_card_id !== $card->id, 404);
        $this->authorize('update', [$transaction, $workspace]);

        $scope = $request->validated()['scope'] ?? 'single';

        if ($scope === 'group' && $transaction->installment_group_id) {
            $service->updateGroup($transaction, $request->validated());
        } else {
            $service->updateSingle($transaction, $request->validated());
        }

        return redirect()->route('cards.show', [$workspace, $card]);
    }

    public function destroy(Workspace $workspace, CreditCard $card, Transaction $transaction, CardExpenseService $service): RedirectResponse
    {
        abort_if($card->workspace_id !== $workspace->id, 404);
        abort_if($transaction->workspace_id !== $workspace->id, 404);
        abort_if($transaction->credit_card_id !== $card->id, 404);
        $this->authorize('delete', [$transaction, $workspace]);

        $scope = request()->input('scope', 'single');

        if ($scope === 'group' && $transaction->installment_group_id) {
            $service->deleteGroup($transaction);
        } else {
            $service->deleteSingle($transaction);
        }

        return redirect()->route('cards.show', [$workspace, $card]);
    }
}
