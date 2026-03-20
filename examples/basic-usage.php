<?php

declare(strict_types=1);

/**
 * Example: Working with Chronos immutable date/time objects.
 */

use Cake\Chronos\Chronos;
use Cake\Chronos\Chronos_Date;
use Cake\Chronos\Clock_Factory;

require_once __DIR__ . '/../vendor/autoload.php';

// --- 1. Creating instances ---
$now   = Chronos::now();
$birth = new Chronos('1990-06-15 08:30:00');

// --- 2. Arithmetic — all return new instances (immutable) ---
$tomorrow    = $now->add_days(1);
$next_month  = $now->add_months(1);
$last_friday = $now->previous(Chronos::FRIDAY);

echo 'Tomorrow: '    . $tomorrow->to_date_time_string()   . PHP_EOL;
echo 'Next month: '  . $next_month->to_date_time_string() . PHP_EOL;
echo 'Last Friday: ' . $last_friday->to_date_string()     . PHP_EOL;

// --- 3. Comparison ---
echo 'Is past: '   . ($birth->is_past() ? 'yes' : 'no')      . PHP_EOL;
echo 'Is future: ' . ($tomorrow->is_future() ? 'yes' : 'no') . PHP_EOL;

// --- 4. Date-only value ---
$today = Chronos_Date::today();
$end   = new Chronos_Date('2026-12-31');
echo 'Days until year end: ' . $today->diff_in_days($end) . PHP_EOL;

// --- 5. Frozen clock for deterministic tests ---
Clock_Factory::freeze_time(new Chronos('2026-01-01 12:00:00'));
$frozen_now = Chronos::now();
echo 'Frozen now: ' . $frozen_now->to_date_time_string() . PHP_EOL;  // 2026-01-01 12:00:00
Clock_Factory::unfreeze_time();

// --- 6. Start/end of period ---
$start_of_week  = $now->start_of_week();
$end_of_month   = $now->end_of_month();
echo 'Week starts: '  . $start_of_week->to_date_string() . PHP_EOL;
echo 'Month ends: '   . $end_of_month->to_date_string()  . PHP_EOL;
