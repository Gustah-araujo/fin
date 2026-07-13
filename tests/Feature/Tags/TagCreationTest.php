<?php

namespace Tests\Feature\Tags;

use App\Enums\WorkspaceRole;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class TagCreationTest extends TestCase
{
    public function test_user_can_create_tag(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->post(route('tags.store', $workspace), [
                'name' => 'Urgente',
                'color' => '#FF0000',
            ]);

        $response->assertRedirect(route('tags.index', $workspace));

        $this->assertDatabaseHas('tags', [
            'workspace_id' => $workspace->id,
            'name' => 'Urgente',
            'color' => '#FF0000',
        ]);
    }

    public function test_tag_list_shows_all_tags(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        Tag::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'name' => 'Urgente',
        ]);
        Tag::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'name' => 'Viagem',
        ]);

        $response = $this->actingAs($user)
            ->get(route('tags.index', $workspace));

        $response->assertInertia(fn ($page) => $page
            ->component('Tags/Index', false)
            ->has('tags', 2)
        );
    }

    public function test_user_can_update_tag(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $tag = Tag::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'name' => 'Original',
        ]);

        $response = $this->actingAs($user)
            ->put(route('tags.update', ['workspace' => $workspace, 'tag' => $tag]), [
                'name' => 'Atualizada',
            ]);

        $response->assertRedirect(route('tags.index', $workspace));

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'name' => 'Atualizada',
        ]);
    }

    public function test_rejects_duplicate_tag_name(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        Tag::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'name' => 'Urgente',
        ]);

        $response = $this->actingAs($user)
            ->post(route('tags.store', $workspace), [
                'name' => 'Urgente',
                'color' => '#00FF00',
            ]);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_rejects_invalid_color_hex(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->post(route('tags.store', $workspace), [
                'name' => 'Teste',
                'color' => 'invalid',
            ]);

        $response->assertSessionHasErrors(['color']);
    }
}
