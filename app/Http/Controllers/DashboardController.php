<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Workspace $workspace, Request $request, DashboardService $service): Response
    {
        $this->authorize('view', $workspace);

        $month = $request->query('month');

        $overview = $service->getMonthOverview($workspace, is_string($month) ? $month : null);

        return inertia('Home', [
            'month' => $overview['month'],
            'kpis' => $overview['kpis'],
            'expenseGroups' => $overview['expense_groups'],
            'categories' => $overview['categories'],
            'hasGroups' => $workspace->expenseGroups()->exists(),
        ]);
    }
}
