<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

class AccountService
{
    public function create(Workspace $workspace, User $creator, array $data): Account
    {
        return Account::create([
            "uuid" => Str::orderedUuid()->toString(),
            "workspace_id" => $workspace->id,
            "created_by" => $creator->id,
            "name" => $data["name"],
            "type" => $data["type"],
            "initial_balance" => $data["initial_balance"],
            "current_balance" => $data["initial_balance"],
        ]);
    }

    public function update(Account $account, array $data): Account
    {
        if (isset($data["name"])) {
            $account->name = $data["name"];
        }
        if (isset($data["type"])) {
            $account->type = $data["type"];
        }
        if (isset($data["initial_balance"])) {
            $account->initial_balance = $data["initial_balance"];
            $account->current_balance = $data["initial_balance"];
        }

        $account->save();

        return $account;
    }

    public function recalculateBalance(Account $account): void
    {
        $account->current_balance = $account->initial_balance;
        $account->save();
    }

    public function archive(Account $account): void
    {
        $account->delete();
    }
}
