<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Enums\FinPendingMutationStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\FinPendingMutation;
use App\Models\Workspace;
use App\Services\DuplicateDetectionService;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

#[Strict]
class ValidateTransactionTool implements Tool
{
    public function __construct(
        protected string $workspaceUuid,
        protected string $conversationId,
        protected int $userId,
    ) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Validate and stage a transaction (income/expense) for user confirmation. Extracts the transaction fields, checks for duplicates, and creates a pending mutation. This does NOT persist the transaction — the user must confirm first via confirm_transaction.';
    }

    /**
     * Execute the tool.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function handle(Request $request): string
    {
        $workspace = Workspace::where('uuid', $this->workspaceUuid)->firstOrFail();

        $typeValue = (string) $request->string('type');

        if (! in_array($typeValue, ['income', 'expense'], true)) {
            return json_encode(['error' => 'Tipo de transação inválido.']);
        }

        $type = TransactionType::from($typeValue);

        $description = trim((string) $request->string('description'));

        if ($description === '') {
            return json_encode(['error' => 'Descrição é obrigatória.']);
        }

        $value = $request->float('value');

        if ($value <= 0) {
            return json_encode(['error' => 'Valor deve ser positivo.']);
        }

        $dateRaw = (string) $request->string('date');

        try {
            $date = Carbon::parse($dateRaw);
        } catch (\Throwable) {
            return json_encode(['error' => 'Data inválida.']);
        }

        $accountUuid = (string) $request->string('account_id');
        $account = Account::where('uuid', $accountUuid)
            ->where('workspace_id', $workspace->id)
            ->first();

        if ($account === null) {
            return json_encode(['error' => 'Conta não encontrada neste workspace.']);
        }

        $categoryUuid = (string) $request->string('category_id');
        $category = Category::where('uuid', $categoryUuid)
            ->where('workspace_id', $workspace->id)
            ->first();

        if ($category === null) {
            return json_encode(['error' => 'Categoria não encontrada neste workspace.']);
        }

        $tags = array_values(array_filter(array_map('strval', $request->array('tags')), fn (string $t): bool => $t !== ''));

        $duplicate = app(DuplicateDetectionService::class)->findDuplicate($workspace, $type, $value, $date, $description);

        $pending = FinPendingMutation::create([
            'uuid' => Str::orderedUuid()->toString(),
            'workspace_id' => $workspace->id,
            'conversation_id' => $this->conversationId,
            'user_id' => $this->userId,
            'type' => $type->value,
            'payload' => [
                'type' => $type->value,
                'description' => $description,
                'value' => $value,
                'date' => $date->toDateString(),
                'account_id' => $account->uuid,
                'category_id' => $category->uuid,
                'tags' => $tags,
            ],
            'status' => FinPendingMutationStatus::Pending,
            'expires_at' => now()->addMinutes((int) config('ai.fin.pending_ttl', 30)),
        ]);

        return json_encode([
            'pending_id' => $pending->uuid,
            'type' => $type->value,
            'description' => $description,
            'value' => $value,
            'date' => $date->toDateString(),
            'account' => ['uuid' => $account->uuid, 'name' => $account->name],
            'category' => ['uuid' => $category->uuid, 'name' => $category->name],
            'tags' => $tags,
            'duplicate_detected' => $duplicate !== null,
            'duplicate' => $duplicate !== null ? ['uuid' => $duplicate->uuid, 'description' => $duplicate->description, 'value' => (float) $duplicate->value, 'date' => $duplicate->date->format('Y-m-d')] : null,
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
            'type' => $schema->string()->enum(['income', 'expense'])->required()->description('Transaction type.'),
            'description' => $schema->string()->required()->description('Transaction description.'),
            'value' => $schema->number()->required()->description('Transaction value (positive number).'),
            'date' => $schema->string()->required()->description('Transaction date (Y-m-d).'),
            'account_id' => $schema->string()->required()->description('Account UUID.'),
            'category_id' => $schema->string()->required()->description('Category UUID.'),
            'tags' => $schema->array()->items($schema->string())->description('Optional list of tag UUIDs.'),
        ];
    }
}
