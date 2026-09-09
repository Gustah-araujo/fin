# Feature: Brevo Mailer Integration

## Reference
- Issue: [Feature] - Adicionar suporte a envio de emails via Brevo
- Brevo API Docs: https://developers.brevo.com/docs/getting-started
- Symfony Brevo Mailer: https://github.com/symfony/brevo-mailer

## Summary

Add support for sending transactional emails via Brevo (formerly Sendinblue) using the official `symfony/brevo-mailer` bridge. The integration must be fully togglable via `.env` variables, requiring zero code changes to switch between mailers.

## Architecture Decision

### Approach: Official Symfony Bridge (`symfony/brevo-mailer`)

**Decision ID:** D-66  
**Date:** 2026-09-09

**Chosen approach:** Use the official `symfony/brevo-mailer` package — a first-party Symfony Mailer bridge maintained by the Symfony core team (fabpot, nicolas-grekas).

**Rationale:**
- Integrates natively with Laravel's `Mail` facade and `Mail::to()->send()` API
- No custom Transport class needed — Symfony handles the Brevo API/SMTP protocol
- Supports both API (`brevo+api://KEY@default`) and SMTP (`brevo+smtp://USERNAME:PASSWORD@default`) transports
- Toggle is purely via `MAIL_MAILER=brevo` in `.env` — zero code changes
- MIT licensed, 3M+ downloads, actively maintained
- Compatible with PHP 8.3+ (requires >= 8.2) and symfony/mailer ^7.2|^8.0 (project has v7.4.14)

**Rejected alternatives:**
1. **Custom Transport implementing `Symfony\Component\Mailer\Transport\TransportInterface`** — unnecessary complexity when an official bridge exists; violates "don't use hacky solutions" requirement
2. **Direct SDK usage (`getbrevo/brevo-php`)** — would require wrapping in a custom transport anyway; bypasses Laravel's mail ecosystem
3. **SMTP-only via existing `smtp` mailer** — works but doesn't leverage Brevo's API features (templates, tracking, webhooks); less efficient

## Requirements

### BRVO-01: Install `symfony/brevo-mailer` package
**Acceptance Criteria:**
- [ ] `symfony/brevo-mailer` appears in `composer.json` require section
- [ ] `composer install` succeeds without conflicts
- [ ] Package version is `^7.4` (compatible with PHP 8.3 and symfony/mailer v7.4)

### BRVO-02: Configure Brevo mailer in `config/mail.php`
**Acceptance Criteria:**
- [ ] New `brevo` entry exists in `config/mail.php` `mailers` array
- [ ] Uses `transport => 'brevo'`
- [ ] Reads API key from `config('services.brevo.api_key')`
- [ ] Reads sender email from `config('services.brevo.sender_email')`
- [ ] Supports both `api` and `smtp` modes via env variable

### BRVO-03: Add Brevo credentials to `config/services.php`
**Acceptance Criteria:**
- [ ] New `brevo` entry exists in `config/services.php`
- [ ] Contains `api_key` reading from `env('BREVO_API_KEY')`
- [ ] Contains `sender_email` reading from `env('BREVO_SENDER_EMAIL')`
- [ ] Contains `sender_name` reading from `env('BREVO_SENDER_NAME')`

### BRVO-04: Add environment variables to `.env.example`
**Acceptance Criteria:**
- [ ] `BREVO_API_KEY=null` added
- [ ] `BREVO_SENDER_EMAIL=null` added
- [ ] `BREVO_SENDER_NAME="${APP_NAME}"` added
- [ ] Comments explain how to activate (set `MAIL_MAILER=brevo`)

### BRVO-05: Feature test — Brevo mailer configuration
**Acceptance Criteria:**
- [ ] Test verifies `brevo` mailer is registered in Laravel's mail manager
- [ ] Test verifies env variables are correctly read
- [ ] Test verifies `MAIL_MAILER=brevo` makes Brevo the default mailer
- [ ] Test uses `Mail::fake()` to assert mail sending works with Brevo transport

### BRVO-06: Feature test — Toggle between mailers
**Acceptance Criteria:**
- [ ] Test verifies switching `MAIL_MAILER` from `log` to `brevo` changes the default transport
- [ ] Test verifies existing `Mail::to()->send()` calls work without modification
- [ ] Test verifies `MAIL_MAILER=log` still works (backward compatibility)

## Implementation Tasks

### Phase 1: Package Installation (TDD — Red)

| Task | Description | Dependencies | Est. |
|------|-------------|--------------|------|
| T1 | Write feature test: `BrevoMailerConfigTest` — verifies `brevo` mailer is configurable and env vars are read correctly | None | 15min |
| T2 | Write feature test: `BrevoMailerToggleTest` — verifies `MAIL_MAILER=brevo` activates Brevo as default | T1 | 10min |

