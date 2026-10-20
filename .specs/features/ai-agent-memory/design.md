# Design: AI Agent Memory Architecture

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                        SCHEDULER (02:00 daily)                   │
│                      routes/console.php                          │
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│               RunFinancialAnalysisCommand                        │
│  for each Workspace:                                             │
│    1. Instantiate ScheduledFinancialAnalysisAgent(workspace)     │
│    2. prompt("Analyze recent finances")                          │
│    3. For each insight → AgentMemoryService::create()           │
│    4. Notify all members → FinancialInsightNotification          │
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│           ScheduledFinancialAnalysisAgent                        │
│           (extends BaseAgent, implements HasStructuredOutput)    │
│                                                                  │
│  tools():                                                        │
│    - QueryTransactionsTool(workspaceUuid)                        │
│    - QueryRecurrencesTool(workspaceUuid)                         │
│    - CreateMemoryTool(workspaceUuid)    ← from BaseAgent        │
│    - ReadMemoryTool(workspaceUuid)      ← from BaseAgent        │
│    - UpdateMemoryTool(workspaceUuid)    ← from BaseAgent        │
│    - DeleteMemoryTool(workspaceUuid)    ← from BaseAgent        │
│                                                                  │
│  instructions():                                                 │
│    - System role definition                                      │
│    - Memory Map section (indexed memories)                       │
│    - Analysis instructions                                       │
│                                                                  │
│  schema():                                                       │
│    { insights: [{ type, title, description, severity, data }] } │
└─────────────────────────────────────────────────────────────────┘
```

## Component Design

### 1. Migration: `agent_memories` table

```php
Schema::create('agent_memories', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
    $table->string('title');
    $table->text('content');
    $table->string('type'); // insight, pattern, anomaly, summary
    $table->string('source')->nullable(); // agent class name
    $table->timestamp('expires_at')->nullable();
    $table->timestamps();

    $table->index(['workspace_id', 'type']);
    $table->index(['workspace_id', 'expires_at']);
});
```

### 2. Enum: `MemoryType`

```php
enum MemoryType: string
{
    case Insight = 'insight';
    case Pattern = 'pattern';
    case Anomaly = 'anomaly';
    case Summary = 'summary';

    public function label(): string { ... }
}
```

### 3. Model: `AgentMemory`

```php
class AgentMemory extends Model
{
    use HasUuids;

    protected $fillable = ['id', 'workspace_id', 'title', 'content', 'type', 'source', 'expires_at'];

    protected function casts(): array
    {
        return [
            'type' => MemoryType::class,
            'expires_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo { ... }
    public function scopeActive($query) { ... } // non-expired only
}
```

### 4. Workspace Relationship

Add to `Workspace` model:
```php
public function agentMemories(): HasMany
{
    return $this->hasMany(AgentMemory::class);
}
```

### 5. Service: `AgentMemoryService`

```php
class AgentMemoryService
{
    public function create(Workspace $workspace, array $data): AgentMemory;
    public function list(Workspace $workspace): Collection;       // active only
    public function find(Workspace $workspace, string $uuid): AgentMemory;
    public function update(AgentMemory $memory, array $data): AgentMemory;
    public function delete(AgentMemory $memory): void;
    public function formatForPrompt(Workspace $workspace): string; // memory map string
}
```

### 6. BaseAgent (Abstract)

```php
abstract class BaseAgent implements Agent, HasTools
{
    use Promptable;

    public function __construct(protected Workspace $workspace) {}

    public function tools(): iterable
    {
        return [
            new CreateMemoryTool($this->workspace->uuid),
            new ReadMemoryTool($this->workspace->uuid),
            new UpdateMemoryTool($this->workspace->uuid),
            new DeleteMemoryTool($this->workspace->uuid),
            ...$this->agentTools(), // subclass hook
        ];
    }

    abstract protected function agentTools(): iterable;

    protected function memoryMap(): string
    {
        return AgentMemoryService::formatForPrompt($this->workspace);
    }
}
```

### 7. Memory Tools (in `app/Ai/Tools/Memory/`)

| Tool | Purpose | Schema |
|------|---------|--------|
| `CreateMemoryTool` | Create memory | title, content, type, source?, expires_at? |
| `ReadMemoryTool` | Read all or by UUID | uuid? (optional) |
| `UpdateMemoryTool` | Update existing | uuid, title?, content?, type? |
| `DeleteMemoryTool` | Delete by UUID | uuid |

All tools use `AgentMemoryService` internally, scoped by workspace UUID from constructor.

### 8. ScheduledFinancialAnalysisAgent

```php
class ScheduledFinancialAnalysisAgent extends BaseAgent implements HasStructuredOutput
{
    protected function agentTools(): iterable
    {
        return [
            new QueryTransactionsTool($this->workspace->uuid),
            new QueryRecurrencesTool($this->workspace->uuid),
        ];
    }

    public function instructions(): string
    {
        return <<<PROMPT
            You are a financial analyst agent. Analyze the workspace's recent 30-day
            transactions and recurrences to identify patterns, anomalies, and insights.

            ## Memory Map (previously identified knowledge):
            {$this->memoryMap()}

            Instructions:
            - Use query_transactions to fetch recent 30-day data
            - Use query_recurrences to check active patterns
            - Compare current data against existing memories to avoid duplicates
            - Identify: spending anomalies, upcoming due dates, category trends, recurring pattern changes
            - Use create_memory to persist new insights for future runs
            - Return structured insights array
        PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'insights' => $schema->array()->items(
                $schema->object([
                    'type' => $schema->string()->enum(['anomaly', 'pattern', 'insight']),
                    'title' => $schema->string()->required(),
                    'description' => $schema->string()->required(),
                    'severity' => $schema->string()->enum(['low', 'medium', 'high']),
                    'data' => $schema->object()->nullable(), // supporting data
                ])
            )->required(),
        ];
    }
}
```

### 9. Notification: `FinancialInsightNotification`

```php
class FinancialInsightNotification extends Notification
{
    public function __construct(
        private readonly Workspace $workspace,
        private readonly int $insightCount,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'workspace_id' => $this->workspace->uuid,
            'insight_count' => $this->insightCount,
            'message' => "{$this->insightCount} novos insights financeiros identificados para {$this->workspace->name}.",
        ];
    }
}
```

### 10. Command: `RunFinancialAnalysisCommand`

```php
class RunFinancialAnalysisCommand extends Command
{
    protected $signature = 'agent:financial-analysis';
    protected $description = 'Run scheduled financial analysis for all workspaces';

