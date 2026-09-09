<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a date is after or equal to a sibling date field.
 *
 * Why a custom Rule instead of Laravel's native `after_or_equal:field`?
 * ------------------------------------------------------------------------
 * Laravel's built-in `after_or_equal` tries to parse the parameter as a
 * date string first (via Carbon::parse), and only falls back to treating
 * it as a field reference when parsing fails. On PHP 8.3 + Carbon 3,
 * `Carbon::parse("date")` throws a `DateMalformedStringException` that
 * bubbles up and breaks the entire validation pipeline. This is a known
 * framework bug (laravel/framework#31432) with no clean workaround other
 * than bypassing the native rule entirely.
 *
 * This custom rule resolves the sibling `date` field directly from the
 * request payload and compares the two timestamps, giving us full control
 * over the behavior and avoiding the framework's parameter ambiguity.
 */
class AfterOrEqualDateRule implements ValidationRule
{
    public function __construct(
        private readonly string $field = 'date',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $request = request();

        if (! $request->filled($this->field)) {
            return;
        }

        $reference = $request->input($this->field);

        if (strtotime($value) < strtotime($reference)) {
            $fail('A data final deve ser maior ou igual à data inicial.');
        }
    }
}