### Phase 2: Configuration (TDD — Green)

| Task | Description | Dependencies | Est. |
|------|-------------|--------------|------|
| T3 | Run tests → expect RED (package not installed, config missing) | T1, T2 | 5min |
| T4 | `composer require symfony/brevo-mailer:^7.4` | T3 | 5min |
| T5 | Add `brevo` mailer to `config/mail.php` | T4 | 5min |
| T6 | Add `brevo` credentials to `config/services.php` | T4 | 5min |
| T7 | Add Brevo env vars to `.env.example` | T4 | 5min |
| T8 | Run tests → expect GREEN | T5, T6, T7 | 5min |

### Phase 3: Quality Gate

| Task | Description | Dependencies | Est. |
|------|-------------|--------------|------|
| T9 | Run `composer quality` (Pint + PHPMD) | T8 | 5min |
| T10 | Run full PHPUnit suite to verify no regressions | T8 | 5min |

## File Changes

### New files:
- `tests/Feature/Mail/BrevoMailerConfigTest.php` — configuration tests
- `tests/Feature/Mail/BrevoMailerToggleTest.php` — toggle tests

### Modified files:
- `composer.json` — add `symfony/brevo-mailer:^7.4`
- `composer.lock` — updated by composer
- `config/mail.php` — add `brevo` mailer entry
- `config/services.php` — add `brevo` credentials
- `.env.example` — add Brevo env vars

### Files NOT modified (by design):
- `app/Mail/` — no Mailable changes needed
- `app/Notifications/` — no Notification changes needed
- `app/Services/AuthService.php` — email sending code unchanged
- `routes/web.php` — no route changes needed

## Configuration Details

### `config/mail.php` — new mailer entry:
```php
'brevo' => [
    'transport' => 'brevo',
    'api_key' => env('BREVO_API_KEY'),
    'sender' => [
        'email' => env('BREVO_SENDER_EMAIL'),
        'name' => env('BREVO_SENDER_NAME'),
    ],
],
```

### `config/services.php` — new service entry:
```php
'brevo' => [
    'api_key' => env('BREVO_API_KEY'),
    'sender_email' => env('BREVO_SENDER_EMAIL'),
    'sender_name' => env('BREVO_SENDER_NAME', env('APP_NAME', 'Fin')),
],
```

### `.env.example` — new variables:
```dotenv
BREVO_API_KEY=null
BREVO_SENDER_EMAIL=null
BREVO_SENDER_NAME="${APP_NAME}"
```

### Activation:
To activate Brevo, user simply sets in `.env`:
```dotenv
MAIL_MAILER=brevo
BREVO_API_KEY=xkeysib-your-api-key-here
BREVO_SENDER_EMAIL=noreply@yourdomain.com
BREVO_SENDER_NAME="Fin"
```

## Verification Strategy

### PHPUnit Feature Tests:
1. `BrevoMailerConfigTest::test_brevo_mailer_is_registered` — asserts `brevo` transport exists in MailManager
2. `BrevoMailerConfigTest::test_brevo_reads_env_variables` — asserts config reads from env correctly
3. `BrevoMailerToggleTest::test_mailer_brevo_becomes_default` — asserts `MAIL_MAILER=brevo` sets Brevo as default
4. `BrevoMailerToggleTest::test_existing_mail_code_works_unchanged` — asserts `Mail::fake()` + `Mail::to()->send()` works with Brevo

### Manual verification (post-merge):
1. Set `MAIL_MAILER=brevo` in `.env` with real Brevo API key
2. Trigger password reset email → verify delivery in Brevo dashboard
3. Trigger email verification → verify delivery in Brevo dashboard
4. Set `MAIL_MAILER=log` → verify emails logged instead

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Brevo API key invalid/expired | Medium | High | Clear error messages in logs; failover mailer config available |
| PHP version incompatibility | Low | High | Verified: v7.4.x requires PHP >= 8.2, project has 8.3+ |
| symfony/mailer version conflict | Low | Medium | Verified: v7.4.x requires ^7.2\|^8.0, project has v7.4.14 |
| Brevo rate limits | Low | Medium | Symfony bridge has built-in retry with exponential backoff |

## Out of Scope

- Custom Mailable classes (existing auth notifications work unchanged)
- Brevo template management (use Brevo dashboard directly)
- Webhook handling for delivery tracking (future feature)
- Brevo contact list sync (future feature)
- Admin UI for mailer configuration (env-only by design)
