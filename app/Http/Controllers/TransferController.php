<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransferRequest;
use App\Http\Requests\UpdateTransferRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\TagResource;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Models\Workspace;
use App\Services\TransferService;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class TransferController extends Controller
{
    public function create(Workspace $workspace): Response
    {
        $this->authorize('create', [Transaction::class, $workspace]);

        return inertia('Transfers/Create', [
            'accounts' => AccountResource::collection($workspace->accounts()->orderBy('name')->get()),
            'tags' => TagResource::collection($workspace->tags()->orderBy('name')->get()),
        ]);
    }

    public function store(StoreTransferRequest $request, Workspace $workspace, TransferService $transferService): RedirectResponse
    {
        $this->authorize('create', [Transaction::class, $workspace]);

        $transferService->create($workspace, $request->user(), $request->validated());

        return redirect()->route('incomes.index', $workspace);
    }

    public function edit(Workspace $workspace, string $transferGroup): Response
    {
        $this->authorize('viewAny', [Transaction::class, $workspace]);

        $legs = Transaction::where('transfer_group_id', $transferGroup)
            ->where('workspace_id', $workspace->id)
            ->get();

        if ($legs->isEmpty()) {
            abort(404);
        }

        $incomeLeg = $legs->where('type', 'income')->first();
        $incomeLeg->load(['account', 'tags']);

        return inertia('Transfers/Edit', [
            'transfer_group_id' => $transferGroup,
            'transfer' => new TransactionResource($incomeLeg),
            'accounts' => AccountResource::collection($workspace->accounts()->orderBy('name')->get()),
            'tags' => TagResource::collection($workspace->tags()->orderBy('name')->get()),
        ]);
    }

    public function update(UpdateTransferRequest $request, Workspace $workspace, string $transferGroup, TransferService $transferService): RedirectResponse
    {
        $this->authorize('create', [Transaction::class, $workspace]);

        $transferService->updateGroup($transferGroup, $workspace, $request->validated());

        return redirect()->route('incomes.index', $workspace);
    }

    public function destroy(Workspace $workspace, string $transferGroup, TransferService $transferService): RedirectResponse
    {
        $this->authorize('create', [Transaction::class, $workspace]);

        $transferService->deleteGroup($transferGroup, $workspace);

        return redirect()->route('incomes.index', $workspace);
    }
}
