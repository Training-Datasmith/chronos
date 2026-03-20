# Architecture: chronos

## Purpose

An immutable date/time library for CakePHP. It wraps PHP's `DateTimeImmutable` and `DateInterval` with a fluent API for arithmetic, comparison, formatting, and human-readable differences. All instances are immutable — every modifier returns a new object.

## Directory Structure

```
src/
  Chronos.php                      — Full datetime (date + time); extends DateTimeImmutable
  Chronos_Date.php                 — Date-only value object (no time component)
  Chronos_Time.php                 — Time-only value object (no date component)
  Chronos_Period.php               — Iterator over a date/time range with a step interval
  Chronos_Date_Period.php          — Period specialised for date-only iteration
  Clock_Factory.php                — Testable clock: can return a fixed "now" for deterministic tests
  Difference_Formatter.php         — Formats the difference between two instants as human-readable text
  Difference_Formatter_Interface.php — Contract for custom difference formatters
  Formatting_Trait.php             — Shared formatting helpers used by Chronos and Chronos_Date
  Translator.php                   — I18n message translator for human-readable date strings

tests/
  TestCase/DateTime/               — Comprehensive tests for Chronos (create, arithmetic, compare, etc.)
  TestCase/Date/                   — Tests for Chronos_Date
  Benchmark/                       — Performance benchmarks (PHPBench)
```

## Key Design Decisions

- **Immutability** — all modifier methods (`add_days()`, `start_of_month()`, etc.) call the underlying `DateTimeImmutable::modify()` or `::setDate()` variants, returning new instances.
- **Testable time** — `Clock_Factory` allows freezing "now" in tests via `Clock_Factory::freeze_time()`, eliminating time-sensitive test failures without global mocking.
- **Date-only and Time-only types** — `Chronos_Date` and `Chronos_Time` enforce domain intent (no accidental time-zone confusion for date-only values).
- **Human diff formatting** — `Difference_Formatter` produces strings like "3 hours ago" or "in 2 days"; can be injected with a custom implementation and translator.

## Extension Points

- Implement `Difference_Formatter_Interface` for custom human-readable diff output.
- Use `Clock_Factory::freeze_time()` / `Clock_Factory::set_test_now()` in test setup/teardown for deterministic tests.

## Dependency Flow

```
Chronos / Chronos_Date / Chronos_Time
  └── DateTimeImmutable (PHP built-in)
        └── all arithmetic delegated to PHP's immutable date API

Clock_Factory::now()
  └── returns frozen or real DateTimeImmutable

Difference_Formatter::format(Chronos $from, Chronos $to)
  └── Translator (locale strings)
```
