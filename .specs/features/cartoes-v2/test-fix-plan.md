# Test Fix Plan

## Failing Test

**`TransactionUpdateTest::test_moving_paid_transaction_recalculates_both_accounts`**

Expected: After moving a paid transaction from Account A → Account B, Account A balance reverts to 1000 and Account B drops to 1800.

Actual: Account A stays at 800 (transaction still linked to old account).

## Root Cause

`UpdateTransactionRequest::rules()` is **missing `account_id`**. When the controller calls `$request->validated()`, Laravel strips any input key not declared in the rules array. So the `account_id` field sent by the test is silently discarded — the `TransactionService::update()` receives empty data and never changes the account.

### Evidence

- `UpdateTransactionRequest::rules()` (line 19-28) lists: `description`, `value`, `date`, `category_id`, `tags`, `paid_at`, `scope` — **no `account_id`**
- `StoreTransactionRequest::rules()` (line 26) correctly declares: `'account_id' => ['nullable', 'exists:accounts,uuid']`
- `TransactionService::update()` (line 89-94) expects `$data['account_id']` but never receives it

## Fix

### 1. Add `account_id` to `UpdateTransactionRequest::rules()`

```php
'account_id' => ['sometimes', 'required', 'exists:accounts,uuid'],
```

### 2. Add workspace-belonging validation for `account_id` in `TransactionValidator::validateUpdate()`

Currently `validateUpdate()` only calls `validateCategory` and `validateTags`. Add `validateAccountBelongsToWorkspace` to mirror the store validation:

```php
public static function validateUpdate(Validator $validator, FormRequest $request): void
{
    $workspace = $request->route('workspace');

    self::validateAccountBelongsToWorkspace($validator, $request, $workspace);
    self::validateCategory($validator, $request, $workspace);
    self::validateTags($validator, $request, $workspace);
}
```

Note: The service layer already enforces workspace ownership via `Account::where('uuid', ...)->where('workspace_id', ...)->firstOrFail()`, so this is defense-in-depth.

## Files Changed

| File | Change |
|------|--------|
| `app/Http/Requests/UpdateTransactionRequest.php` | Add `account_id` rule |
| `app/Services/TransactionValidator.php` | Add `validateAccountBelongsToWorkspace` call in `validateUpdate()` |

## Verification

```bash
php artisan test --filter=test_moving_paid_transaction_recalculates_both_accounts
php artisan test tests/Feature/Transactions/
```
