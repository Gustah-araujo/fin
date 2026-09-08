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

        $invite = $inviteService->invite(
            $workspace,
            $request->user(),
            $request->validated()['email'],
            WorkspaceRole::from($request->validated()['role']),
        );

        if (! $invite) {
            Toast::success('Convite enviado.');

            return back();
        }

        Toast::success('Convite enviado com sucesso.');

        return back();
    }

    public function accept(Invite $invite, InviteService $inviteService): RedirectResponse
    {
        $inviteService->accept($invite, request()->user());

        Toast::success('Você entrou no workspace.');

        return redirect()->route('dashboard', ['workspace' => $invite->workspace->uuid]);
    }

    public function decline(Invite $invite, InviteService $inviteService): RedirectResponse
    {
        $inviteService->decline($invite, request()->user());

        Toast::success('Convite recusado.');

        return back();
    }
}
