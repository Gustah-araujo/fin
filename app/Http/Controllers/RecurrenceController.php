<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\UpdateRecurrenceRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\RecurrenceResource;
use App\Http\Resources\TagResource;
use App\Models\Recurrence;
use App\Models\Workspace;
use App\Services\RecurrenceService;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class RecurrenceController extends Controller
{
    public function index(Workspace $workspace): Response
    {
        $this->authorize('viewAny', [Recurrence::class, $workspace]);

        $recurrences = $workspace->recurrences()
            ->with(['account', 'category', 'tags'])
            ->orderByRaw('next_date IS NULL')
            ->orderBy('next_date')
            ->get();

        return inertia('Recurrences/Index', [
            'recurrences' => RecurrenceResource::collection($recurrences),
        ]);
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
                    ->whereIn('type', [TransactionType::Income->value, TransactionType::Both->value])
                    ->orderBy('name')
                    ->get()
            ),
            'tags' => TagResource::collection($workspace->tags()->orderBy('name')->get()),
            'has_transactions' => $recurrence->transactions()->exists(),
        ]);
    }

    public function update(UpdateRecurrenceRequest $request, Workspace $workspace, Recurrence $recurrence, RecurrenceService $recurrenceService): RedirectResponse
    {
        abort_if($recurrence->workspace_id !== $workspace->id, 404);

        $this->authorize('update', [$recurrence, $workspace]);

        $recurrenceService->updateRule($recurrence, $request->validated());

        return redirect()->route('recurrences.index', $workspace);
    }

    public function destroy(Workspace $workspace, Recurrence $recurrence): RedirectResponse
    {
        abort_if($recurrence->workspace_id !== $workspace->id, 404);

        $this->authorize('delete', [$recurrence, $workspace]);

        $recurrence->delete();

        return redirect()->route('recurrences.index', $workspace);
    }

    public function pause(Workspace $workspace, Recurrence $recurrence, RecurrenceService $recurrenceService): RedirectResponse
    {
        abort_if($recurrence->workspace_id !== $workspace->id, 404);

        $this->authorize('pause', [$recurrence, $workspace]);

        $recurrenceService->pause($recurrence);

        return redirect()->back();
    }

    public function restore(Workspace $workspace, Recurrence $recurrence, RecurrenceService $recurrenceService): RedirectResponse
    {
        abort_if($recurrence->workspace_id !== $workspace->id, 404);

        $this->authorize('restore', [$recurrence, $workspace]);

        $recurrenceService->restore($recurrence);

        return redirect()->back();
    }

    public function generateNow(Workspace $workspace, Recurrence $recurrence, RecurrenceService $recurrenceService): RedirectResponse
    {
        abort_if($recurrence->workspace_id !== $workspace->id, 404);

        $this->authorize('generateNow', [$recurrence, $workspace]);

        $recurrenceService->generateNextInstance($recurrence);

        return redirect()->back();
    }
}
