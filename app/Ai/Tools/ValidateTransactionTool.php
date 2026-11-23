<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Enums\FinPendingMutationStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\FinPendingMutation;
use App\Models\Transaction;
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
     * @SuppressWarnings("PHPMD.StaticAccess")
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    public function handle(Request $request): string
    {
        $workspace = Workspace::where('uuid', $this->workspaceUuid)->firstOrFail();

        $transaction = $this->resolveTransaction($request, $workspace);

        if (is_string($transaction)) {
            return json_encode(['error' => $transaction], JSON_THROW_ON_ERROR);
        }

        [$type, $description, $value, $date, $account, $category, $tags, $isPaid] = $transaction;

        $duplicate = app(DuplicateDetectionService::class)->findDuplicate($workspace, $type, $value, $date, $description);

        $pendingTtl = config('ai.fin.pending_ttl', 30);
        $pendingTtl = is_numeric($pendingTtl) ? (int) $pendingTtl : 30;

        $pending = FinPendingMutation::create([
            'uuid' => Str::orderedUuid()->toString(),
            'workspace_id' => $workspace->id,
            'conversation_id' => $this->conversationId,
            'user_id' => $this->userId,
            'type' => $type->value,
            'payload' => $this->buildPayload($isPaid, $type, $description, $value, $date, $account, $category, $tags),
            'status' => FinPendingMutationStatus::Pending,
            'expires_at' => now()->addMinutes($pendingTtl),
        ]);

        return json_encode($this->buildResponse($pending, $type, $description, $value, $date, $account, $category, $tags, $isPaid, $duplicate), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Resolve and validate the transaction fields from the tool request.
     *
     * @return array{TransactionType, string, float, Carbon, ?Account, Category, list<string>, bool}|string
     *
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    private function resolveTransaction(Request $request, Workspace $workspace): array|string
    {
        $typeValue = (string) $request->string('type');

        if (! in_array($typeValue, ['income', 'expense'], true)) {
            return 'Tipo de transação inválido.';
        }

        $type = TransactionType::from($typeValue);

        $description = trim((string) $request->string('description'));

        if ($description === '') {
            return 'Descrição é obrigatória.';
        }

        $value = $request->float('value');

        if ($value <= 0) {
            return 'Valor deve ser positivo.';
        }

        $date = $this->resolveDate((string) $request->string('date'));

        if ($date === null) {
            return 'Data inválida.';
        }

        $account = $this->resolveAccount($workspace, trim((string) $request->string('account_id')));

        if ($account === false) {
            return 'Conta não encontrada neste workspace.';
        }

        $category = $this->resolveCategory($workspace, (string) $request->string('category_id'));

        if ($category === null) {
            return 'Categoria não encontrada neste workspace.';
        }

        $tags = array_values(array_filter(array_map('strval', $request->array('tags')), fn (string $t): bool => $t !== ''));

        return [$type, $description, $value, $date, $account, $category, $tags, $request->boolean('is_paid')];
    }

    /**
     * Parse the transaction date, returning null when invalid.
     *
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    private function resolveDate(string $dateRaw): ?Carbon
    {
        try {
            return Carbon::parse($dateRaw);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Resolve the account by UUID. Returns null when no account was provided
     * (account is optional) and false when the provided account is invalid.
     *
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    private function resolveAccount(Workspace $workspace, string $accountUuid): Account|false|null
    {
        if ($accountUuid === '') {
            return null;
        }

        return Account::where('uuid', $accountUuid)
            ->where('workspace_id', $workspace->id)
            ->first() ?? false;
    }

    /**
     * Resolve the category by UUID, returning null when not found.
     *
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    private function resolveCategory(Workspace $workspace, string $categoryUuid): ?Category
    {
        return Category::where('uuid', $categoryUuid)
            ->where('workspace_id', $workspace->id)
            ->first();
    }

    /**
     * Build the pending mutation payload, omitting the account when not linked.
     *
     * @param  list<string>  $tags
     * @return array<string, mixed>
     */
    private function buildPayload(bool $isPaid, TransactionType $type, string $description, float $value, Carbon $date, ?Account $account, Category $category, array $tags): array
    {
        $payload = [
            'type' => $type->value,
            'description' => $description,
            'value' => $value,
            'date' => $date->toDateString(),
            'category_id' => $category->uuid,
            'tags' => $tags,
        ];

        if ($account !== null) {
            $payload['account_id'] = $account->uuid;
        }

        if ($isPaid) {
            $payload['is_paid'] = true;
        }

        return $payload;
    }

    /**
     * Build the tool response for the model.
     *
     * @param  list<string>  $tags
     * @return array<string, mixed>
     *
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    private function buildResponse(FinPendingMutation $pending, TransactionType $type, string $description, float $value, Carbon $date, ?Account $account, Category $category, array $tags, bool $isPaid, ?Transaction $duplicate): array
    {
        return [
            'pending_id' => $pending->uuid,
            'type' => $type->value,
            'description' => $description,
            'value' => $value,
            'date' => $date->toDateString(),
            'account' => $account !== null ? ['uuid' => $account->uuid, 'name' => $account->name] : null,
            'category' => ['uuid' => $category->uuid, 'name' => $category->name],
            'tags' => $tags,
            'is_paid' => $isPaid,
            'duplicate_detected' => $duplicate !== null,
            'duplicate' => $duplicate !== null ? ['uuid' => $duplicate->uuid, 'description' => $duplicate->description, 'value' => (float) $duplicate->value, 'date' => $duplicate->date->format('Y-m-d')] : null,
        ];
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
            'account_id' => $schema->string()->description('Account UUID (optional). Omit when the transaction has no linked account.'),
            'is_paid' => $schema->boolean()->description('Se a transação já foi paga (despesa) ou recebida (receita).'),
            'category_id' => $schema->string()->required()->description('Category UUID.'),
            'tags' => $schema->array()->items($schema->string())->description('Optional list of tag UUIDs.'),
        ];
    }
}
