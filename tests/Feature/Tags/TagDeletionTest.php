<?php

namespace Tests\Feature\Tags;

use App\Enums\WorkspaceRole;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class TagDeletionTest extends TestCase
{
    public function test_soft_deletes_tag(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $tag = Tag::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->delete(route('tags.destroy', ['workspace' => $workspace, 'tag' => $tag]));

        $response->assertRedirect(route('tags.index', $workspace));

        $this->assertSoftDeleted('tags', ['id' => $tag->id]);
    }
}
