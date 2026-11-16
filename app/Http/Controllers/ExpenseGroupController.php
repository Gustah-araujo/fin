<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseGroupRequest;
use App\Http\Requests\UpdateExpenseGroupRequest;
use App\Http\Resources\ExpenseGroupResource;
use App\Models\ExpenseGroup;
use App\Models\Workspace;
use App\Services\ExpenseGroupService;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class ExpenseGroupController extends Controller
{
    public function index(Workspace $workspace): Response
    {
        $this->authorize('viewAny', [ExpenseGroup::class, $workspace]);

        $expenseGroups = $workspace->expenseGroups()->orderBy('name')->get();

        return inertia('ExpenseGroups/Index', [
            'expenseGroups' => ExpenseGroupResource::collection($expenseGroups),
        ]);
    }

    public function create(Workspace $workspace): Response
    {
        $this->authorize('create', [ExpenseGroup::class, $workspace]);

        return inertia('ExpenseGroups/Create');
    }

    public function store(StoreExpenseGroupRequest $request, Workspace $workspace, ExpenseGroupService $expenseGroupService): RedirectResponse
    {
        $this->authorize('create', [ExpenseGroup::class, $workspace]);

        $expenseGroupService->create($workspace, $request->validated());

        Toast::success('Grupo de despesa criado com sucesso.');

        return redirect()->route('expense-groups.index', $workspace);
    }

    public function show(Workspace $workspace): RedirectResponse
    {
        return redirect()->route('expense-groups.index', $workspace);
    }

    public function edit(Workspace $workspace, ExpenseGroup $expenseGroup): Response
    {
        abort_if($expenseGroup->workspace_id !== $workspace->id, 404);

        $this->authorize('update', [$expenseGroup, $workspace]);

        return inertia('ExpenseGroups/Edit', [
            'expenseGroup' => new ExpenseGroupResource($expenseGroup),
        ]);
    }

    public function update(UpdateExpenseGroupRequest $request, Workspace $workspace, ExpenseGroup $expenseGroup, ExpenseGroupService $expenseGroupService): RedirectResponse
    {
        abort_if($expenseGroup->workspace_id !== $workspace->id, 404);

        $this->authorize('update', [$expenseGroup, $workspace]);

        $expenseGroupService->update($expenseGroup, $request->validated());

        Toast::success('Grupo de despesa atualizado com sucesso.');

        return redirect()->route('expense-groups.index', $workspace);
    }

    public function destroy(Workspace $workspace, ExpenseGroup $expenseGroup, ExpenseGroupService $expenseGroupService): RedirectResponse
    {
        abort_if($expenseGroup->workspace_id !== $workspace->id, 404);

        $this->authorize('delete', [$expenseGroup, $workspace]);

        $expenseGroupService->delete($expenseGroup);

        Toast::success('Grupo de despesa excluído com sucesso.');

        return redirect()->route('expense-groups.index', $workspace);
    }
}
