<?php

declare(strict_types=1);

namespace Tests\Feature\Incomes;

use App\Models\Tag;
use App\Models\Transaction;

class IncomeDeletionTest extends IncomeTestCase
{
    public function test_deleted_income_does_not_appear_in_list(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);
        $income = Transaction::factory()->income()->create([
            'workspace_id' => $workspace->id, 'account_id' => $account->id, 'category_id' => $category->id, 'created_by' => $user->id,
            'description' => 'Disappear',
        ]);

        $this->actingAs($user)
            ->delete(route('incomes.destroy', [$workspace, $income]));

        $response = $this->actingAs($user)
            ->get(route('incomes.index', $workspace));

        $response->assertInertia(fn ($page) => $page->has('incomes.data', 0));
    }

    public function test_soft_delete_preserves_record_in_db(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);
        $income = Transaction::factory()->income()->create([
            'workspace_id' => $workspace->id, 'account_id' => $account->id, 'category_id' => $category->id, 'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->delete(route('incomes.destroy', [$workspace, $income]));

        $this->assertSoftDeleted('transactions', ['id' => $income->id]);
        $this->assertDatabaseHas('transactions', ['id' => $income->id]);
    }

    public function test_soft_delete_preserves_tag_relations_for_restore(): void
    {
        [$user, $workspace] = $this->createWorkspaceWithMember();
        $account = $this->createAccount($workspace, $user);
        $category = $this->createIncomeCategory($workspace, $user);
        $tag = Tag::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
        ]);
        $income = Transaction::factory()->income()->create([
            'workspace_id' => $workspace->id, 'account_id' => $account->id, 'category_id' => $category->id, 'created_by' => $user->id,
        ]);
        $income->tags()->attach($tag->id);

        $this->actingAs($user)
            ->delete(route('incomes.destroy', [$workspace, $income]));

        $this->assertDatabaseHas('taggables', [
            'taggable_type' => Transaction::class,
            'taggable_id' => $income->id,
            'tag_id' => $tag->id,
        ]);
    }
}
