<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminUserResource;
use App\Http\Resources\WorkspaceResource;
use App\Models\User;
use App\Services\Admin\AdminUserService;
use App\Services\Datatable\DatatableConfig;
use App\Services\Datatable\DatatableService;
use App\Services\Datatable\Filter;
use App\Support\Toast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function index(): Response
    {
        return inertia('Admin/Users/Index');
    }

    public function datatable(Request $request): JsonResponse
    {
        $query = User::query()->withCount('workspaces');

        return app(DatatableService::class)->paginate($query, $request, $this->datatableConfig());
    }

    private function datatableConfig(): DatatableConfig
    {
        return DatatableConfig::make(AdminUserResource::class)
            ->searchable(['name', 'email'])
            ->filter('email_verified_at', Filter::select(fn (Builder $q, string $v) => $v === 'verified' ? $q->whereNotNull('email_verified_at') : $q->whereNull('email_verified_at')))
            ->sortable(['name', 'email', 'created_at'])
            ->defaultSort('created_at', 'desc')
            ->perPage(25);
    }

    public function show(User $user): Response
    {
        return inertia('Admin/Users/Show', [
            'user' => new AdminUserResource($user),
            'workspaces' => WorkspaceResource::collection(
                $user->workspaces()->withPivot('role')->get()
            ),
        ]);
    }

    public function resendVerification(User $user): RedirectResponse
    {
        AdminUserService::resendVerification($user);
        Toast::success('E-mail de verificação reenviado.');

        return back();
    }

    public function verify(User $user): RedirectResponse
    {
        AdminUserService::manuallyVerify($user);
        Toast::success('Usuário marcado como verificado.');

        return back();
    }
}
