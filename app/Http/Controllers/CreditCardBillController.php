<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PayBillRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\CreditCardBillResource;
use App\Models\CreditCardBill;
use App\Models\Workspace;
use App\Services\BillService;
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
            'card' => new \App\Http\Resources\CreditCardResource($bill->creditCard),
            'accounts' => AccountResource::collection(
                $workspace->accounts()->orderBy('name')->get()
            ),
        ]);
    }

    public function pay(PayBillRequest $request, Workspace $workspace, CreditCardBill $bill, BillService $billService): RedirectResponse
    {
        abort_if($bill->workspace_id !== $workspace->id, 404);
        $this->authorize('pay', [$bill, $workspace]);

        $account = \App\Models\Account::where('uuid', $request->validated()['account_id'])
            ->where('workspace_id', $workspace->id)
            ->firstOrFail();

        $billService->payBill($bill, $account, $request->user());

        return redirect()->back();
    }

    public function unpay(Workspace $workspace, CreditCardBill $bill, BillService $billService): RedirectResponse
    {
        abort_if($bill->workspace_id !== $workspace->id, 404);
        $this->authorize('unpay', [$bill, $workspace]);

        $billService->undoPayment($bill);

        return redirect()->back();
    }
}
