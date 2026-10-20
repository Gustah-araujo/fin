# Tasks: AI Agent Memory Architecture + Scheduled Financial Analysis

## Task 1: Migration & Enum
**What:** Create `agent_memories` table migration and `MemoryType` enum.
**Where:**
- `database/migrations/2026_10_20_000001_create_agent_memories_table.php`
- `app/Enums/MemoryType.php`
**Depends on:** —
**Reuses:** Existing migration patterns (`create_workspaces_table.php`), enum patterns (`WorkspaceRole.php`)
**Done when:** Migration creates table with all columns and indexes; enum has 4 cases with label method.
**Tests:** Feature test asserts table exists with correct columns.
**Gate:** `php artisan test --filter=AgentMemory`

## Task 2: AgentMemory Model + Workspace Relationship
**What:** Create `AgentMemory` model with UUID, casts, scopes. Add `agentMemories()` to `Workspace`.
**Where:**
- `app/Models/AgentMemory.php`
- `app/Models/Workspace.php` (add relationship)
**Depends on:** Task 1
**Reuses:** Existing model patterns (`Transaction.php` for HasUuids, casts)
**Done when:** Model has correct fillable, casts, BelongsTo relationship, active scope. Workspace has agentMemories().
**Tests:** Feature test asserts model can be created, workspace relationship works.
**Gate:** `php artisan test --filter=AgentMemory`

## Task 3: AgentMemoryService
**What:** Service class for CRUD operations on memories, scoped per workspace. Includes `formatForPrompt()` method.
**Where:** `app/Services/AgentMemoryService.php`
**Depends on:** Task 2
**Reuses:** Service patterns (`TransactionService.php`), workspace scoping pattern
**Done when:** All CRUD methods work scoped to workspace; formatForPrompt returns indexed memory map string.
**Tests:** Feature test for create, list, find, update, delete, formatForPrompt.
**Gate:** `php artisan test --filter=AgentMemoryService`

## Task 4: BaseAgent Abstract Class
What:** Abstract agent with memory tool injection and memory map in instructions.
**Where:** `app/Ai/Agents/BaseAgent.php`
**Depends on:** Task 3
**Reuses:** `TransactionParser` as reference for Agent/HasTools/Promptable pattern
**Done when:** BaseAgent is abstract, implements Agent+HasTools, has Promptable trait, tools() returns 4 memory tools + agentTools() hook, instructions() includes memoryMap().
**Tests:** Feature test with concrete subclass verifying memory injection and tools present.
**Gate:** `php artisan test --filter=BaseAgent`

## Task 5: Memory Tools (Create, Read, Update, Delete)
**What:** Four Tool implementations for memory manipulation.
**Where:** `app/Ai/Tools/Memory/CreateMemoryTool.php`, `ReadMemoryTool.php`, `UpdateMemoryTool.php`, `DeleteMemoryTool.php`
**Depends on:** Task 3
**Reuses:** Existing tool patterns (`QueryTransactionsTool.php`)
**Done when:** Each tool has correct description, handle(), and schema(). All delegate to AgentMemoryService.
**Tests:** Feature test for each tool's create/read/update/delete behavior.
**Gate:** `php artisan test --filter=MemoryTool`

## Task 6: ScheduledFinancialAnalysisAgent
What:** Concrete agent for daily financial analysis.
**Where:** `app/Ai/Agents/ScheduledFinancialAnalysisAgent.php`
**Depends on:** Task 4, Task 5
**Reuses:** `TransactionParser` for HasStructuredOutput pattern
**Done when:** Extends BaseAgent, implements HasStructuredOutput, has instructions() with memory map, tools include QueryTransactions + QueryRecurrences + 4 memory tools, schema returns insights array.
**Tests:** Feature test with fake response asserting structured output, tools count (6 total).
**Gate:** `php artisan test --filter=ScheduledFinancialAnalysisAgent`

## Task 7: FinancialInsightNotification
What:** Database notification for financial insights.
**Where:** `app/Notifications/Workspace/FinancialInsightNotification.php`
**Depends on:** —
**Reuses:** Existing notification pattern (`NewInvite.php`)
**Done when:** Notification via `database` channel, toDatabase returns workspace_id, insight_count, message.
**Tests:** Feature test asserting via includes database, payload structure.
**Gate:** `php artisan test --filter=FinancialInsightNotification`

## Task 8: RunFinancialAnalysisCommand
What:** Artisan command that runs analysis for all workspaces.
**Where:** `app/Console/Commands/RunFinancialAnalysisCommand.php`
**Depends on:** Task 6, Task 7
**Reuses:** Existing command patterns, Job patterns for reference
**Done when:** Command iterates workspaces, runs agent, creates memories, sends notifications. Handles failures gracefully.
**Tests:** Feature test asserting command processes workspaces, creates memories, sends notifications.
**Gate:** `php artisan test --filter=RunFinancialAnalysisCommand`

## Task 9: Schedule Registration
What:** Register command in routes/console.php at 02:00 daily.
**Where:** `routes/console.php`
**Depends on:** Task 8
**Reuses:** Existing schedule patterns (dailyAt)
**Done when:** `Schedule::command(RunFinancialAnalysisCommand::class)->dailyAt('02:00')` present.
**Tests:** Smoke test asserting command can be called manually.
**Gate:** `php artisan agent:financial-analysis` (with faked agent)

## Task 10: Notifications Table Migration
What:** Ensure `notifications` table exists (Laravel built-in).
**Where:** `database/migrations/2026_10_20_000002_create_notifications_table.php`
**Depends on:** — (can run anytime)
**Reuses:** Laravel's default notification migration
**Done when:** Standard Laravel notifications table migration present.
**Tests:** Migration runs successfully.
**Gate:** `php artisan migrate --database=testing`

## Task 11: Quality Gate & Final Verification
What:** Run all tests, quality checks, fix any issues.
**Where:** —
**Depends on:** All previous tasks
**Reuses:** `composer quality`, `php artisan test`
**Done when:** All tests pass, quality gate passes.
**Tests:** Full test suite.
**Gate:** `composer quality && php artisan test`
