<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanningResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $month = $this->resource['month'];
        $carbon = Carbon::parse($month.'-01');

        return [
            'month' => $month,
            'month_label' => $carbon->locale('pt_BR')->isoFormat('MMM/YYYY'),
            'expenses' => (float) $this->resource['expenses'],
            'incomes' => (float) $this->resource['incomes'],
            'balance' => (float) $this->resource['balance'],
        ];
    }
}
