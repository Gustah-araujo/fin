# E2E Test Fix Plan — 12 Failing Tests

## Summary

12 Cypress E2E tests fail across 6 spec files after the CCV2 refactor. Root causes: test-vs-implementation drift (new select columns shifted indices, validation message text changed), Cypress async variable anti-pattern, UUID/Integer mismatch in 2 backend files, and date-dependent import test (fixture has September 2026 dates but current month is October).

---

## Fix 1: Backend — UUID/Integer mismatch in RecurrenceService

**File**: `app/Services/RecurrenceService.php`
**Lines**: 1156, 1243

`'credit_card_id' => $card->id` → `'credit_card_id' => $card->uuid`

The `recurrences.credit_card_id` and `transactions.credit_card_id` columns are UUID (FK to `credit_cards.uuid`). MariaDB enforces FK constraints — inserting an integer causes FK violation → HTTP 500.

---

## Fix 2: Backend — UUID/Integer mismatch in TransactionController filter

**File**: `app/Http/Controllers/TransactionController.php`
**Line**: 96

```php
// Before:
->filter('credit_card_id', Filter::select(fn (Builder $q, string $v) => $q->where('credit_card_id', CreditCard::where('uuid', $v)->value('id'))))

// After:
->filter('credit_card_id', Filter::select(fn (Builder $q, string $v) => $q->where('credit_card_id', CreditCard::where('uuid', $v)->value('uuid'))))
```

The `transactions.credit_card_id` column stores UUID (consistent with `CreditCard` UUID model), so the lookup must compare UUID to UUID.

---

## Fix 3: Backend — Import redirect includes month

**File**: `app/Http/Controllers/ImportController.php`
**Lines**: 102-104

After creating transactions, redirect to `transactions.index` with the month of the first imported item so users see what they imported:

```php
$route = $type === 'income' ? 'incomes.index' : 'transactions.index';

$month = ! empty($items) ? substr($items[0]['date'], 0, 7) : null;

return $month
    ? redirect()->route($route, ['workspace' => $workspace, 'month' => $month])
    : redirect()->route($route, $workspace);
```

---

## Fix 4: Test — Cypress async variable anti-pattern (credit-card-hub)

**File**: `cypress/e2e/cards/credit-card-hub.cy.js`
**Tests**: "pays a closed bill" (line 148), "undoes a bill payment" (line 208)

`cardUuid` is assigned inside `cy.url().then()` but used in `cy.visit()` at the top level — evaluated before the callback runs, so it's `undefined`. Fix: nest `cy.visit` inside the `.then()` callback.

```js
cy.url().should('match', /\/cards\/([a-f0-9-]+)$/);
cy.url().then((url) => {
    const cardUuid = url.match(/\/cards\/([a-f0-9-]+)$/)[1];
    // All cy.visit/cy.request using cardUuid go HERE
});
```

---

## Fix 5: Test — Cypress async variable anti-pattern (card-recurrence)

**File**: `cypress/e2e/cards/card-recurrence.cy.js`
**Test**: "shows error for card recurrence with paid bill collision" (line 133)

Same pattern as Fix 4. Nest all `cy.visit`/`cy.request` calls inside the `cy.url().then()` callback.

---

## Fix 6: Test — cards/crud delete test navigates to wrong page

**File**: `cypress/e2e/cards/crud.cy.js`
**Test**: "deletes a credit card" (line 88)

Controller now redirects to `cards.show` (hub) after store, but the test assumes it lands on `cards.index` grid. Fix: navigate to index page after creation, then delete:

```js
cy.contains('Criar Cartão').click();
cy.assertToast('success', 'criado');

// Navigate back to cards index
cy.get('[data-testid="sidebar-cards"]').click();

cy.contains('Para Excluir')
    .closest('[data-slot="card"]')
    .contains('button', 'Excluir')
    .click({ force: true });
```

---

## Fix 7: Test — datatable select trigger index shifted

**File**: `cypress/e2e/datatable/state-persistence.cy.js`
**Lines**: 58, 85, 104, 125

CCV2 added `Cartão` and `Fatura` select filters before `Categoria`, shifting the category trigger from index 1 to index 3. Select-trigger order is now: `[0]=Conta, [1]=Cartão, [2]=Fatura, [3]=Categoria, [4]=Status`.

Change `.eq(1)` → `.eq(3)` and scope to filter row:

```js
cy.get('table thead tr').eq(1).find('[data-slot="select-trigger"]').eq(3).click();
```

---

## Fix 8: Test — validation message text changed

**File**: `cypress/e2e/transactions/crud.cy.js`
**Line**: 47

`account_id` is now `nullable` (card expenses don't need it). The validation message for "neither account nor card" is now `'Uma transação deve ter uma conta ou cartão selecionado.'`.

Change:
```js
cy.contains('A conta é obrigatória').should('be.visible');
```
To:
```js
cy.contains('Uma transação deve ter uma conta ou cartão selecionado').should('be.visible');
```

---

## Verification

```bash
php artisan test --filter=RecurrenceCardTest
php artisan test --filter=TransactionCardFilterSmokeTest
composer quality
npm run quality
npx cypress run --spec "cypress/e2e/cards/**,cypress/e2e/datatable/**,cypress/e2e/transactions/crud.cy.js,cypress/e2e/import.cy.js"
```
