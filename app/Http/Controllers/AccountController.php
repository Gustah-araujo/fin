<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use App\Models\Workspace;
use App\Services\AccountService;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(Workspace $workspace): Response
    {
        $this->authorize("viewAny", [Account::class, $workspace]);

        $accounts = $workspace->accounts()->latest()->get();

        return inertia("Accounts/Index", [
            "accounts" => AccountResource::collection($accounts),
        ]);
    }

    public function create(Workspace $workspace): Response
    {
        $this->authorize("create", [Account::class, $workspace]);

        return inertia("Accounts/Create");
    }

    public function store(StoreAccountRequest $request, Workspace $workspace, AccountService $accountService)
    {
        $this->authorize("create", [Account::class, $workspace]);

        $accountService->create($workspace, $request->user(), $request->validated());

        return redirect()->route("accounts.index", $workspace);
    }

    public function edit(Workspace $workspace, Account $account): Response
    {
        abort_if($account->workspace_id !== $workspace->id, 404);

        $this->authorize("update", [$account, $workspace]);

        return inertia("Accounts/Edit", [
            "account" => new AccountResource($account),
        ]);
    }

    public function update(UpdateAccountRequest $request, Workspace $workspace, Account $account, AccountService $accountService)
    {
        abort_if($account->workspace_id !== $workspace->id, 404);

        $this->authorize("update", [$account, $workspace]);

        $accountService->update($account, $request->validated());

        return redirect()->route("accounts.index", $workspace);
    }

    public function destroy(Workspace $workspace, Account $account, AccountService $accountService)
    {
        abort_if($account->workspace_id !== $workspace->id, 404);

        $this->authorize("delete", [$account, $workspace]);

        $accountService->archive($account);

        return redirect()->route("accounts.index", $workspace);
    }
}
