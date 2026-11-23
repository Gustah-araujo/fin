<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{month: string, expenses: float, incomes: float, balance: float} $resource
 */
class PlanningResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $month = $this->resource['month'];
        $carbon = Carbon::parse($month.'-01');
        $carbon->locale('pt_BR');

        return [
            'month' => $month,
            'month_label' => $carbon->isoFormat('MMM/YYYY'),
            'expenses' => (float) $this->resource['expenses'],
            'incomes' => (float) $this->resource['incomes'],
            'balance' => (float) $this->resource['balance'],
        ];
    }
}
