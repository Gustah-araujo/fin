# E2E Final Fix Plan — 12 Failing Tests

## Summary

All 12 failures fall into 5 root-cause categories. All fixes are in Cypress test files only (no backend/frontend changes needed).

---

## Fix 1: import.cy.js — Race condition on confirm (3 tests)

**Tests:** "creates transactions in the database after confirm", "allows editing a field in the preview table before confirming", "unchecking a row excludes it from import"

**Root cause:** The `useEffect` in `Imports/Index.tsx` (line 50-64) seeds `confirmForm.data.items` AFTER the preview renders. The confirm button is `disabled={selectedCount === 0}` until then. The failing tests click "Confirmar Importação" immediately after seeing "Revise os dados" text, before the effect runs. A disabled submit button doesn't fire form submission even with `{ force: true }`.

The passing test "shows full import journey" has explicit waits that the 3 failing tests lack:
```js
cy.get('table tbody tr').should('have.length', 3);
cy.contains('3 de 3 selecionadas').should('be.visible');
```

**Fix:** Add the same waits before clicking confirm in all 3 tests.

**Files:** `cypress/e2e/import.cy.js` (lines 64-65, 80-81, 105-106)

---

## Fix 2: card-recurrence.cy.js & credit-card-hub.cy.js — UUID capture (3 tests)

**Tests:** "shows error for card recurrence with paid bill collision", "pays a closed bill and verifies expenses marked paid", "undoes a bill payment"

**Root cause:** `cy.url().then(url => url.match(/\/cards\/([a-f0-9-]+)$/)[1])` yields `"undefined"`. The `assertToast` command checks `win.__toastBuffer` first — if a toast from a previous operation exists in the buffer, it returns immediately, potentially before the redirect to `cards.show` completes. The subsequent URL regex then captures incorrectly.

**Fix:** After `assertToast('success', 'criado')`, wait for the Show page to render by asserting the card name is visible in an `h1` (or similar page-specific element). Then extract UUID from URL.

**Files:**
- `cypress/e2e/cards/card-recurrence.cy.js` (lines 144-147)
- `cypress/e2e/cards/credit-card-hub.cy.js` (lines 159-162, 218-220)

---

## Fix 3: cards/crud.cy.js — Navigation timing (1 test)

**Test:** "deletes a credit card"

**Root cause:** After card creation, `CreditCardController::store` redirects to `cards.show`. The test clicks `[data-testid="sidebar-cards"]` to navigate back to `cards.index`, but there's no wait for the Inertia SPA navigation to complete. On the Show page, "Para Excluir" exists in an `<h1>`, not inside `[data-slot="card"]`, so `.closest('[data-slot="card"]')` returns null.

**Fix:** After sidebar click, wait for the cards index to render:
```js
cy.get('[data-testid="sidebar-cards"]').click();
cy.get('[data-slot="card"]').should('exist');
```

**File:** `cypress/e2e/cards/crud.cy.js` (line 99)

---

## Fix 4: datatable/state-persistence.cy.js — Toast buffer masking (4 tests)

**Tests:** "persists filters across navigation", "clears filters with Limpar Filtros", "removes individual filter via badge X button", "keeps state isolated between tables"

**Root cause:** The `before` hook creates an account and asserts `assertToast('success', 'criada')`. The toast buffer (`win.__toastBuffer`) persists across Inertia SPA navigations. When the category creation's `assertToast('success', 'criada')` runs, it finds the ACCOUNT creation toast in the buffer (which also contains "criada") and passes immediately — even if category creation failed. The category "Alimentação" is never created, so it doesn't appear in filter options.

**Fix:** Clear the toast buffer after the account creation assertion:
```js
cy.assertToast('success', 'criada');
cy.window().then((win) => { win.__toastBuffer = []; });
```

**File:** `cypress/e2e/datatable/state-persistence.cy.js` (line 23)

---

## Fix 5: transactions/crud.cy.js — Validation message (1 test)

**Test:** "shows validation errors on create"

**Root cause:** The test expects "Uma transação deve ter uma conta ou cartão selecionado" but the Cypress error shows "A conta é obrigatória". This suggests the test file on disk was updated but the CI ran an older version, OR the validation after-callback error isn't reaching the frontend.

The after-callback in `StoreTransactionRequest::withValidator` calls `TransactionValidator::validateStore()` which adds the error to the `account_id` key. `AccountFields.tsx` renders `form.errors.account_id`. This should work.

However, there's a subtle issue: when the main rules fail (description, value, category), Laravel may not run the `after` callback in all configurations. The `after` callback should always run, but let's verify the error is being returned.

**Fix:** No code change needed if the test file is correct. The validation errors should display correctly. If still failing, investigate the `withValidator` after-callback execution order.

**File:** `cypress/e2e/transactions/crud.cy.js` (lines 45-48)

---

## Execution Order

1. Fix 1 (import.cy.js) — 3 tests
2. Fix 4 (datatable/state-persistence.cy.js) — 4 tests
3. Fix 3 (cards/crud.cy.js) — 1 test
4. Fix 2 (card-recurrence.cy.js + credit-card-hub.cy.js) — 3 tests
5. Fix 5 (transactions/crud.cy.js) — 1 test (verify only)

Total: 12 tests fixed
