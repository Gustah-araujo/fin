# Cypress E2E Fix Plan — Root Causes & Corrections

## Root Cause Analysis

### Issue 1 (CRITICAL): `recurrences.credit_card_id` UUID vs Integer Mismatch

The migration defines `recurrences.credit_card_id` as a **UUID column** with FK to `credit_cards.uuid`:
```php
$table->uuid('credit_card_id')->nullable()->after('account_id')->index();
$table->foreign('credit_card_id')->references('uuid')->on('credit_cards')->cascadeOnDelete();
```

But ALL application code stores the **integer `$card->id`**:
- `RecurrenceService.php:196` — `Recurrence::create(['credit_card_id' => $card?->id])`
- `RecurrenceService.php:1156` — bulk insert uses `$card->id`
- `Recurrence.php:77-80` — `belongsTo(CreditCard::class)` defaults to `credit_cards.id` owner key
- `RecurrenceController.php:81` — datatable filter converts UUID→integer: `CreditCard::where('uuid', $v)->value('id')`

On MariaDB, inserting an integer (e.g. `5`) into a `CHAR(36)` UUID column with FK to `credit_cards.uuid` causes a **foreign key constraint violation → HTTP 500**. This breaks all 3 card-recurrence Cypress tests.

PHPUnit passes because SQLite has FK enforcement disabled by default (the test even has a fallback branch acknowledging this).

**Fix**: Store UUIDs everywhere for `recurrences.credit_card_id`.

### Issue 2 (Major): `credit_card_name` Missing from `CreditCardBillResource`

`Transactions/Index.tsx:53,136,237` expects `bill.credit_card_name`, but `CreditCardBillResource.php` never outputs it. Bill filter options render as `undefined — 2026/11`.

### Issue 3 (Major): Bill-Filter Test Clicks "Todos" (Vacuous Pass)

`card-expense-guard.cy.js:223` — `cy.contains('[role="option"]').first().click()` always selects "Todos" (the first option), which clears the filter instead of testing bill filtering.

## Corrections

### File 1: `app/Services/RecurrenceService.php`
- Line 196: `$card?->id` → `$card?->uuid`
- Line 1156: `$card->id` → `$card->uuid`

### File 2: `app/Models/Recurrence.php`
- Line 77-80: `belongsTo(CreditCard::class)` → `belongsTo(CreditCard::class, 'credit_card_id', 'uuid')`

### File 3: `app/Http/Controllers/RecurrenceController.php`
- Line 81: `CreditCard::where('uuid', $v)->value('id')` → `CreditCard::where('uuid', $v)->value('uuid')`

### File 4: `app/Http/Resources/CreditCardBillResource.php`
- Add `'credit_card_name' => $this->creditCard?->name`

### File 5: `tests/Feature/Recurrences/RecurrenceCardTest.php`
- Line 79: `$this->card->id` → `$this->card->uuid`
- Line 89: `$this->card->id` → `$this->card->uuid`

### File 6: `cypress/e2e/cards/card-expense-guard.cy.js`
- Line 223: Replace `cy.contains('[role="option"]').first().click()` with clicking the actual first bill option (skip "Todos" — click the second option or filter by visible text)
