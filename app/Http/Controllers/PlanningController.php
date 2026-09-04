<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\PlanningResource;
use App\Models\Transaction;
use App\Models\Workspace;
use App\Services\PlanningService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;

class PlanningController extends Controller
{
    public function index(Workspace $workspace, Request $request): InertiaResponse
    {
        $this->authorize('viewAny', [Transaction::class, $workspace]);

        $dateStart = $this->input($request, 'date_start')
            ? Carbon::parse($this->input($request, 'date_start'))
            : Carbon::now()->startOfMonth();

        $dateEnd = $this->input($request, 'date_end')
            ? Carbon::parse($this->input($request, 'date_end'))
            : Carbon::now()->addMonths(6)->endOfMonth();

        $this->validateDateRange($dateStart, $dateEnd);

        $service = app(PlanningService::class);
        $projection = $service->getProjection($workspace, $dateStart, $dateEnd);

        $filter = $this->input($request, 'filter') ?: 'all';

        $totals = [
            'expenses' => collect($projection)->sum('expenses'),
            'incomes' => collect($projection)->sum('incomes'),
            'balance' => collect($projection)->sum('balance'),
        ];

        return inertia('Planning/Index', [
            'projection' => PlanningResource::collection($projection),
            'date_start' => $dateStart->format('Y-m-d'),
            'date_end' => $dateEnd->format('Y-m-d'),
            'totals' => $totals,
            'filter' => $filter,
        ]);
    }

    public function monthDetail(Workspace $workspace, Request $request): JsonResponse
    {
        $this->authorize('viewAny', [Transaction::class, $workspace]);

        $month = $this->input($request, 'month') ?: Carbon::now()->format('Y-m');

        $service = app(PlanningService::class);
        $detail = $service->getMonthDetail($workspace, Carbon::parse($month.'-01'));

        return response()->json([
            'month' => $month,
            'expenses' => $detail['expenses'],
            'incomes' => $detail['incomes'],
        ]);
    }

    /**
     * Read a value from query/body input or HTTP header.
     */
    private function input(Request $request, string $key): ?string
    {
        return $request->input($key) ?? $request->header($key);
    }

    private function validateDateRange(Carbon $dateStart, Carbon $dateEnd): void
    {
        if ($dateEnd->lte($dateStart)) {
            abort(422, 'A data fim deve ser posterior à data início.');
        }

        if ($dateStart->diffInMonths($dateEnd) > 24) {
            abort(422, 'Período máximo de 24 meses.');
        }
    }
}