    public function handle(): int
    {
        Workspace::query()->each(function (Workspace $workspace) {
            $agent = new ScheduledFinancialAnalysisAgent($workspace);
            $response = $agent->prompt('Analyze recent finances for insights.');
            $insights = $response['insights'] ?? [];

            foreach ($insights as $insight) {
                AgentMemoryService::create($workspace, [
                    'title' => $insight['title'],
                    'content' => $insight['description'],
                    'type' => $insight['type'],
                    'source' => ScheduledFinancialAnalysisAgent::class,
                ]);
            }

            if (count($insights) > 0) {
                $notification = new FinancialInsightNotification($workspace, count($insights));
                $workspace->members->each->notify($notification);
            }
        });

        return Command::SUCCESS;
    }
}
```

### 11. Schedule Registration

In `routes/console.php`:
```php
use App\Console\Commands\RunFinancialAnalysisCommand;

Schedule::command(RunFinancialAnalysisCommand::class)->dailyAt('02:00');
```

## Data Flow

1. **Scheduler** triggers `RunFinancialAnalysisCommand` at 02:00 daily
2. **Command** iterates all workspaces
3. For each workspace:
   - `ScheduledFinancialAnalysisAgent` is instantiated with the workspace
   - Agent's `instructions()` includes the memory map from existing `agent_memories`
   - Agent calls `QueryTransactionsTool` and `QueryRecurrencesTool` to gather data
   - Agent uses `ReadMemoryTool` to check existing knowledge
   - Agent produces structured output with insights
   - Command creates new memories via `AgentMemoryService::create()`
   - Command sends `FinancialInsightNotification` to all workspace members
4. Next execution: new memories appear in the memory map, preventing duplicate insights

## Error Handling

- Agent failures per workspace are caught and logged; other workspaces continue processing
- Empty workspaces (no members) are skipped
- Tool execution errors are handled by Laravel AI SDK's built-in retry/failover
- Memory creation failures are logged but don't block notification delivery

## Testing Strategy

| Component | Test Type | What |
|-----------|-----------|------|
| Migration | Feature | Table exists with correct columns |
| AgentMemory model | Feature | UUID, casts, workspace relationship |
| AgentMemoryService | Feature | CRUD scoped to workspace |
| BaseAgent | Feature | Memory injection in instructions, tools present |
| Memory Tools | Feature | Each tool creates/reads/updates/deletes |
| ScheduledFinancialAnalysisAgent | Feature | Structured output, tools registered |
| FinancialInsightNotification | Feature | via includes database, correct payload |
| RunFinancialAnalysisCommand | Feature | Processes workspaces, sends notifications |
| ScheduledFinancialAnalysisAgent | Fake test | Schema validation, structured response |
