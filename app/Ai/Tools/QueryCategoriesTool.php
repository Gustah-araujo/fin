<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Enums\TransactionType;
use App\Models\Category;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

#[Strict]
class QueryCategoriesTool implements Tool
{
    public function __construct(
        protected string $workspaceId,
    ) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Query available categories in the workspace. Returns uuid, name, type, color, and icon for each category. Use this to find the correct category_uuid for transactions.';
    }

    /**
     * Execute the tool.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function handle(Request $request): string
    {
        $type = (string) $request->string('type');

        $query = Category::query()
            ->where('workspace_id', $this->workspaceId);

        if ($type !== '' && $type !== 'both') {
            $query->where('type', TransactionType::from($type));
        }

        $categories = $query
            ->orderBy('position')
            ->orderBy('name')
            ->get(['uuid', 'name', 'type', 'color', 'icon', 'is_system']);

        return json_encode([
            'count' => $categories->count(),
            'categories' => $categories->map(fn (Category $c): array => [
                'uuid' => $c->uuid,
                'name' => $c->name,
                'type' => $c->type->value,
                'color' => $c->color,
                'icon' => $c->icon,
                'is_system' => $c->is_system,
            ])->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Get the tool's schema definition.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->enum(['expense', 'income', 'both'])
                ->description('Filter by transaction type. Use "both" to return all categories.'),
        ];
    }
}
