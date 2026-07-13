<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use App\Http\Requests\StoreInviteRequest;
use App\Http\Resources\InviteResource;
use App\Models\Invite;
use App\Models\Workspace;
use App\Services\InviteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class InviteController extends Controller
{
    public function store(StoreInviteRequest $request, Workspace $workspace, InviteService $inviteService): RedirectResponse
    {
        Gate::authorize("invite", $workspace);

        $invite = $inviteService->invite(
            $workspace,
            $request->user(),
            $request->validated()["email"],
            WorkspaceRole::from($request->validated()["role"]),
        );

        if (! $invite) {
            return back()->with("status", "Convite enviado.");
        }

        return back()->with("status", "Convite enviado com sucesso.");
    }

    public function accept(Invite $invite, InviteService $inviteService): RedirectResponse
    {
        $inviteService->accept($invite, request()->user());

        return redirect()->route("dashboard", ["workspace" => $invite->workspace->uuid])
            ->with("status", "Você entrou no workspace.");
    }

    public function decline(Invite $invite, InviteService $inviteService): RedirectResponse
    {
        $inviteService->decline($invite, request()->user());

        return back()->with("status", "Convite recusado.");
    }
}
