<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RecurrenceStatus;
use App\Enums\TransactionType;
use App\Exceptions\RecurrenceGenerationException;
use App\Http\Requests\UpdateRecurrenceRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\RecurrenceResource;
use App\Http\Resources\TagResource;
use App\Models\Recurrence;
use App\Models\Workspace;
use App\Services\Datatable\DatatableConfig;
use App\Services\Datatable\DatatableService;
use App\Services\Datatable\Filter;
use App\Services\RecurrenceService;
use App\Support\Toast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class RecurrenceController extends Controller
{
    public function index(Workspace $workspace): Response
    {
        $this->authorize('viewAny', [Recurrence::class, $workspace]);

        return inertia('Recurrences/Index', [
            'accounts' => AccountResource::collection($workspace->accounts()->orderBy('name')->get()),
            'categories' => CategoryResource::collection($workspace->categories()->orderBy('name')->get()),
        ]);
    }

    public function datatable(Workspace $workspace, Request $request): JsonResponse
    {
        $this->authorize('viewAny', [Recurrence::class, $workspace]);

        $query = $workspace->recurrences()
            ->with(['account', 'category', 'tags'])
            ->orderByRaw('next_date IS NULL');

        return app(DatatableService::class)->paginate($query, $request, $this->datatableConfig());
    }

    private function datatableConfig(): DatatableConfig
    {
        return DatatableConfig::make(RecurrenceResource::class)
            ->filter('description', Filter::text('description'))
            ->filter('type', Filter::select(fn (Builder $q, string $v) => match ($v) {
                'income' => $q->where('type', TransactionType::Income),
                'expense' => $q->where('type', TransactionType::Expense),
                default => null,
            }))
            ->filter('value', Filter::numberRange('value'))
            ->filter('next_date', Filter::dateRange('next_date'))
            ->filter('account', Filter::relation('account', 'uuid'))
            ->filter('category', Filter::relation('category', 'uuid'))
            ->filter('status', Filter::select(fn (Builder $q, string $v) => match ($v) {
                'paused' => $q->where('status', RecurrenceStatus::Paused),
                'active' => $q->where('status', RecurrenceStatus::Active)->whereNotNull('next_date'),
                'exhausted' => $q->whereNull('next_date'),
                default => null,
            }))
            ->sortable(['description', 'value', 'next_date'])
            ->defaultSort('next_date', 'asc')
            ->perPage(25);
    }

    public function edit(Workspace $workspace, Recurrence $recurrence): Response
    {
        abort_if($recurrence->workspace_id !== $workspace->id, 404);

        $this->authorize('update', [$recurrence, $workspace]);

        $recurrence->load(['account', 'category', 'tags']);

        return inertia('Recurrences/Edit', [
            'recurrence' => new RecurrenceResource($recurrence),
            'accounts' => AccountResource::collection($workspace->accounts()->orderBy('name')->get()),
            'categories' => CategoryResource::collection(
                $workspace->categories()
                    ->whereIn('type', $recurrence->type === TransactionType::Expense
                        ? [TransactionType::Expense->value, TransactionType::Both->value]
                        : [TransactionType::Income->value, TransactionType::Both->value])
                    ->orderBy('name')
                    ->get()
            ),
            'tags' => TagResource::collection($workspace->tags()->orderBy('name')->get()),
            'has_transactions' => $recurrence->transactions()->exists(),
        ]);
    }

    public function update(
        UpdateRecurrenceRequest $request,
        Workspace $workspace,
        Recurrence $recurrence,
        RecurrenceService $recurrenceService
    ): RedirectResponse {
        abort_if($recurrence->workspace_id !== $workspace->id, 404);

        $this->authorize('update', [$recurrence, $workspace]);

        $data = $request->validated();
        $propagate = $data['propagate_to_future'] ?? false;
        unset($data['propagate_to_future']);

        $recurrenceService->updateRule($recurrence, $data);

        if ($propagate) {
            $recurrenceService->propagateToFuture($recurrence, $data);
            Toast::success('Recorrência e instâncias futuras atualizadas.');
        } else {
            Toast::success('Recorrência atualizada com sucesso.');
        }

        return redirect()->route('recurrences.index', $workspace);
    }

    public function destroy(Workspace $workspace, Recurrence $recurrence): RedirectResponse
    {
        abort_if($recurrence->workspace_id !== $workspace->id, 404);

        $this->authorize('delete', [$recurrence, $workspace]);

        $recurrence->delete();
        Toast::success('Recorrência removida com sucesso.');

        return redirect()->route('recurrences.index', $workspace);
    }

    public function pause(Workspace $workspace, Recurrence $recurrence, RecurrenceService $recurrenceService): RedirectResponse
    {
        abort_if($recurrence->workspace_id !== $workspace->id, 404);

        $this->authorize('pause', [$recurrence, $workspace]);

        $recurrenceService->pause($recurrence);
        Toast::success('Recorrência pausada.');

        return redirect()->back();
    }

    public function restore(Workspace $workspace, Recurrence $recurrence, RecurrenceService $recurrenceService): RedirectResponse
    {
        abort_if($recurrence->workspace_id !== $workspace->id, 404);

        $this->authorize('restore', [$recurrence, $workspace]);

        $recurrenceService->restore($recurrence);
        Toast::success('Recorrência retomada.');

        return redirect()->back();
    }

    public function generateNow(Workspace $workspace, Recurrence $recurrence, RecurrenceService $recurrenceService): RedirectResponse
    {
        abort_if($recurrence->workspace_id !== $workspace->id, 404);

        $this->authorize('generateNow', [$recurrence, $workspace]);

        try {
            $transaction = $recurrenceService->generateNextInstance($recurrence);
            Toast::success("Transação \"{$transaction->description}\" gerada com sucesso.");
        } catch (RecurrenceGenerationException $e) {
            Toast::error($e->getMessage());
        }

        return redirect()->back();
    }
}
