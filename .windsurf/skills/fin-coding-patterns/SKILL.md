---
name: fin-coding-patterns
description: Coding patterns, conventions, and rules for the Fin project (Laravel 13 + React 19 + InertiaJS 2.x + TypeScript + shadcn/ui). Use when writing, reviewing, or refactoring any code in this project. Triggers on writing PHP, TypeScript, React components, Laravel controllers, services, policies, resources, migrations, or tests.
---

# Fin Coding Patterns

Conventions and rules for the Fin financial assistant project. Follow these for every file you write or edit.

## Stack Overview

- **Backend:** Laravel 13.x, PHP 8.3+, MariaDB, Laravel AI SDK (DeepSeek)
- **Frontend:** React 19, InertiaJS 2.x, TypeScript strict mode, shadcn/ui
- **Auth:** Laravel Socialite (Google OAuth) + native email/password
- **Testing:** PHPUnit (feature tests), Cypress (E2E)
- **UI language:** pt-BR. **Codebase:** English only.

## Project Structure

```
app/
├── Enums/                  # PHP 8.3 native enums
├── Http/
│   ├── Controllers/        # Resource controllers
│   ├── Middleware/
│   ├── Requests/           # FormRequests (one per action)
│   └── Resources/          # ApiResource classes
├── Models/                 # Eloquent models
├── Policies/               # Per-model policies
└── Services/               # Business logic (not in models/controllers)

resources/js/
├── Components/             # PascalCase React components
│   ├── ui/                 # shadcn/ui primitives (do not modify manually)
│   └── [domain]/           # Domain-specific components
├── hooks/                  # Shared hooks (use-workspace.ts, etc.)
├── lib/                    # Utility functions
├── Layouts/                # Page layouts
└── Pages/                  # Inertia pages, organized by domain
    └── [Domain]/           # e.g., Workspace/, Accounts/, Expenses/
```

## Backend Patterns

### API Resource Layer (MANDATORY)

Every response from controller to frontend MUST go through an ApiResource.

```php
// Controller
public function show(Account $account): JsonResponse
{
    return response()->json(new AccountResource($account));
}

public function index(Workspace $workspace): JsonResponse
{
    return response()->json(AccountResource::collection($workspace->accounts));
}
```

**Nested model rule:** If a Resource contains another model as a field, that model MUST also be wrapped in its own Resource.

```php
class TransactionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->uuid,
            'description' => $this->description,
            'value' => (float) $this->value,
            'account' => new AccountResource($this->whenLoaded('account')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
```

### Route Model Binding

Use UUIDs, not auto-increment IDs, in all routes.

```php
// routes/web.php
Route::middleware(['auth', 'verified'])->prefix('w/{workspace}')->group(function () {
    Route::resource('accounts', AccountController::class);
    Route::resource('expenses', ExpenseController::class);
});
```

Models must use UUIDs:

```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Account extends Model
{
    use HasUuids;
}
```

### FormRequests

One FormRequest per action. No inline validation in controllers.

```php
// app/Http/Requests/StoreAccountRequest.php
class StoreAccountRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', new Enum(AccountType::class)],
            'initial_balance' => ['required', 'numeric', 'min:0'],
        ];
    }
}

// Controller
public function store(StoreAccountRequest $request, Workspace $workspace): RedirectResponse
{
    $account = AccountService::create($workspace, $request->validated());
    return redirect()->route('accounts.show', [$workspace, $account]);
}
```

### Controllers

Resource controllers (index, create, store, show, edit, update, destroy). No single-action controllers.

```php
class AccountController extends Controller
{
    public function index(Workspace $workspace): InertiaResponse { }
    public function create(Workspace $workspace): InertiaResponse { }
    public function store(StoreAccountRequest $request, Workspace $workspace): RedirectResponse { }
    public function show(Workspace $workspace, Account $account): InertiaResponse { }
    public function edit(Workspace $workspace, Account $account): InertiaResponse { }
    public function update(UpdateAccountRequest $request, Workspace $workspace, Account $account): RedirectResponse { }
    public function destroy(Workspace $workspace, Account $account): RedirectResponse { }
}
```

### Policies

Always use Policies for authorization. One policy per model.

```php
// app/Policies/AccountPolicy.php
class AccountPolicy
{
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $workspace->hasMember($user);
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return $workspace->hasRole($user, ['admin', 'editor']);
    }
}
```

For non-model classes that need authorization, register the policy manually in `AuthServiceProvider`:

```php
Gate::policy(SomeCustomClass::class, SomeCustomPolicy::class);
```

### Enums

Use PHP 8.3 native enums. Place in `app/Enums/`.

```php
enum AccountType: string
{
    case Checking = 'checking';
    case Savings = 'savings';
    case Investment = 'investment';
}
```

### Services

Business logic goes in dedicated service classes, NOT in models or controllers.

```php
class BillPaymentService
{
    public function pay(CreditCardBill $bill, Account $account): void
    {
        DB::transaction(function () use ($bill, $account) {
            $bill->markAsPaid();
            $account->deduct($bill->total);
            Expense::createFromBillPayment($bill, $account);
        });
    }
}
```

