# Bugfix: React `style` prop as string in Transactions/Incomes listing

## Issue

After creating a recurring expense tied to a credit card, the user is redirected to the
transactions listing, which crashes with:

```
The `style` prop expects a mapping from style properties to values, not a string.
```

## Root Cause (confirmed)

`getTagStyle()` is defined in **two** pages and returns a raw CSS **string**:

- `resources/js/Pages/Transactions/Index.tsx` (lines 107–109, used at line 383)
- `resources/js/Pages/Incomes/Index.tsx` (lines 86–88, used at lines 315–319)

```ts
function getTagStyle(color: string): string {
    return `background-color: ${color}20; color: ${color}; border-color: ${color}40`;
}
// used as: style={getTagStyle(tag.color) as React.CSSProperties}  ← invalid cast
```

The recurrence service syncs tags onto generated transaction instances
(`RecurrenceService` line ~309 / ~772), so after saving a recurring expense with tags on a
credit card, the listing renders the Tags column and React throws at runtime. The
`as React.CSSProperties` cast silences TypeScript but does not fix runtime.

## Preventive audit result

Full grep of `resources/js` shows **only these 2 occurrences** pass a string to `style=`.
All other 27 `style=` usages already use valid object syntax
(`style={{ backgroundColor: ... }}`). No other fixes needed.

## Tasks

### 1. Frontend fix (pawn)
- Convert `getTagStyle()` in both files to return `React.CSSProperties`:
  ```ts
  function getTagStyle(color?: string | null): React.CSSProperties {
      if (!color) return {};
      return {
          backgroundColor: `${color}20`,
          color: color,
          borderColor: `${color}40`,
      };
  }
  ```
- Remove the `as React.CSSProperties` casts at both call sites (now typed correctly).
- Gate: `npm run quality` + `npx tsc --noEmit` (if configured).

### 2. Backend feature test (pawn)
- New test in `tests/Feature/Recurrences/` (or extend `RecurrenceCardTest.php`):
  create user + workspace + credit card + tag + category; POST recurring expense with
  `credit_card_id`, recurrence, and the tag; assert:
  - recurrence + generated transaction instances exist (workspace-scoped)
  - redirect to `transactions.index`
  - `transactions.index` Inertia response: instances carry `tags` with `uuid`, `name`, `color`
- Gate: `php artisan test`

### 3. Cypress E2E test (pawn)
- New spec `cypress/e2e/transactions/recurring-card-expense.cy.js`:
  smoke + full journey: login → new expense → select credit card → recurrence → save →
  redirected to listing → row renders (no crash) → Sonner success toast visible.
  Follow conventions of `cypress/e2e/recurring-expenses.cy.js`.
- Gate: syntax check / follow existing patterns (browser run may be unavailable in CI).

## Verification

- `php artisan test` — all green
- `npm run quality` — clean
- `composer quality` — clean (backend untouched except tests, still run it)
