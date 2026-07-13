<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InviteStatus;
use App\Enums\WorkspaceRole;
use App\Models\Invite;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class InviteService
{
    public function __construct(
        private readonly WorkspaceService $workspaceService,
    ) {}

    public function invite(Workspace $workspace, User $inviter, string $email, WorkspaceRole $role): ?Invite
    {
        $targetUser = User::where("email", $email)->first();

        if (! $targetUser) {
            return null;
        }

        if ($workspace->members()->where("user_id", $targetUser->id)->exists()) {
            throw new HttpException(422, "Este usuário já pertence ao workspace.");
        }

        $existingInvite = Invite::where("workspace_id", $workspace->id)
            ->where("email", $email)
            ->where("status", InviteStatus::Pending)
            ->first();

        if ($existingInvite) {
            return $existingInvite;
        }

        return Invite::create([
            "uuid" => Str::orderedUuid()->toString(),
            "workspace_id" => $workspace->id,
            "email" => $email,
            "role" => $role,
            "inviter_id" => $inviter->id,
            "status" => InviteStatus::Pending,
        ]);
    }

    public function accept(Invite $invite, User $user): void
    {
        if ($invite->status !== InviteStatus::Pending) {
            throw new HttpException(422, "Este convite não está mais pendente.");
        }

        if ($invite->email !== $user->email) {
            throw new HttpException(403, "Este convite não pertence a você.");
        }

        $this->workspaceService->addMember(
            $invite->workspace,
            $user,
            $invite->role,
        );

        $invite->forceFill([
            "status" => InviteStatus::Accepted,
        ])->save();
    }

    public function decline(Invite $invite, User $user): void
    {
        if ($invite->email !== $user->email) {
            throw new HttpException(403, "Este convite não pertence a você.");
        }

        $invite->forceFill([
            "status" => InviteStatus::Declined,
        ])->save();
    }

    public function getPendingInvites(User $user): Collection
    {
        return Invite::with(["workspace", "inviter"])
            ->where("email", $user->email)
            ->where("status", InviteStatus::Pending)
            ->get();
    }
}
