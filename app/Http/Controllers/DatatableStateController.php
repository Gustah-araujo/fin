<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\Datatable\TableStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DatatableStateController extends Controller
{
    private const ENTITY_MAP = [
        'transactions' => 'transactions',
        'incomes' => 'incomes',
        'recurrences' => 'recurrences',
    ];

    public function destroy(Request $request, Workspace $workspace, string $entity): JsonResponse
    {
        if (! Gate::allows('view', $workspace)) {
            abort(404);
        }

        $tableKey = self::ENTITY_MAP[$entity] ?? abort(404);

        app(TableStateService::class)->clear($request, $tableKey);

        return response()->json(['message' => 'Estado limpo.']);
    }
}
