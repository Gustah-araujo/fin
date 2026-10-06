<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\BillStatus;
use App\Http\Requests\PayBillRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\CreditCardBillResource;
use App\Http\Resources\CreditCardResource;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\CreditCardBill;
use App\Models\Workspace;
use App\Services\BillService;
use App\Support\Toast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class CreditCardBillController extends Controller
{
    public function __construct(
        private readonly BillService $billService,
    ) {}

    public function show(Workspace $workspace, CreditCardBill $bill): Response
    {
        abort_if($bill->workspace_id !== $workspace->id, 404);
        $this->authorize('view', [$bill, $workspace]);

        $bill->load(['transactions' => fn ($q) => $q
            ->with(['category', 'tags'])
            ->orderBy('date')
            ->orderBy('installment_number')]);
        $bill->load('paymentAccount');
        $bill->load('creditCard');

        return inertia('Bills/Show', [
            'bill' => new CreditCardBillResource($bill),
            'card' => new CreditCardResource($bill->creditCard),
            'accounts' => AccountResource::collection(
                $workspace->accounts()->orderBy('name')->get()
            ),
        ]);
    }

    public function pay(PayBillRequest $request, Workspace $workspace, CreditCardBill $bill, BillService $billService): RedirectResponse
    {
        abort_if($bill->workspace_id !== $workspace->id, 404);
        $this->authorize('pay', [$bill, $workspace]);

        $account = Account::where('uuid', $request->validated()['account_id'])
            ->where('workspace_id', $workspace->id)
            ->firstOrFail();

        $billService->payBill($bill, $account, $request->user());
        Toast::success('Fatura paga com sucesso.');

        return redirect()->back();
    }

    public function unpay(Workspace $workspace, CreditCardBill $bill, BillService $billService): RedirectResponse
    {
        abort_if($bill->workspace_id !== $workspace->id, 404);
        $this->authorize('unpay', [$bill, $workspace]);

        $billService->undoPayment($bill);
        Toast::success('Fatura marcada como não paga.');

        return redirect()->back();
    }

    public function closeCurrent(Workspace $workspace): JsonResponse
    {
        $request = request();
        $cardUuid = $request->input('credit_card_id');

        abort_if(empty($cardUuid), 422, 'credit_card_id é obrigatório.');

        $card = CreditCard::where('uuid', $cardUuid)
            ->where('workspace_id', $workspace->id)
            ->firstOrFail();

        $bill = $card->bills()
            ->where('status', BillStatus::Open)
            ->orderBy('period_year', 'desc')
            ->orderBy('period_month', 'desc')
            ->firstOrFail();

        $this->billService->closeBill($bill);
        Toast::success('Fatura fechada com sucesso.');

        return response()->json(['message' => 'Fatura fechada com sucesso.']);
    }
}
