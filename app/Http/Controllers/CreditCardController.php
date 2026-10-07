<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\BillStatus;
use App\Http\Requests\StoreCardRequest;
use App\Http\Requests\UpdateCardRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\CreditCardBillResource;
use App\Http\Resources\CreditCardResource;
use App\Models\CreditCard;
use App\Models\Workspace;
use App\Services\BillService;
use App\Services\CreditCardService;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class CreditCardController extends Controller
{
    public function __construct(
        private readonly BillService $billService,
    ) {}

    public function index(Workspace $workspace): Response
    {
        $this->authorize('viewAny', [CreditCard::class, $workspace]);

        $cards = $workspace->creditCards()->latest()->get();

        return inertia('Cards/Index', [
            'cards' => CreditCardResource::collection($cards),
        ]);
    }

    public function show(Workspace $workspace, CreditCard $card): Response
    {
        abort_if($card->workspace_id !== $workspace->id, 404);
        $this->authorize('viewAny', [CreditCard::class, $workspace]);

        // On-demand close: close any Open bill with closing_date < today (fallback for delayed job)
        $staleBills = $card->bills()
            ->where('status', BillStatus::Open)
            ->where('closing_date', '<', now()->toDateString())
            ->get();

        foreach ($staleBills as $staleBill) {
            $this->billService->closeBill($staleBill);
        }

        // Load all bills (past, current, future pre-created) for the invoice selector
        // Eager-load transactions so CreditCardBillResource can serialize expenses
        $bills = $card->bills()
            ->with(['transactions' => fn ($q) => $q
                ->with(['category', 'tags'])
                ->orderBy('date')
                ->orderBy('installment_number')])
            ->orderBy('period_year', 'desc')
            ->orderBy('period_month', 'desc')
            ->get();

        // Current cycle bill (the one with the current date's period)
        $currentBill = $this->billService->findOrCreateBill($card, now(), $card->created_by);

        return inertia('Cards/Show', [
            'card' => new CreditCardResource($card),
            'bills' => CreditCardBillResource::collection($bills),
            'currentBill' => new CreditCardBillResource($currentBill->load('transactions')),
            'accounts' => AccountResource::collection(
                $workspace->accounts()->whereNull('deleted_at')->get()
            ),
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

        $card = $service->create($workspace, $request->user(), $request->validated());

        Toast::success('Cartão criado com sucesso.');

        return redirect()->route('cards.show', [$workspace, $card]);
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

        Toast::success('Cartão atualizado com sucesso.');

        return redirect()->route('cards.index', $workspace);
    }

    public function destroy(Workspace $workspace, CreditCard $card, CreditCardService $service): RedirectResponse
    {
        abort_if($card->workspace_id !== $workspace->id, 404);

        $this->authorize('delete', [$card, $workspace]);

        $service->archive($card);

        Toast::success('Cartão arquivado com sucesso.');

        return redirect()->route('cards.index', $workspace);
    }
}
