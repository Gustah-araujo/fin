<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Tests\TestCase;

class ImportSmokeTest extends TestCase
{
    public function test_import_create_page_renders(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($user, ['role' => WorkspaceRole::Admin->value]);

        $response = $this->actingAs($user)
            ->get(route('import.create', $workspace));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Imports/Index'));
    }
}
