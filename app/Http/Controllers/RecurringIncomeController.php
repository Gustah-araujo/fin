<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CancelRecurringIncomeRequest;
use App\Http\Requests\UpdateRecurringIncomeRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\TagResource;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Models\Workspace;
use App\Services\RecurringIncomeService;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class RecurringIncomeController extends Controller
{
    public function index(Workspace $workspace): Response
    {
        $this->authorize('viewAny', [Transaction::class, $workspace]);

        $templates = $workspace->transactions()
            ->with(['account', 'category', 'tags'])
            ->where('is_recurring', true)
            ->whereNull('recurring_parent_uuid')
            ->latest('date')
            ->get();

        $occurrenceCounts = Transaction::whereNotNull('recurring_parent_uuid')
            ->where('workspace_id', $workspace->id)
            ->selectRaw('recurring_parent_uuid, COUNT(*) as count')
            ->groupBy('recurring_parent_uuid')
            ->pluck('count', 'recurring_parent_uuid');

        $templates->each(function ($template) use ($occurrenceCounts) {
            $template->occurrences_count = $occurrenceCounts->get($template->uuid, 0);
        });

        return inertia('RecurringIncomes/Index', [
            'templates' => TransactionResource::collection($templates),
        ]);
    }

    public function edit(Workspace $workspace, Transaction $template): Response
    {
        abort_if($template->workspace_id !== $workspace->id, 404);
        abort_if(! $template->is_recurring || $template->recurring_parent_uuid !== null, 404);

        $this->authorize('update', [$template, $workspace]);

        $template->load(['account', 'category', 'tags']);

        return inertia('RecurringIncomes/Edit', [
            'template' => new TransactionResource($template),
            'accounts' => AccountResource::collection($workspace->accounts()->orderBy('name')->get()),
            'categories' => CategoryResource::collection(
                $workspace->categories()
                    ->whereIn('type', ['income', 'both'])
                    ->orderBy('name')
                    ->get()
            ),
            'tags' => TagResource::collection($workspace->tags()->orderBy('name')->get()),
        ]);
    }

    public function update(UpdateRecurringIncomeRequest $request, Workspace $workspace, Transaction $template, RecurringIncomeService $service): RedirectResponse
    {
        abort_if($template->workspace_id !== $workspace->id, 404);
        abort_if(! $template->is_recurring || $template->recurring_parent_uuid !== null, 404);

        $this->authorize('update', [$template, $workspace]);

        $service->updateTemplate($template, $request->validated(), (bool) $request->input('propagate', false));

        return redirect()->route('recurring-incomes.index', $workspace);
    }

    public function cancel(CancelRecurringIncomeRequest $request, Workspace $workspace, Transaction $template, RecurringIncomeService $service): RedirectResponse
    {
        abort_if($template->workspace_id !== $workspace->id, 404);
        abort_if(! $template->is_recurring || $template->recurring_parent_uuid !== null, 404);

        $this->authorize('update', [$template, $workspace]);

        $service->cancelTemplate($template);

        return redirect()->route('recurring-incomes.index', $workspace);
    }

    public function destroy(Workspace $workspace, Transaction $template, RecurringIncomeService $service): RedirectResponse
    {
        abort_if($template->workspace_id !== $workspace->id, 404);
        abort_if(! $template->is_recurring || $template->recurring_parent_uuid !== null, 404);

        $this->authorize('delete', [$template, $workspace]);

        $orphan = (bool) request()->input('orphan_occurrences', true);
        $service->deleteTemplate($template, $orphan);

        return redirect()->route('recurring-incomes.index', $workspace);
    }
}