### Shared Inertia Data

Only the minimum goes in `HandleInertiaRequests::share()`. Everything else is fetched on-demand via controller props.

```php
// HandleInertiaRequests.php
public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        'auth' => [
            'user' => $request->user() ? new UserResource($request->user()) : null,
        ],
        'workspace' => $request->workspace ? new WorkspaceResource($request->workspace) : null,
        'workspaces' => $request->user()
            ? WorkspaceResource::collection($request->user()->workspaces)
            : [],
    ]);
}
```

## Frontend Patterns

### TypeScript (MANDATORY)

All frontend code is TypeScript. `tsconfig.json` has `strict: true`. No `.js` files in `resources/js/`.

### Inertia Data Flow

Use Inertia pure: props from controller + `useForm()` for mutations.

```typescript
// Page component receives typed props
interface Props {
  accounts: App.Data.Account[];
}

export default function AccountsIndex({ accounts }: Props) {
  const { delete: destroy } = useForm();

  return (
    <div>
      {accounts.map((account) => (
        <AccountCard key={account.id} account={account} />
      ))}
    </div>
  );
}
```

Mutations use `useForm()`:

```typescript
const form = useForm({
  name: '',
  type: 'checking' as AccountType,
  initial_balance: 0,
});

function handleSubmit(e: React.FormEvent) {
  e.preventDefault();
  form.post(route('accounts.store', { workspace: workspace.id }));
}

// Errors come from Inertia automatically
const nameError = form.errors.name;
```

After mutations that need a refresh, use `router.reload()`:

```typescript
function handleDelete(accountId: string) {
  destroy(route('accounts.destroy', { workspace: workspace.id, account: accountId }), {
    onSuccess: () => router.reload(),
  });
}
```

### Component Naming and Location

- Components: **PascalCase** files (`AccountCard.tsx`)
- Pages: **PascalCase** in domain folders (`Pages/Accounts/Index.tsx`)
- Shared components in `Components/` at the top level
- Domain-specific components in `Components/[domain]/`
- shadcn/ui primitives in `Components/ui/` (never modify manually)

```
Components/
├── ui/                        # shadcn/ui
│   ├── button.tsx
│   ├── card.tsx
│   └── input.tsx
├── Workspace/
│   ├── WorkspaceSwitcher.tsx  # Domain-specific
│   └── MemberList.tsx
├── Accounts/
│   └── AccountCard.tsx
└── SharedCard.tsx             # Shared across domains
```

### Shared Hooks and Utilities

PascalCase for components, kebab-case for hooks and utilities.

```
hooks/
├── use-workspace.ts
├── use-auth.ts
└── use-toast.ts

lib/
├── format-currency.ts
├── api.ts
└── validators.ts
```

### shadcn/ui Usage

Install components via the shadcn CLI (`npx shadcn-ui@latest add button`). Do NOT manually edit `Components/ui/` files. Compose primitives into domain components:

```tsx
// Components/Accounts/AccountForm.tsx
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

export default function AccountForm({ form }: { form: InertiaForm<AccountFormData> }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Nova Conta</CardTitle>
      </CardHeader>
      <CardContent>
        <form onSubmit={(e) => { e.preventDefault(); form.post(route('accounts.store')); }}>
          <div>
            <Label htmlFor="name">Nome</Label>
            <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
            {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
          </div>
          <Button type="submit" disabled={form.processing}>Salvar</Button>
        </form>
      </CardContent>
    </Card>
  );
}
```

### Links and Routing

Always use the `route()` helper from Ziggy for all links and redirects:

```tsx
import { Link } from '@inertiajs/react';

<Link href={route('accounts.index', { workspace: workspace.id })}>
  Contas
</Link>
```

## Testing Patterns

### PHPUnit Feature Tests

Feature tests only (HTTP-level). No unit tests for now. Test one action per test method.

```php
class AccountTest extends TestCase
{
    public function test_user_can_create_account_in_their_workspace(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->addMember($user, WorkspaceRole::Admin);

        $response = $this->actingAs($user)
            ->post(route('accounts.store', $workspace), [
                'name' => 'Nubank',
                'type' => 'checking',
                'initial_balance' => 1000,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('accounts', ['name' => 'Nubank', 'workspace_id' => $workspace->id]);
    }
}
```

## What NOT To Do

- ❌ No inline validation in controllers — use FormRequests
- ❌ No business logic in controllers or models — use Service classes
- ❌ No manual JSON encoding — use ApiResources
- ❌ No passing models directly as props — wrap in Resources
- ❌ No `.js` or `.jsx` files in frontend — TypeScript only
- ❌ No hardcoded route URLs — use `route()` helper
- ❌ No direct DB queries in Blade/templates — use controller + props
- ❌ No modifying `Components/ui/` files manually
- ❌ No auto-increment IDs in URLs or frontend — use UUIDs
