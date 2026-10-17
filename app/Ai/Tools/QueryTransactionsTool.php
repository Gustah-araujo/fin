<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

#[Strict]
class QueryTransactionsTool implements Tool
{
    private const DEFAULT_LIMIT = 50;

    private const MAX_LIMIT = 100;

    private const DEFAULT_ORDER = 'date';

    private const DEFAULT_DIRECTION = 'desc';

    private const ALLOWED_ORDER_FIELDS = ['date', 'value', 'description', 'created_at'];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        protected string $workspaceId,
    ) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Query transactions in the workspace with optional filters (date range, type, value, recurrence, category, ordering). Use this to detect potential duplicates by searching for transactions with similar date and value.';
    }

    /**
     * Execute the tool.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function handle(Request $request): string
    {
        $query = $this->buildBaseQuery($request);
        [$orderBy, $direction, $limit] = $this->resolveOrdering($request);

        $query->orderBy($orderBy, $direction)->limit($limit);

        return $this->formatResponse($query->get(['uuid', 'description', 'value', 'type', 'date', 'category_id', 'recurrence_id']));
    }

    /**
     * Build the base query with all filters applied.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    private function buildBaseQuery(Request $request): Builder
    {
        $query = Transaction::query()
            ->where('workspace_id', $this->workspaceId);

        $this->applyDateRange($request, $query);
        $this->applyTypeFilter($request, $query);

        if ($request->has('value')) {
            $query->where('value', $request->float('value'));
        }

        $this->applyUuidFilter($request, $query, 'recurrence_id');
        $this->applyUuidFilter($request, $query, 'category_id');

        return $query;
    }

    private function applyDateRange(Request $request, Builder $query): void
    {
        $startDate = (string) $request->string('start_date');
        $endDate = (string) $request->string('end_date');

        if ($startDate !== '') {
            $query->whereDate('date', '>=', $startDate);
        }

        if ($endDate !== '') {
            $query->whereDate('date', '<=', $endDate);
        }
    }

    /**
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    private function applyTypeFilter(Request $request, Builder $query): void
    {
        $type = (string) $request->string('type');

        if ($type !== '') {
            $query->where('type', TransactionType::from($type));
        }
    }

    private function applyUuidFilter(Request $request, Builder $query, string $field): void
    {
        $value = (string) $request->string($field);

        if ($value !== '') {
            $query->where($field, $value);
        }
    }

    /**
     * @return array{string, string, int}
     */
    private function resolveOrdering(Request $request): array
    {
        $orderBy = (string) $request->string('order_by');
        $direction = (string) $request->string('direction');
        $limit = $request->integer('limit') ?: self::DEFAULT_LIMIT;

        return [
            in_array($orderBy, self::ALLOWED_ORDER_FIELDS, true) ? $orderBy : self::DEFAULT_ORDER,
            in_array($direction, self::ALLOWED_DIRECTIONS, true) ? $direction : self::DEFAULT_DIRECTION,
            min(max($limit, 1), self::MAX_LIMIT),
        ];
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     */
    private function formatResponse(Collection $transactions): string
    {
        return json_encode([
            'count' => $transactions->count(),
            'transactions' => $transactions->map(fn (Transaction $t): array => [
                'uuid' => $t->uuid,
                'description' => $t->description,
                'value' => (float) $t->value,
                'type' => $t->type->value,
                'date' => $t->date->format('Y-m-d'),
                'category_id' => $t->category_id,
                'recurrence_id' => $t->recurrence_id,
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
            'start_date' => $schema->string()
                ->description('Start date filter (Y-m-d). Optional — omit to set only an end date.'),
            'end_date' => $schema->string()
                ->description('End date filter (Y-m-d). Optional — omit to set only a start date.'),
            'type' => $schema->string()
                ->enum(['expense', 'income'])
                ->description('Filter by transaction type.'),
            'value' => $schema->number()
                ->description('Filter by exact value match.'),
            'recurrence_id' => $schema->string()
                ->description('Filter by recurrence UUID.'),
            'category_id' => $schema->string()
                ->description('Filter by category UUID.'),
            'order_by' => $schema->string()
                ->enum(['date', 'value', 'description', 'created_at'])
                ->description('Field to order by. Default: date.'),
            'direction' => $schema->string()
                ->enum(['asc', 'desc'])
                ->description('Sort direction. Default: desc.'),
            'limit' => $schema->integer()
                ->description('Maximum results to return (1-100). Default: 50.'),
        ];
    }
}
