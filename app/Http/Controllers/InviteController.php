<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use App\Http\Requests\StoreInviteRequest;
use App\Models\Invite;
use App\Models\Workspace;
use App\Services\InviteService;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class InviteController extends Controller
{
    public function store(StoreInviteRequest $request, Workspace $workspace, InviteService $inviteService): RedirectResponse
    {
        Gate::authorize('invite', $workspace);

        $user = $request->user();

        abort_unless($user !== null, 401);

        $email = $request->validated('email');
        $role = $request->validated('role');

        $invite = $inviteService->invite(
            $workspace,
            $user,
            is_string($email) ? $email : '',
            WorkspaceRole::from(is_string($role) ? $role : ''),
        );

        if (! $invite) {
            Toast::error('Usuário não encontrado. O convite só pode ser enviado para usuários registrados.');

            return back();
        }

        Toast::success('Convite enviado com sucesso. Um email de notificação foi enviado.');

        return back();
    }

    public function accept(Invite $invite, InviteService $inviteService): RedirectResponse
    {
        $user = request()->user();

        abort_unless($user !== null, 401);

        $inviteService->accept($invite, $user);

        Toast::success('Você entrou no workspace.');

        $workspace = $invite->workspace;

        abort_unless($workspace instanceof Workspace, 404);

        return redirect()->route('dashboard', ['workspace' => $workspace->uuid]);
    }

    public function decline(Invite $invite, InviteService $inviteService): RedirectResponse
    {
        $user = request()->user();

        abort_unless($user !== null, 401);

        $inviteService->decline($invite, $user);

        Toast::success('Convite recusado.');

        return back();
    }
}
