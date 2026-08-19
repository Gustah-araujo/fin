<?php

declare(strict_types=1);

namespace Tests\Feature\Recurrences;

use App\Enums\RecurrenceFrequency;
use App\Enums\RecurrenceStatus;
use Tests\TestCase;

class RecurrenceEnumTest extends TestCase
{
    // RecurrenceFrequency

    public function test_recurrence_frequency_has_weekly_case(): void
    {
        $this->assertEquals('weekly', RecurrenceFrequency::Weekly->value);
    }

    public function test_recurrence_frequency_has_monthly_case(): void
    {
        $this->assertEquals('monthly', RecurrenceFrequency::Monthly->value);
    }

    public function test_recurrence_frequency_weekly_label(): void
    {
        $this->assertEquals('Semanal', RecurrenceFrequency::Weekly->label());
    }

    public function test_recurrence_frequency_monthly_label(): void
    {
        $this->assertEquals('Mensal', RecurrenceFrequency::Monthly->label());
    }

    public function test_recurrence_frequency_weekly_day_label_returns_weekday_name(): void
    {
        $frequency = RecurrenceFrequency::Weekly;

        $this->assertEquals('Dom', $frequency->dayLabel(0));
        $this->assertEquals('Seg', $frequency->dayLabel(1));
        $this->assertEquals('Ter', $frequency->dayLabel(2));
        $this->assertEquals('Qua', $frequency->dayLabel(3));
        $this->assertEquals('Qui', $frequency->dayLabel(4));
        $this->assertEquals('Sex', $frequency->dayLabel(5));
        $this->assertEquals('Sáb', $frequency->dayLabel(6));
    }

    public function test_recurrence_frequency_weekly_day_label_fallback_for_invalid_day(): void
    {
        $frequency = RecurrenceFrequency::Weekly;

        $this->assertEquals('Dia 7', $frequency->dayLabel(7));
        $this->assertEquals('Dia 15', $frequency->dayLabel(15));
    }

    public function test_recurrence_frequency_monthly_day_label(): void
    {
        $frequency = RecurrenceFrequency::Monthly;

        $this->assertEquals('Dia 1', $frequency->dayLabel(1));
        $this->assertEquals('Dia 15', $frequency->dayLabel(15));
        $this->assertEquals('Dia 31', $frequency->dayLabel(31));
    }

    // RecurrenceStatus

    public function test_recurrence_status_has_active_case(): void
    {
        $this->assertEquals('active', RecurrenceStatus::Active->value);
    }

    public function test_recurrence_status_has_paused_case(): void
    {
        $this->assertEquals('paused', RecurrenceStatus::Paused->value);
    }

    public function test_recurrence_status_active_label(): void
    {
        $this->assertEquals('Ativa', RecurrenceStatus::Active->label());
    }

    public function test_recurrence_status_paused_label(): void
    {
        $this->assertEquals('Pausada', RecurrenceStatus::Paused->label());
    }
}
