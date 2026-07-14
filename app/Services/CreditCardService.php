<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CreditCard;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

class CreditCardService
{
    public function create(Workspace $workspace, User $creator, array $data): CreditCard
    {
        return CreditCard::create([
            'uuid' => Str::orderedUuid()->toString(),
            'workspace_id' => $workspace->id,
            'created_by' => $creator->id,
            'name' => $data['name'],
            'credit_limit' => $data['credit_limit'],
            'available_limit' => $data['credit_limit'],
            'closing_day' => $data['closing_day'],
            'due_day' => $data['due_day'],
        ]);
    }

    public function update(CreditCard $card, array $data): CreditCard
    {
        if (isset($data['name'])) {
            $card->name = $data['name'];
        }
        if (isset($data['closing_day'])) {
            $card->closing_day = $data['closing_day'];
        }
        if (isset($data['due_day'])) {
            $card->due_day = $data['due_day'];
        }
        if (isset($data['credit_limit'])) {
            $card->credit_limit = $data['credit_limit'];
            $this->recalculateAvailableLimit($card);
        }

        $card->save();

        return $card;
    }

    public function recalculateAvailableLimit(CreditCard $card): void
    {
        $card->available_limit = (float) $card->credit_limit;
        $card->save();
    }

    public function archive(CreditCard $card): void
    {
        $card->delete();
    }
}
