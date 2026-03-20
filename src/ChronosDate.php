<?php

declare (strict_types=1);
/**
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @license       https://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Chronos;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use Stringable;
/**
 * An immutable date object.
 *
 * This class is useful when you want to represent a calendar date and ignore times.
 * This means that timezone changes take no effect as a calendar date exists in all timezones
 * in each respective date.
 *
 * @property-read int $year
 * @property-read int $yearIso
 * @property-read int<1, 12> $month
 * @property-read int<1, 31> $day
 * @property-read int<1, 7> $dayOfWeek 1 (for Monday) through 7 (for Sunday)
 * @property-read int<0, 365> $dayOfYear 0 through 365
 * @property-read int<1, 5> $weekOfMonth 1 through 5
 * @property-read int<1, 53> $weekOfYear ISO-8601 week number of year, weeks starting on Monday
 * @property-read int<1, 31> $daysInMonth number of days in the given month
 * @property-read int $age does a diffInYears() with default parameters
 * @property-read int<1, 4> $quarter the quarter of this instance, 1 - 4
 * @property-read int<1, 2> $half the half of the year, with 1 for months Jan...Jun and 2 for Jul...Dec.
 * @immutable
 * @phpstan-consistent-constructor
 */
class Chronos_Date implements Stringable
{
    use Formatting_Trait;
    /**
     * Default format to use for __toString method when type juggling occurs.
     *
     * @var string
     */
    public const DEFAULT_TO_STRING_FORMAT = 'Y-m-d';
    /**
     * Format to use for __toString method when type juggling occurs.
     */
    protected static string $to_string_format = self::DEFAULT_TO_STRING_FORMAT;
    /**
     * Names of days of the week.
     */
    protected static array $days = [Chronos::MONDAY => 'Monday', Chronos::TUESDAY => 'Tuesday', Chronos::WEDNESDAY => 'Wednesday', Chronos::THURSDAY => 'Thursday', Chronos::FRIDAY => 'Friday', Chronos::SATURDAY => 'Saturday', Chronos::SUNDAY => 'Sunday'];
    /**
     * Instance of the diff formatting object.
     */
    protected static ?Difference_Formatter_Interface $diff_formatter = null;
    /**
     * Errors from last time createFromFormat() was called.
     */
    protected static array|false $last_errors = false;
    protected DateTimeImmutable $native;
    /**
     * Create a new Immutable Date instance.
     *
     * Dates do not have time or timezone components exposed. Internally
     * ChronosDate wraps a PHP DateTimeImmutable but limits modifications
     * to only those that operate on day values.
     *
     * By default dates will be calculated from the server's default timezone.
     * You can use the `timezone` parameter to use a different timezone. Timezones
     * are used when parsing relative date expressions like `today` and `yesterday`
     * but do not participate in parsing values like `2022-01-01`.
     *
     * @param \Cake\Chronos\ChronosDate|\DateTimeInterface|string $time Fixed or relative time
     * @param \DateTimeZone|string|null $timezone The time zone used for 'now'
     */
    public function __construct(Chronos_Date|DateTimeInterface|string $time = 'now', DateTimeZone|string|null $timezone = null)
    {
        $this->native = $this->create_native($time, $timezone);
    }
    /**
     * Initializes the PHP DateTimeImmutable object.
     *
     * @param \Cake\Chronos\ChronosDate|\DateTimeInterface|string $time Fixed or relative time
     * @param \DateTimeZone|string|null $timezone The time zone used for 'now'
     */
    protected function create_native(Chronos_Date|DateTimeInterface|string $time, DateTimeZone|string|null $timezone): DateTimeImmutable
    {
        if (!is_string($time)) {
            return new DateTimeImmutable($time->format('Y-m-d 00:00:00'));
        }
        $timezone ??= date_default_timezone_get();
        $timezone = $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone($timezone);
        $test_now = Chronos::get_test_now();
        if ($test_now === null) {
            $time = new DateTimeImmutable($time, $timezone);
            return new DateTimeImmutable($time->format('Y-m-d 00:00:00'));
        }
        $test_now = $test_now->set_timezone($timezone);
        if ($time !== 'now') {
            $test_now = $test_now->modify($time);
        }
        return new DateTimeImmutable($test_now->format('Y-m-d 00:00:00'));
    }
    /**
     * Get today's date.
     *
     * @param \DateTimeZone|string|null $timezone Time zone to use for now.
     */
    public static function now(DateTimeZone|string|null $timezone = null): static
    {
        return new static('now', $timezone);
    }
    /**
     * Get today's date.
     *
     * @param \DateTimeZone|string|null $timezone Time zone to use for today.
     */
    public static function today(DateTimeZone|string|null $timezone = null): static
    {
        return static::now($timezone);
    }
    /**
     * Get tomorrow's date.
     *
     * @param \DateTimeZone|string|null $timezone Time zone to use for tomorrow.
     */
    public static function tomorrow(DateTimeZone|string|null $timezone = null): static
    {
        return new static('tomorrow', $timezone);
    }
    /**
     * Get yesterday's date.
     *
     * @param \DateTimeZone|string|null $timezone Time zone to use for yesterday.
     */
    public static function yesterday(DateTimeZone|string|null $timezone = null): static
    {
        return new static('yesterday', $timezone);
    }
    /**
     * Create an instance from a string.  This is an alias for the
     * constructor that allows better fluent syntax as it allows you to do
     * Chronos::parse('Monday next week')->fn() rather than
     * (new Chronos('Monday next week'))->fn()
     *
     * @param \Cake\Chronos\ChronosDate|\DateTimeInterface|string $time The strtotime compatible string to parse
     */
    public static function parse(Chronos_Date|DateTimeInterface|string $time): static
    {
        return new static($time);
    }
    /**
     * Create an instance from a specific date.
     *
     * @param int $year The year to create an instance with.
     * @param int $month The month to create an instance with.
     * @param int $day The day to create an instance with.
     */
    public static function create(int $year, int $month, int $day): static
    {
        $instance = static::create_from_format('Y-m-d', sprintf('%s-%s-%s', 0, $month, $day));
        return $instance->add_years($year);
    }
    /**
     * Create an instance from a specific format
     *
     * @param string $format The date() compatible format string.
     * @param string $time The formatted date string to interpret.
     * @throws \InvalidArgumentException
     */
    public static function create_from_format(string $format, string $time): static
    {
        $date_time = DateTimeImmutable::create_from_format($format, $time);
        static::$last_errors = DateTimeImmutable::get_last_errors();
        if (!$date_time) {
            $message = static::$last_errors ? implode(PHP_EOL, static::$last_errors['errors']) : 'Unknown error';
            throw new InvalidArgumentException($message);
        }
        return new static($date_time);
    }
    /**
     * Returns parse warnings and errors from the last ``createFromFormat()``
     * call.
     *
     * Returns the same data as DateTimeImmutable::getLastErrors().
     *
     * @return array|false
     */
    public static function get_last_errors(): array|false
    {
        return static::$last_errors;
    }
    /**
     * Creates an instance from an array of date values.
     *
     * Allowed values:
     *  - year
     *  - month
     *  - day
     *
     * @param array<int|string> $values Array of date and time values.
     */
    public static function create_from_array(array $values): static
    {
        $formatted = sprintf('%04d-%02d-%02d ', $values['year'], $values['month'], $values['day']);
        return static::parse($formatted);
    }
    /**
     * Get the difference formatter instance or overwrite the current one.
     *
     * @param \Cake\Chronos\DifferenceFormatterInterface|null $formatter The formatter instance when setting.
     * @return \Cake\Chronos\DifferenceFormatterInterface The formatter instance.
     */
    public static function diff_formatter(?Difference_Formatter_Interface $formatter = null): Difference_Formatter_Interface
    {
        if ($formatter === null) {
            if (static::$diff_formatter === null) {
                static::$diff_formatter = new Difference_Formatter();
            }
            return static::$diff_formatter;
        }
        return static::$diff_formatter = $formatter;
    }
    /**
     * Add an Interval to a Date
     *
     * Any changes to the time will be ignored and reset to 00:00:00
     *
     * @param \DateInterval $interval The interval to modify this date by.
     * @return static A modified Date instance
     */
    public function add(DateInterval $interval): static
    {
        if ($interval->f > 0 || $interval->s > 0 || $interval->i > 0 || $interval->h > 0) {
            throw new InvalidArgumentException('Cannot add intervals with time components');
        }
        $new = clone $this;
        $new->native = $new->native->add($interval)->set_time(0, 0, 0);
        return $new;
    }
    /**
     * Subtract an Interval from a Date.
     *
     * Any changes to the time will be ignored and reset to 00:00:00
     *
     * @param \DateInterval $interval The interval to modify this date by.
     * @return static A modified Date instance
     */
    public function sub(DateInterval $interval): static
    {
        if ($interval->f > 0 || $interval->s > 0 || $interval->i > 0 || $interval->h > 0) {
            throw new InvalidArgumentException('Cannot subtract intervals with time components');
        }
        $new = clone $this;
        $new->native = $new->native->sub($interval)->set_time(0, 0, 0);
        return $new;
    }
    /**
     * Creates a new instance with date modified according to DateTimeImmutable::modifier().
     *
     * Attempting to change a time component will raise an exception
     *
     * @param string $modifier Date modifier
     */
    public function modify(string $modifier): static
    {
        if (preg_match('/hour|minute|second/', $modifier)) {
            throw new InvalidArgumentException('Cannot modify date objects by time values');
        }
        $new = clone $this;
        $native = $new->native->modify($modifier);
        if ($native === false) {
            throw new InvalidArgumentException(sprintf('Unable to modify date using `%s`', $modifier));
        }
        $new->native = $native;
        if ($new->format('H:i:s') !== '00:00:00') {
            $new->native = $new->native->set_time(0, 0, 0);
        }
        return $new;
    }
    /**
     * Sets the date.
     *
     * @param int $year The year to set.
     * @param int $month The month to set.
     * @param int $day The day to set.
     */
    public function set_date(int $year, int $month, int $day): static
    {
        $new = clone $this;
        $new->native = $new->native->set_date($year, $month, $day);
        return $new;
    }
    /**
     * Sets the date according to the ISO 8601 standard
     *
     * @param int $year Year of the date.
     * @param int $week Week of the date.
     * @param int $dayOfWeek Offset from the first day of the week.
     */
    public function set_iso_date(int $year, int $week, int $day_of_week = 1): static
    {
        $new = clone $this;
        $new->native = $new->native->set_iso_date($year, $week, $day_of_week);
        return $new;
    }
    /**
     * Returns the difference between this instance and target.
     *
     * @param \Cake\Chronos\ChronosDate $target Target instance
     * @param bool $absolute Whether the interval is forced to be positive
     */
    public function diff(Chronos_Date $target, bool $absolute = false): DateInterval
    {
        return $this->native->diff($target->native, $absolute);
    }
    /**
     * Returns formatted date string according to DateTimeImmutable::format().
     *
     * @param string $format String format
     */
    public function format(string $format): string
    {
        return $this->native->format($format);
    }
    /**
     * Set the instance's year
     *
     * @param int $value The year value.
     */
    public function year(int $value): static
    {
        return $this->set_date($value, $this->month, $this->day);
    }
    /**
     * Set the instance's month
     *
     * @param int $value The month value.
     */
    public function month(int $value): static
    {
        return $this->set_date($this->year, $value, $this->day);
    }
    /**
     * Set the instance's day
     *
     * @param int $value The day value.
     */
    public function day(int $value): static
    {
        return $this->set_date($this->year, $this->month, $value);
    }
    /**
     * Add years to the instance. Positive $value travel forward while
     * negative $value travel into the past.
     *
     * If the new ChronosDate does not exist, the last day of the month is used
     * instead instead of overflowing into the next month.
     *
     * ### Example:
     *
     * ```
     *  (new Chronos('2015-01-03'))->addYears(1); // Results in 2016-01-03
     *
     *  (new Chronos('2012-02-29'))->addYears(1); // Results in 2013-02-28
     * ```
     *
     * @param int $value The number of years to add.
     */
    public function add_years(int $value): static
    {
        $month = $this->month;
        $date = $this->modify($value . ' years');
        if ($date->month !== $month) {
            return $date->modify('last day of previous month');
        }
        return $date;
    }
    /**
     * Remove years from the instance.
     *
     * Has the same behavior as `addYears()`.
     *
     * @param int $value The number of years to remove.
     */
    public function sub_years(int $value): static
    {
        return $this->add_years(-$value);
    }
    /**
     * Add years with overflowing to the instance. Positive $value
     * travels forward while negative $value travels into the past.
     *
     * If the new ChronosDate does not exist, the days overflow into the next month.
     *
     * ### Example:
     *
     * ```
     *  (new Chronos('2012-02-29'))->addYearsWithOverflow(1); // Results in 2013-03-01
     * ```
     *
     * @param int $value The number of years to add.
     */
    public function add_years_with_overflow(int $value): static
    {
        return $this->modify($value . ' year');
    }
    /**
     * Remove years with overflow from the instance
     *
     * Has the same behavior as `addYeasrWithOverflow()`.
     *
     * @param int $value The number of years to remove.
     */
    public function sub_years_with_overflow(int $value): static
    {
        return $this->add_years_with_overflow(-1 * $value);
    }
    /**
     * Add months to the instance. Positive $value travels forward while
     * negative $value travels into the past.
     *
     * When adding or subtracting months, if the resulting time is a date
     * that does not exist, the result of this operation will always be the
     * last day of the intended month.
     *
     * ### Example:
     *
     * ```
     *  (new Chronos('2015-01-03'))->addMonths(1); // Results in 2015-02-03
     *
     *  (new Chronos('2015-01-31'))->addMonths(1); // Results in 2015-02-28
     * ```
     *
     * @param int $value The number of months to add.
     */
    public function add_months(int $value): static
    {
        $day = $this->day;
        $date = $this->modify($value . ' months');
        if ($date->day !== $day) {
            return $date->modify('last day of previous month');
        }
        return $date;
    }
    /**
     * Remove months from the instance
     *
     * Has the same behavior as `addMonths()`.
     *
     * @param int $value The number of months to remove.
     */
    public function sub_months(int $value): static
    {
        return $this->add_months(-$value);
    }
    /**
     * Add months with overflowing to the instance. Positive $value
     * travels forward while negative $value travels into the past.
     *
     * If the new ChronosDate does not exist, the days overflow into the next month.
     *
     * ### Example:
     *
     * ```
     *  (new Chronos('2012-01-30'))->addMonthsWithOverflow(1); // Results in 2013-03-01
     * ```
     *
     * @param int $value The number of months to add.
     */
    public function add_months_with_overflow(int $value): static
    {
        return $this->modify($value . ' months');
    }
    /**
     * Add months with overflowing to the instance. Positive $value
     * travels forward while negative $value travels into the past.
     *
     * If the new ChronosDate does not exist, the days overflow into the next month.
     *
     * ### Example:
     *
     * ```
     *  (new Chronos('2012-01-30'))->addMonthsWithOverflow(1); // Results in 2013-03-01
     * ```
     *
     * @param int $value The number of months to remove.
     */
    public function sub_months_with_overflow(int $value): static
    {
        return $this->add_months_with_overflow(-1 * $value);
    }
    /**
     * Add days to the instance. Positive $value travels forward while
     * negative $value travels into the past.
     *
     * @param int $value The number of days to add.
     */
    public function add_days(int $value): static
    {
        return $this->modify("{$value} days");
    }
    /**
     * Remove days from the instance
     *
     * @param int $value The number of days to remove.
     */
    public function sub_days(int $value): static
    {
        return $this->add_days(-$value);
    }
    /**
     * Add weekdays to the instance. Positive $value travels forward while
     * negative $value travels into the past.
     *
     * @param int $value The number of weekdays to add.
     */
    public function add_weekdays(int $value): static
    {
        return $this->modify($value . ' weekdays, ' . $this->format('H:i:s'));
    }
    /**
     * Remove weekdays from the instance
     *
     * @param int $value The number of weekdays to remove.
     */
    public function sub_weekdays(int $value): static
    {
        return $this->add_weekdays(-$value);
    }
    /**
     * Add weeks to the instance. Positive $value travels forward while
     * negative $value travels into the past.
     *
     * @param int $value The number of weeks to add.
     */
    public function add_weeks(int $value): static
    {
        return $this->modify("{$value} week");
    }
    /**
     * Remove weeks to the instance
     *
     * @param int $value The number of weeks to remove.
     */
    public function sub_weeks(int $value): static
    {
        return $this->add_weeks(-$value);
    }
    /**
     * Resets the date to the first day of the month
     */
    public function start_of_month(): static
    {
        return $this->modify('first day of this month');
    }
    /**
     * Resets the date to end of the month
     */
    public function end_of_month(): static
    {
        return $this->modify('last day of this month');
    }
    /**
     * Resets the date to the first day of the year
     */
    public function start_of_year(): static
    {
        return $this->modify('first day of january');
    }
    /**
     * Resets the date to end of the year
     */
    public function end_of_year(): static
    {
        return $this->modify('last day of december');
    }
    /**
     * Resets the date to the first day of the decade
     */
    public function start_of_decade(): static
    {
        $year = $this->year - $this->year % Chronos::YEARS_PER_DECADE;
        return $this->modify("first day of january {$year}");
    }
    /**
     * Resets the date to end of the decade
     */
    public function end_of_decade(): static
    {
        $year = $this->year - $this->year % Chronos::YEARS_PER_DECADE + Chronos::YEARS_PER_DECADE - 1;
        return $this->modify("last day of december {$year}");
    }
    /**
     * Resets the date to the first day of the century
     */
    public function start_of_century(): static
    {
        $year = $this->start_of_year()->year($this->year - 1 - ($this->year - 1) % Chronos::YEARS_PER_CENTURY + 1)->year;
        return $this->modify("first day of january {$year}");
    }
    /**
     * Resets the date to end of the century and time to 23:59:59
     */
    public function end_of_century(): static
    {
        $y = $this->year - 1 - ($this->year - 1) % Chronos::YEARS_PER_CENTURY + Chronos::YEARS_PER_CENTURY;
        $year = $this->end_of_year()->year($y)->year;
        return $this->modify("last day of december {$year}");
    }
    /**
     * Resets the date to the first day of week (defined in $weekStartsAt)
     */
    public function start_of_week(): static
    {
        $date_time = $this;
        if ($date_time->day_of_week !== Chronos::get_week_starts_at()) {
            return $date_time->previous(Chronos::get_week_starts_at());
        }
        return $date_time;
    }
    /**
     * Resets the date to end of week (defined in $weekEndsAt) and time to 23:59:59
     */
    public function end_of_week(): static
    {
        $date_time = $this;
        if ($date_time->day_of_week !== Chronos::get_week_ends_at()) {
            return $date_time->next(Chronos::get_week_ends_at());
        }
        return $date_time;
    }
    /**
     * Modify to the next occurrence of a given day of the week.
     * If no dayOfWeek is provided, modify to the next occurrence
     * of the current day of the week.  Use the supplied consts
     * to indicate the desired dayOfWeek, ex. Chronos::MONDAY.
     *
     * @param int|null $dayOfWeek The day of the week to move to.
     */
    public function next(?int $day_of_week = null): static
    {
        if ($day_of_week === null) {
            $day_of_week = $this->day_of_week;
        }
        $day = static::$days[$day_of_week];
        return $this->modify("next {$day}");
    }
    /**
     * Modify to the previous occurrence of a given day of the week.
     * If no dayOfWeek is provided, modify to the previous occurrence
     * of the current day of the week.  Use the supplied consts
     * to indicate the desired dayOfWeek, ex. Chronos::MONDAY.
     *
     * @param int|null $dayOfWeek The day of the week to move to.
     */
    public function previous(?int $day_of_week = null): static
    {
        if ($day_of_week === null) {
            $day_of_week = $this->day_of_week;
        }
        $day = static::$days[$day_of_week];
        return $this->modify("last {$day}");
    }
    /**
     * Modify to the first occurrence of a given day of the week
     * in the current month. If no dayOfWeek is provided, modify to the
     * first day of the current month.  Use the supplied consts
     * to indicate the desired dayOfWeek, ex. Chronos::MONDAY.
     *
     * @param int|null $dayOfWeek The day of the week to move to.
     */
    public function first_of_month(?int $day_of_week = null): static
    {
        $day = $day_of_week === null ? 'day' : static::$days[$day_of_week];
        return $this->modify("first {$day} of this month");
    }
    /**
     * Modify to the last occurrence of a given day of the week
     * in the current month. If no dayOfWeek is provided, modify to the
     * last day of the current month.  Use the supplied consts
     * to indicate the desired dayOfWeek, ex. Chronos::MONDAY.
     *
     * @param int|null $dayOfWeek The day of the week to move to.
     */
    public function last_of_month(?int $day_of_week = null): static
    {
        $day = $day_of_week === null ? 'day' : static::$days[$day_of_week];
        return $this->modify("last {$day} of this month");
    }
    /**
     * Modify to the given occurrence of a given day of the week
     * in the current month. If the calculated occurrence is outside the scope
     * of the current month, then return false and no modifications are made.
     * Use the supplied consts to indicate the desired dayOfWeek, ex. Chronos::MONDAY.
     *
     * @param int $nth The offset to use.
     * @param int $dayOfWeek The day of the week to move to.
     * @return static|false
     */
    public function nth_of_month(int $nth, int $day_of_week): static|false
    {
        $date_time = $this->first_of_month();
        $check = $date_time->format('Y-m');
        $date_time = $date_time->modify("+{$nth} " . static::$days[$day_of_week]);
        return $date_time->format('Y-m') === $check ? $date_time : false;
    }
    /**
     * Modify to the first occurrence of a given day of the week
     * in the current quarter. If no dayOfWeek is provided, modify to the
     * first day of the current quarter.  Use the supplied consts
     * to indicate the desired dayOfWeek, ex. Chronos::MONDAY.
     *
     * @param int|null $dayOfWeek The day of the week to move to.
     */
    public function first_of_quarter(?int $day_of_week = null): static
    {
        return $this->day(1)->month($this->quarter * Chronos::MONTHS_PER_QUARTER - 2)->first_of_month($day_of_week);
    }
    /**
     * Modify to the last occurrence of a given day of the week
     * in the current quarter. If no dayOfWeek is provided, modify to the
     * last day of the current quarter.  Use the supplied consts
     * to indicate the desired dayOfWeek, ex. Chronos::MONDAY.
     *
     * @param int|null $dayOfWeek The day of the week to move to.
     */
    public function last_of_quarter(?int $day_of_week = null): static
    {
        return $this->day(1)->month($this->quarter * Chronos::MONTHS_PER_QUARTER)->last_of_month($day_of_week);
    }
    /**
     * Modify to the given occurrence of a given day of the week
     * in the current quarter. If the calculated occurrence is outside the scope
     * of the current quarter, then return false and no modifications are made.
     * Use the supplied consts to indicate the desired dayOfWeek, ex. Chronos::MONDAY.
     *
     * @param int $nth The offset to use.
     * @param int $dayOfWeek The day of the week to move to.
     * @return static|false
     */
    public function nth_of_quarter(int $nth, int $day_of_week): static|false
    {
        $date_time = $this->day(1)->month($this->quarter * Chronos::MONTHS_PER_QUARTER);
        $last_month = $date_time->month;
        $year = $date_time->year;
        $date_time = $date_time->first_of_quarter()->modify("+{$nth}" . static::$days[$day_of_week]);
        return $last_month < $date_time->month || $year !== $date_time->year ? false : $date_time;
    }
    /**
     * Modify to the first occurrence of a given day of the week
     * in the current year. If no dayOfWeek is provided, modify to the
     * first day of the current year.  Use the supplied consts
     * to indicate the desired dayOfWeek, ex. Chronos::MONDAY.
     *
     * @param int|null $dayOfWeek The day of the week to move to.
     */
    public function first_of_year(?int $day_of_week = null): static
    {
        $day = $day_of_week === null ? 'day' : static::$days[$day_of_week];
        return $this->modify("first {$day} of january");
    }
    /**
     * Modify to the last occurrence of a given day of the week
     * in the current year. If no dayOfWeek is provided, modify to the
     * last day of the current year.  Use the supplied consts
     * to indicate the desired dayOfWeek, ex. Chronos::MONDAY.
     *
     * @param int|null $dayOfWeek The day of the week to move to.
     */
    public function last_of_year(?int $day_of_week = null): static
    {
        $day = $day_of_week === null ? 'day' : static::$days[$day_of_week];
        return $this->modify("last {$day} of december");
    }
    /**
     * Modify to the given occurrence of a given day of the week
     * in the current year. If the calculated occurrence is outside the scope
     * of the current year, then return false and no modifications are made.
     * Use the supplied consts to indicate the desired dayOfWeek, ex. Chronos::MONDAY.
     *
     * @param int $nth The offset to use.
     * @param int $dayOfWeek The day of the week to move to.
     * @return static|false
     */
    public function nth_of_year(int $nth, int $day_of_week): static|false
    {
        $date_time = $this->first_of_year()->modify("+{$nth} " . static::$days[$day_of_week]);
        return $this->year === $date_time->year ? $date_time : false;
    }
    /**
     * Determines if the instance is equal to another
     *
     * @param \Cake\Chronos\ChronosDate $other The instance to compare with.
     */
    public function equals(Chronos_Date $other): bool
    {
        return $this->native == $other->native;
    }
    /**
     * Determines if the instance is not equal to another
     *
     * @param \Cake\Chronos\ChronosDate $other The instance to compare with.
     */
    public function not_equals(Chronos_Date $other): bool
    {
        return !$this->equals($other);
    }
    /**
     * Determines if the instance is greater (after) than another
     *
     * @param \Cake\Chronos\ChronosDate $other The instance to compare with.
     */
    public function greater_than(Chronos_Date $other): bool
    {
        return $this->native > $other->native;
    }
    /**
     * Determines if the instance is greater (after) than or equal to another
     *
     * @param \Cake\Chronos\ChronosDate $other The instance to compare with.
     */
    public function greater_than_or_equals(Chronos_Date $other): bool
    {
        return $this->native >= $other->native;
    }
    /**
     * Determines if the instance is less (before) than another
     *
     * @param \Cake\Chronos\ChronosDate $other The instance to compare with.
     */
    public function less_than(Chronos_Date $other): bool
    {
        return $this->native < $other->native;
    }
    /**
     * Determines if the instance is less (before) or equal to another
     *
     * @param \Cake\Chronos\ChronosDate $other The instance to compare with.
     */
    public function less_than_or_equals(Chronos_Date $other): bool
    {
        return $this->native <= $other->native;
    }
    /**
     * Determines if the instance is between two others
     *
     * @param \Cake\Chronos\ChronosDate $start Start of target range
     * @param \Cake\Chronos\ChronosDate $end End of target range
     * @param bool $equals Whether to include the beginning and end of range
     */
    public function between(Chronos_Date $start, Chronos_Date $end, bool $equals = true): bool
    {
        if ($start->greater_than($end)) {
            [$start, $end] = [$end, $start];
        }
        if ($equals) {
            return $this->greater_than_or_equals($start) && $this->less_than_or_equals($end);
        }
        return $this->greater_than($start) && $this->less_than($end);
    }
    /**
     * Get the closest date from the instance.
     *
     * @param \Cake\Chronos\ChronosDate $first The instance to compare with.
     * @param \Cake\Chronos\ChronosDate $second The instance to compare with.
     * @param \Cake\Chronos\ChronosDate ...$others Others instance to compare with.
     */
    public function closest(Chronos_Date $first, Chronos_Date $second, Chronos_Date ...$others): Chronos_Date
    {
        $closest = $first;
        $closest_diff_in_days = $this->diff_in_days($first);
        foreach ([$second, ...$others] as $other) {
            $other_diff_in_days = $this->diff_in_days($other);
            if ($other_diff_in_days < $closest_diff_in_days) {
                $closest = $other;
                $closest_diff_in_days = $other_diff_in_days;
            }
        }
        return $closest;
    }
    /**
     * Get the farthest date from the instance.
     *
     * @param \Cake\Chronos\ChronosDate $first The instance to compare with.
     * @param \Cake\Chronos\ChronosDate $second The instance to compare with.
     * @param \Cake\Chronos\ChronosDate ...$others Others instance to compare with.
     */
    public function farthest(Chronos_Date $first, Chronos_Date $second, Chronos_Date ...$others): Chronos_Date
    {
        $farthest = $first;
        $farthest_diff_in_days = $this->diff_in_days($first);
        foreach ([$second, ...$others] as $other) {
            $other_diff_in_days = $this->diff_in_days($other);
            if ($other_diff_in_days > $farthest_diff_in_days) {
                $farthest = $other;
                $farthest_diff_in_days = $other_diff_in_days;
            }
        }
        return $farthest;
    }
    /**
     * Determines if the instance is a weekday
     */
    public function is_weekday(): bool
    {
        return !$this->is_weekend();
    }
    /**
     * Determines if the instance is a weekend day
     */
    public function is_weekend(): bool
    {
        return in_array($this->day_of_week, Chronos::get_weekend_days(), true);
    }
    /**
     * Determines if the instance is yesterday
     *
     * @param \DateTimeZone|string|null $timezone Time zone to use for now.
     */
    public function is_yesterday(DateTimeZone|string|null $timezone = null): bool
    {
        return $this->equals(static::yesterday($timezone));
    }
    /**
     * Determines if the instance is today
     *
     * @param \DateTimeZone|string|null $timezone Time zone to use for now.
     */
    public function is_today(DateTimeZone|string|null $timezone = null): bool
    {
        return $this->equals(static::now($timezone));
    }
    /**
     * Determines if the instance is tomorrow
     *
     * @param \DateTimeZone|string|null $timezone Time zone to use for now.
     */
    public function is_tomorrow(DateTimeZone|string|null $timezone = null): bool
    {
        return $this->equals(static::tomorrow($timezone));
    }
    /**
     * Determines if the instance is within the next week
     *
     * @param \DateTimeZone|string|null $timezone Time zone to use for now.
     */
    public function is_next_week(DateTimeZone|string|null $timezone = null): bool
    {
        return $this->format('W o') === static::now($timezone)->add_weeks(1)->format('W o');
    }
    /**
     * Determines if the instance is within the last week
     *
     * @param \DateTimeZone|string|null $timezone Time zone to use for now.
     */
    public function is_last_week(DateTimeZone|string|null $timezone = null): bool
    {
        return $this->format('W o') === static::now($timezone)->sub_weeks(1)->format('W o');
    }
    /**
     * Determines if the instance is within the next month
     *
     * @param \DateTimeZone|string|null $timezone Time zone to use for now.
     */
    public function is_next_month(DateTimeZone|string|null $timezone = null): bool
    {
        return $this->format('m Y') === static::now($timezone)->add_months(1)->format('m Y');
    }
    /**
     * Determines if the instance is within the last month
     *
     * @param \DateTimeZone|string|null $timezone Time zone to use for now.
     */
    public function is_last_month(DateTimeZone|string|null $timezone = null): bool
    {
        return $this->format('m Y') === static::now($timezone)->sub_months(1)->format('m Y');
    }
    /**
     * Determines if the instance is within the next year
     *
     * @param \DateTimeZone|string|null $timezone Time zone to use for now.
     */
    public function is_next_year(DateTimeZone|string|null $timezone = null): bool
    {
        return $this->year === static::now($timezone)->add_years(1)->year;
    }
    /**
     * Determines if the instance is within the last year
     *
     * @param \DateTimeZone|string|null $timezone Time zone to use for now.
     */
    public function is_last_year(DateTimeZone|string|null $timezone = null): bool
    {
        return $this->year === static::now($timezone)->sub_years(1)->year;
    }
    /**
     * Determines if the instance is within the first half of year
     */
    public function is_first_half(): bool
    {
        return $this->half === 1;
    }
    /**
     * Determines if the instance is within the second half of year
     */
    public function is_second_half(): bool
    {
        return $this->half === 2;
    }
    /**
     * Determines if the instance is in the future, ie. greater (after) than now
     *
     * @param \DateTimeZone|string|null $timezone Time zone to use for now.
     */
    public function is_future(DateTimeZone|string|null $timezone = null): bool
    {
        return $this->greater_than(static::now($timezone));
    }
    /**
     * Determines if the instance is in the past, ie. less (before) than now
     *
     * @param \DateTimeZone|string|null $timezone Time zone to use for now.
     */
    public function is_past(DateTimeZone|string|null $timezone = null): bool
    {
        return $this->less_than(static::now($timezone));
    }
    /**
     * Determines if the instance is a leap year
     */
    public function is_leap_year(): bool
    {
        return $this->format('L') === '1';
    }
    /**
     * Checks if this day is a Sunday.
     */
    public function is_sunday(): bool
    {
        return $this->day_of_week === Chronos::SUNDAY;
    }
    /**
     * Checks if this day is a Monday.
     */
    public function is_monday(): bool
    {
        return $this->day_of_week === Chronos::MONDAY;
    }
    /**
     * Checks if this day is a Tuesday.
     */
    public function is_tuesday(): bool
    {
        return $this->day_of_week === Chronos::TUESDAY;
    }
    /**
     * Checks if this day is a Wednesday.
     */
    public function is_wednesday(): bool
    {
        return $this->day_of_week === Chronos::WEDNESDAY;
    }
    /**
     * Checks if this day is a Thursday.
     */
    public function is_thursday(): bool
    {
        return $this->day_of_week === Chronos::THURSDAY;
    }
    /**
     * Checks if this day is a Friday.
     */
    public function is_friday(): bool
    {
        return $this->day_of_week === Chronos::FRIDAY;
    }
    /**
     * Checks if this day is a Saturday.
     */
    public function is_saturday(): bool
    {
        return $this->day_of_week === Chronos::SATURDAY;
    }
    /**
     * Returns true this instance happened within the specified interval
     *
     * @param string|int $timeInterval the numeric value with space then time type.
     *    Example of valid types: 6 hours, 2 days, 1 minute.
     */
    public function was_within_last(string|int $time_interval): bool
    {
        $now = new static(new Chronos());
        $interval = $now->modify('-' . $time_interval);
        $this_time = $this->format('U');
        return $this_time >= $interval->format('U') && $this_time <= $now->format('U');
    }
    /**
     * Returns true this instance will happen within the specified interval
     *
     * @param string|int $timeInterval the numeric value with space then time type.
     *    Example of valid types: 6 hours, 2 days, 1 minute.
     */
    public function is_within_next(string|int $time_interval): bool
    {
        $now = new static(new Chronos());
        $interval = $now->modify('+' . $time_interval);
        $this_time = $this->format('U');
        return $this_time <= $interval->format('U') && $this_time >= $now->format('U');
    }
    /**
     * Get the difference by the given interval using a filter callable
     *
     * @param \DateInterval $interval An interval to traverse by
     * @param callable $callback The callback to use for filtering.
     * @param \Cake\Chronos\ChronosDate|null $other The instance to difference from.
     * @param bool $absolute Get the absolute of the difference
     * @param int $options DatePeriod options, {@see https://www.php.net/manual/en/class.dateperiod.php}
     */
    public function diff_filtered(DateInterval $interval, callable $callback, ?Chronos_Date $other = null, bool $absolute = true, int $options = 0): int
    {
        $start = $this;
        $end = $other ?? new Chronos_Date(Chronos::now());
        $inverse = false;
        if ($end < $start) {
            $start = $end;
            $end = $this;
            $inverse = true;
        }
        // Hack around PHP's DatePeriod not counting equal dates at midnight as
        // within the range. Sadly INCLUDE_END_DATE doesn't land until 8.2
        $end_time = $end->native->modify('+1 second');
        $period = new DatePeriod($start->native, $interval, $end_time, $options);
        $vals = array_filter(iterator_to_array($period), fn(DateTimeInterface $date) => $callback(static::parse($date)));
        $diff = count($vals);
        return $inverse && !$absolute ? -$diff : $diff;
    }
    /**
     * Get the difference in years
     *
     * @param \Cake\Chronos\ChronosDate|null $other The instance to difference from.
     * @param bool $absolute Get the absolute of the difference
     */
    public function diff_in_years(?Chronos_Date $other = null, bool $absolute = true): int
    {
        $diff = $this->diff($other ?? new static(new Chronos()), $absolute);
        return $diff->invert ? -$diff->y : $diff->y;
    }
    /**
     * Get the difference in months
     *
     * @param \Cake\Chronos\ChronosDate|null $other The instance to difference from.
     * @param bool $absolute Get the absolute of the difference
     */
    public function diff_in_months(?Chronos_Date $other = null, bool $absolute = true): int
    {
        $diff = $this->diff($other ?? new static(Chronos::now()), $absolute);
        $months = $diff->y * Chronos::MONTHS_PER_YEAR + $diff->m;
        return $diff->invert ? -$months : $months;
    }
    /**
     * Get the difference in weeks
     *
     * @param \Cake\Chronos\ChronosDate|null $other The instance to difference from.
     * @param bool $absolute Get the absolute of the difference
     */
    public function diff_in_weeks(?Chronos_Date $other = null, bool $absolute = true): int
    {
        return (int) ($this->diff_in_days($other, $absolute) / Chronos::DAYS_PER_WEEK);
    }
    /**
     * Get the difference in days
     *
     * @param \Cake\Chronos\ChronosDate|null $other The instance to difference from.
     * @param bool $absolute Get the absolute of the difference
     */
    public function diff_in_days(?Chronos_Date $other = null, bool $absolute = true): int
    {
        $diff = $this->diff($other ?? new static(Chronos::now()), $absolute);
        return $diff->invert ? -(int) $diff->days : (int) $diff->days;
    }
    /**
     * Get the difference in days using a filter callable
     *
     * @param callable $callback The callback to use for filtering.
     * @param \Cake\Chronos\ChronosDate|null $other The instance to difference from.
     * @param bool $absolute Get the absolute of the difference
     * @param int $options DatePeriod options, {@see https://www.php.net/manual/en/class.dateperiod.php}
     */
    public function diff_in_days_filtered(callable $callback, ?Chronos_Date $other = null, bool $absolute = true, int $options = 0): int
    {
        return $this->diff_filtered(new DateInterval('P1D'), $callback, $other, $absolute, $options);
    }
    /**
     * Get the difference in weekdays
     *
     * @param \Cake\Chronos\ChronosDate|null $other The instance to difference from.
     * @param bool $absolute Get the absolute of the difference
     * @param int $options DatePeriod options, {@see https://www.php.net/manual/en/class.dateperiod.php}
     */
    public function diff_in_weekdays(?Chronos_Date $other = null, bool $absolute = true, int $options = 0): int
    {
        return $this->diff_in_days_filtered(fn(Chronos_Date $date) => $date->is_weekday(), $other, $absolute, $options);
    }
    /**
     * Get the difference in weekend days using a filter
     *
     * @param \Cake\Chronos\ChronosDate|null $other The instance to difference from.
     * @param bool $absolute Get the absolute of the difference
     * @param int $options DatePeriod options, {@see https://www.php.net/manual/en/class.dateperiod.php}
     */
    public function diff_in_weekend_days(?Chronos_Date $other = null, bool $absolute = true, int $options = 0): int
    {
        return $this->diff_in_days_filtered(fn(Chronos_Date $date) => $date->is_weekend(), $other, $absolute, $options);
    }
    /**
     * Get the difference in a human readable format.
     *
     * When comparing a value in the past to default now:
     * 5 months ago
     *
     * When comparing a value in the future to default now:
     * 5 months from now
     *
     * When comparing a value in the past to another value:
     * 5 months before
     *
     * When comparing a value in the future to another value:
     * 5 months after
     *
     * @param \Cake\Chronos\ChronosDate|null $other The datetime to compare with.
     * @param bool $absolute removes difference modifiers ago, after, etc
     */
    public function diff_for_humans(?Chronos_Date $other = null, bool $absolute = false): string
    {
        return static::diff_formatter()->diff_for_humans($this, $other, $absolute);
    }
    /**
     * Returns the date as a `DateTimeImmutable` instance at midnight.
     *
     * @param \DateTimeZone|string|null $timezone Time zone the DateTimeImmutable instance will be in
     */
    public function to_date_time_immutable(DateTimeZone|string|null $timezone = null): DateTimeImmutable
    {
        if ($timezone === null) {
            return $this->native;
        }
        $timezone = is_string($timezone) ? new DateTimeZone($timezone) : $timezone;
        return new DateTimeImmutable($this->native->format('Y-m-d H:i:s.u'), $timezone);
    }
    /**
     * Returns the date as a `DateTimeImmutable` instance at midnight.
     *
     * Alias of `toDateTimeImmutable()`.
     *
     * @param \DateTimeZone|string|null $timezone Time zone the DateTimeImmutable instance will be in
     */
    public function to_native(DateTimeZone|string|null $timezone = null): DateTimeImmutable
    {
        return $this->to_date_time_immutable($timezone);
    }
    /**
     * Get a part of the object
     *
     * @param string $name The property name to read.
     * @return string|float|int|bool The property value.
     * @throws \InvalidArgumentException
     */
    public function __get(string $name): string|float|int|bool
    {
        static $formats = ['year' => 'Y', 'yearIso' => 'o', 'month' => 'n', 'day' => 'j', 'dayOfWeek' => 'N', 'dayOfYear' => 'z', 'weekOfYear' => 'W', 'daysInMonth' => 't'];
        return match (true) {
            isset($formats[$name]) => (int) $this->format($formats[$name]),
            $name === 'dayOfWeekName' => $this->format('l'),
            $name === 'weekOfMonth' => (int) ceil($this->day / Chronos::DAYS_PER_WEEK),
            $name === 'age' => $this->diff_in_years(),
            $name === 'quarter' => (int) ceil($this->month / 3),
            $name === 'half' => $this->month <= 6 ? 1 : 2,
            default => throw new InvalidArgumentException(sprintf('Unknown getter `%s`', $name)),
        };
    }
    /**
     * Check if an attribute exists on the object
     *
     * @param string $name The property name to check.
     * @return bool Whether the property exists.
     */
    public function __isset(string $name): bool
    {
        try {
            $this->__get($name);
        } catch (InvalidArgumentException) {
            return false;
        }
        return true;
    }
    /**
     * Return properties for debugging.
     */
    public function __debugInfo(): array
    {
        return ['hasFixedNow' => Chronos::has_test_now(), 'date' => $this->format('Y-m-d')];
    }
}