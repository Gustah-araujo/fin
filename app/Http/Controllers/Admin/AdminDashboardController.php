<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminUserService;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function index(): Response
    {
        return inertia('Admin/Dashboard', [
            'activeUsers' => AdminUserService::activeUsersCount(),
        ]);
    }
}
