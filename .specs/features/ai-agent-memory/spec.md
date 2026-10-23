# Feature: AI Agent Memory Architecture + Scheduled Financial Analysis Agent

## Problem Statement

The system currently lacks a standardized structure for AI agents that maintain contextual memory scoped to a workspace. Additionally, financial analysis is strictly reactive — users must manually consult reports to identify cash flow fluctuations, due dates, or anomalies.

## Requirements

### R1 — Persistent Memory Model
Create an `agent_memories` table scoped per workspace. Each memory has: UUID, workspace_id (FK), title, content, type (insight/pattern/anomaly/summary), source agent name, optional expires_at, timestamps. Memories are strictly isolated per workspace.

### R2 — AgentMemory Model & Service
`AgentMemory` Eloquent model with UUID primary key, fillable fields, enum cast for type. `AgentMemoryService` provides CRUD operations scoped to a workspace.

### R3 — BaseAgent with Memory Injection
Abstract `BaseAgent` class implementing `Agent` + `HasTools` with `Promptable` trait. Loads all non-expired memories for the workspace, injects them as an indexed memory map into `instructions()`. Provides 4 native memory tools: `CreateMemoryTool`, `ReadMemoryTool`, `UpdateMemoryTool`, `DeleteMemoryTool`.

### R4 — Memory Manipulation Tools
Four `Tool` implementations in `app/Ai/Tools/Memory/`:
- `CreateMemoryTool` — create a new memory in the workspace
- `ReadMemoryTool` — read memories (all or by UUID)
- `UpdateMemoryTool` — update title/content/type of an existing memory
- `DeleteMemoryTool` — delete a memory by UUID

### R5 — ScheduledFinancialAnalysisAgent
Concrete agent extending `BaseAgent` + `HasStructuredOutput`. Uses `QueryTransactionsTool` + `QueryRecurrencesTool` to analyze recent 30-day financial data. Structured output schema: `{ insights: array<{type, title, description, severity, data}> }`. Creates memories for new insights.

### R6 — Notification via Existing Notification Center
`FinancialInsightNotification` notification class via `database` channel, reusing the notification center already merged in PR #35 (no new migration or read-side code). Its `toDatabase()` payload must conform to the existing `NotificationResource` contract — `title`, `message`, `action_url`, `action_text`, `workspace_uuid` — so it renders in the existing `NotificationBell`/`NotificationDrawer` and is scoped by `data->workspace_uuid`. Sent to all workspace members.

### R7 — Artisan Command for Scheduled Execution
`RunFinancialAnalysisCommand` iterates all workspaces, runs `ScheduledFinancialAnalysisAgent` for each, sends notifications to members. Registered in `routes/console.php` to run daily at 02:00.

### R8 — Feature Tests
Full feature test coverage for: migration, model relationships, service CRUD, base agent memory injection, memory tools, scheduled agent structured output, notification delivery, command execution.

### R9 — Smoke Tests
Smoke test for the scheduled command (artisan command runs without error).

## Acceptance Criteria

| ID | Criteria |
|----|----------|
| AC1 | `agent_memories` table created with correct columns, FK to workspaces, and indexes |
| AC2 | `AgentMemoryService::create/read/update/delete` all scoped to workspace |
| AC3 | `BaseAgent::instructions()` contains memory map section when memories exist |
| AC4 | `BaseAgent::tools()` returns 4 memory tools + any subclass tools |
| AC5 | Memory tools correctly create/read/update/delete via agent tool calls |
| AC6 | `ScheduledFinancialAnalysisAgent` returns structured insights from fake response |
| AC7 | `FinancialInsightNotification` uses `database` channel and its payload conforms to the existing notification center contract (`title`/`message`/`workspace_uuid`) |
| AC8 | `RunFinancialAnalysisCommand` processes all workspaces and sends notifications |
| AC9 | Command scheduled at 02:00 daily in `routes/console.php` |
| AC10 | All feature tests pass (`php artisan test`) |
| AC11 | Quality gate passes (`composer quality`) |

## Out of Scope

- Frontend UI for viewing/managing agent memories (future feature)
- Real-time WebSocket notifications (the notification center uses 30s polling — reuse as-is)
- Notification center read-side / UI / polling (already implemented in PR #35)
- Memory versioning or audit trail
- Agent conversation history (handled by Laravel AI SDK natively)

## Dependencies

- Laravel AI SDK ^1.2 (already installed)
- Existing `QueryTransactionsTool`, `QueryRecurrencesTool` (already available)
- `Workspace` model with `members()` relationship (already available)
- `User` model with `Notifiable` (includes `HasDatabaseNotifications` + `RoutesNotifications`) (already available)
- `notifications` table + full notification center read-side (already merged in PR #35 — no new migration needed)
