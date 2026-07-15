<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BillStatus;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\CreditCardBill;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillService
{
    public function __construct(
        private readonly CreditCardService $creditCardService,
        private readonly AccountService $accountService,
    ) {}
    public function computeBillPeriod(CreditCard $card, Carbon $date): array
    {
        $closingDay = min($card->closing_day, $date->daysInMonth);
        $candidateClosing = Carbon::createFromDate($date->year, $date->month, $closingDay)->startOfDay();

        if ($date->copy()->startOfDay()->lte($candidateClosing)) {
            return ['year' => $date->year, 'month' => $date->month];
        }

        $next = $date->copy()->addMonthNoOverflow();
        return ['year' => $next->year, 'month' => $next->month];
    }

    public function computeClosingDate(CreditCard $card, int $year, int $month): Carbon
    {
        $day = min($card->closing_day, Carbon::createFromDate($year, $month, 1)->daysInMonth);
        return Carbon::createFromDate($year, $month, $day);
    }

    public function computeDueDate(CreditCard $card, int $year, int $month): Carbon
    {
        $day = min($card->due_day, Carbon::createFromDate($year, $month, 1)->daysInMonth);
        return Carbon::createFromDate($year, $month, $day);
    }

    public function findOrCreateBill(CreditCard $card, Carbon $date, int $createdBy): CreditCardBill
    {
        $period = $this->computeBillPeriod($card, $date);

        $bill = CreditCardBill::where('credit_card_id', $card->id)
            ->where('period_year', $period['year'])
            ->where('period_month', $period['month'])
            ->first();

        if ($bill) {
            return $bill;
        }

        return CreditCardBill::create([
            'uuid' => Str::orderedUuid()->toString(),
            'credit_card_id' => $card->id,
            'workspace_id' => $card->workspace_id,
            'period_year' => $period['year'],
            'period_month' => $period['month'],
            'closing_date' => $this->computeClosingDate($card, $period['year'], $period['month']),
            'due_date' => $this->computeDueDate($card, $period['year'], $period['month']),
            'status' => BillStatus::Open->value,
            'total_amount' => 0,
            'created_by' => $createdBy,
        ]);
    }

    public function recalculateBillTotal(CreditCardBill $bill): void
    {
        $total = Transaction::where('credit_card_bill_id', $bill->id)
            ->whereNull('transactions.deleted_at')
            ->sum('value');

        $bill->total_amount = (float) $total;
        $bill->save();
    }

    public function closeBill(CreditCardBill $bill): void
    {
        $bill->status = BillStatus::Closed;
        $bill->closed_at = now();
        $bill->save();
    }

    public function closeBillsBefore(Carbon $date): int
    {
        $bills = CreditCardBill::where('status', BillStatus::Open->value)
            ->where('closing_date', '<', $date->toDateString())
            ->get();

        $count = 0;
        foreach ($bills as $bill) {
            try {
                $this->closeBill($bill);
                $count++;
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed to close bill {$bill->id}: " . $e->getMessage());
            }
        }

        return $count;
    }

    public function payBill(CreditCardBill $bill, Account $account, User $user): Transaction
    {
        return DB::transaction(function () use ($bill, $account, $user) {
            $workspace = $bill->workspace;
            $card = $bill->creditCard;

            $category = $this->creditCardService->ensurePaymentCategory($workspace);

            $monthLabel = str_pad((string) $bill->period_month, 2, '0', STR_PAD_LEFT);

            $paymentTransaction = Transaction::create([
                'uuid' => Str::orderedUuid()->toString(),
                'workspace_id' => $workspace->id,
                'account_id' => $account->id,
                'credit_card_id' => null,
                'credit_card_bill_id' => null,
                'category_id' => $category->id,
                'type' => 'expense',
                'description' => "Pagamento Fatura {$card->name} {$monthLabel}/{$bill->period_year}",
                'value' => $bill->total_amount,
                'date' => now()->toDateString(),
                'paid_at' => now(),
                'created_by' => $user->id,
            ]);

            $bill->status = BillStatus::Paid;
            $bill->paid_at = now();
            $bill->paid_to_account_id = $account->id;
            $bill->payment_transaction_id = $paymentTransaction->id;
            $bill->save();

            $this->accountService->recalculateBalance($account);
            $this->creditCardService->recalculateAvailableLimit($card->fresh());

            return $paymentTransaction;
        });
    }

    public function undoPayment(CreditCardBill $bill): void
    {
        DB::transaction(function () use ($bill) {
            $paymentTransaction = $bill->paymentTransaction;
            $account = $bill->paymentAccount;
            $card = $bill->creditCard;

            $bill->status = BillStatus::Closed;
            $bill->paid_at = null;
            $bill->paid_to_account_id = null;
            $bill->payment_transaction_id = null;
            $bill->save();

            if ($paymentTransaction) {
                $paymentTransaction->delete();
            }

            if ($account) {
                $this->accountService->recalculateBalance($account);
            }

            $this->creditCardService->recalculateAvailableLimit($card->fresh());
        });
    }
}
