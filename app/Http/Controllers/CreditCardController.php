<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCardRequest;
use App\Http\Requests\UpdateCardRequest;
use App\Http\Resources\CreditCardResource;
use App\Models\CreditCard;
use App\Models\Workspace;
use App\Services\CreditCardService;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class CreditCardController extends Controller
{
    public function index(Workspace $workspace): Response
    {
        $this->authorize('viewAny', [CreditCard::class, $workspace]);

        $cards = $workspace->creditCards()->latest()->get();

        return inertia('Cards/Index', [
            'cards' => CreditCardResource::collection($cards),
        ]);
    }

    public function create(Workspace $workspace): Response
    {
        $this->authorize('create', [CreditCard::class, $workspace]);

        return inertia('Cards/Create');
    }

    public function store(StoreCardRequest $request, Workspace $workspace, CreditCardService $service): RedirectResponse
    {
        $this->authorize('create', [CreditCard::class, $workspace]);

        $service->create($workspace, $request->user(), $request->validated());

        return redirect()->route('cards.index', $workspace);
    }

    public function edit(Workspace $workspace, CreditCard $card): Response
    {
        abort_if($card->workspace_id !== $workspace->id, 404);

        $this->authorize('update', [$card, $workspace]);

        return inertia('Cards/Edit', [
            'card' => new CreditCardResource($card),
        ]);
    }

    public function update(UpdateCardRequest $request, Workspace $workspace, CreditCard $card, CreditCardService $service): RedirectResponse
    {
        abort_if($card->workspace_id !== $workspace->id, 404);

        $this->authorize('update', [$card, $workspace]);

        $service->update($card, $request->validated());

        return redirect()->route('cards.index', $workspace);
    }

    public function destroy(Workspace $workspace, CreditCard $card, CreditCardService $service): RedirectResponse
    {
        abort_if($card->workspace_id !== $workspace->id, 404);

        $this->authorize('delete', [$card, $workspace]);

        $service->archive($card);

        return redirect()->route('cards.index', $workspace);
    }
}
