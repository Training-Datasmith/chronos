<?php

declare (strict_types=1);
/**
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @copyright     Copyright (c) Brian Nesbitt <brian@nesbot.com>
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
use RuntimeException;
use Stringable;
/**
 * An Immutable extension on the native DateTime object.
 *
 * Adds a number of convenience APIs methods and the ability
 * to easily convert into a mutable object.
 *
 * @property-read int $year
 * @property-read int $yearIso
 * @property-read int<1, 12> $month
 * @property-read int<1, 31> $day
 * @property-read int<0, 23> $hour
 * @property-read int<0, 59> $minute
 * @property-read int<0, 59> $second
 * @property-read int<0, 999999> $micro
 * @property-read int<0, 999999> $microsecond
 * @property-read int $timestamp seconds since the Unix Epoch
 * @property-read \DateTimeZone $timezone the current timezone
 * @property-read \DateTimeZone $tz alias of timezone
 * @property-read int<1, 7> $dayOfWeek 1 (for Monday) through 7 (for Sunday)
 * @property-read int<0, 365> $dayOfYear 0 through 365
 * @property-read int<1, 5> $weekOfMonth 1 through 5
 * @property-read int<1, 53> $weekOfYear ISO-8601 week number of year, weeks starting on Monday
 * @property-read int<1, 31> $daysInMonth number of days in the given month
 * @property-read int $age does a diffInYears() with default parameters
 * @property-read int<1, 4> $quarter the quarter of this instance, 1 - 4
 * @property-read int<1, 2> $half the half of the year, with 1 for months Jan...Jun and 2 for Jul...Dec.
 * @property-read int $offset the timezone offset in seconds from UTC
 * @property-read int $offsetHours the timezone offset in hours from UTC
 * @property-read bool $dst daylight savings time indicator, true if DST, false otherwise
 * @property-read bool $local checks if the timezone is local, true if local, false otherwise
 * @property-read bool $utc checks if the timezone is UTC, true if UTC, false otherwise
 * @property-read string $timezoneName
 * @property-read string $tzName
 * @immutable
 * @phpstan-consistent-constructor
 */
class Chronos extends DateTimeImmutable implements Stringable
{
    use Formatting_Trait;
    /**
     * @var int
     */
    public const MONDAY = 1;
    /**
     * @var int
     */
    public const TUESDAY = 2;
    /**
     * @var int
     */
    public const WEDNESDAY = 3;
    /**
     * @var int
     */
    public const THURSDAY = 4;
    /**
     * @var int
     */
    public const FRIDAY = 5;
    /**
     * @var int
     */
    public const SATURDAY = 6;
    /**
     * @var int
     */
    public const SUNDAY = 7;
    /**
     * @var int
     */
    public const YEARS_PER_CENTURY = 100;
    /**
     * @var int
     */
    public const YEARS_PER_DECADE = 10;
    /**
     * @var int
     */
    public const MONTHS_PER_YEAR = 12;
    /**
     * @var int
     */
    public const MONTHS_PER_QUARTER = 3;
    /**
     * @var int
     */
    public const WEEKS_PER_YEAR = 52;
    /**
     * @var int
     */
    public const DAYS_PER_WEEK = 7;
    /**
     * @var int
     */
    public const HOURS_PER_DAY = 24;
    /**
     * @var int
     */
    public const MINUTES_PER_HOUR = 60;
    /**
     * @var int
     */
    public const SECONDS_PER_MINUTE = 60;
    /**
     * Default format to use for __toString method when type juggling occurs.
     *
     * @var string
     */
    public const DEFAULT_TO_STRING_FORMAT = 'Y-m-d H:i:s';
    /**
     * A test Chronos instance to be returned when now instances are created
     *
     * There is a single test now for all date/time classes provided by Chronos.
     * This aims to emulate stubbing out 'now' which is a single global fact.
     */
    protected static ?Chronos $test_now = null;
    /**
     * Format to use for __toString method when type juggling occurs.
     */
    protected static string $to_string_format = self::DEFAULT_TO_STRING_FORMAT;
    /**
     * Days of weekend
     */
    protected static array $weekend_days = [Chronos::SATURDAY, Chronos::SUNDAY];
    /**
     * Names of days of the week.
     */
    protected static array $days = [Chronos::MONDAY => 'Monday', Chronos::TUESDAY => 'Tuesday', Chronos::WEDNESDAY => 'Wednesday', Chronos::THURSDAY => 'Thursday', Chronos::FRIDAY => 'Friday', Chronos::SATURDAY => 'Saturday', Chronos::SUNDAY => 'Sunday'];
    /**
     * First day of week
     */
    protected static int $week_starts_at = Chronos::MONDAY;
    /**
     * Last day of week
     */
    protected static int $week_ends_at = Chronos::SUNDAY;
    /**
     * Instance of the diff formatting object.
     */
    protected static ?Difference_Formatter_Interface $diff_formatter = null;
    /**
     * Regex for relative period.
     */
    // phpcs:disable Generic.Files.LineLength.TooLong
    protected static string $relative_pattern = '/this|next|last|tomorrow|yesterday|midnight|today|[+-]|first|last|ago/i';
    /**
     * Errors from last time createFromFormat() was called.
     */
    protected static array|false $last_errors = false;
    /**
     * Create a new Chronos instance.
     *
     * Please see the testing aids section (specifically static::setTestNow())
     * for more on the possibility of this constructor returning a test instance.
     *
     * @param \Cake\Chronos\ChronosDate|\Cake\Chronos\ChronosTime|\DateTimeInterface|string|int|null $time Fixed or relative time
     * @param \DateTimeZone|string|null $timezone The timezone for the instance
     */
    public function __construct(Chronos_Date|Chronos_Time|DateTimeInterface|string|int|null $time = 'now', DateTimeZone|string|null $timezone = null)
    {
        if (is_int($time) || is_string($time) && ctype_digit($time)) {
            parent::__construct("@{$time}");
            return;
        }
        if ($timezone !== null) {
            $timezone = $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone($timezone);
        }
        if (is_object($time)) {
            if ($time instanceof DateTimeInterface) {
                $timezone = $time->get_timezone();
            }
            $time = $time->format('Y-m-d H:i:s.u');
        }
        $test_now = static::get_test_now();
        if ($test_now === null) {
            parent::__construct($time ?? 'now', $timezone);
            return;
        }
        $relative = static::has_relative_keywords($time);
        if ($time && $time !== 'now' && !$relative) {
            parent::__construct($time, $timezone);
            return;
        }
        $test_now = clone $test_now;
        $relative_time = self::is_time_expression($time);
        if (!$relative_time && $timezone !== $test_now->get_timezone()) {
            $test_now = $test_now->set_timezone($timezone ?? date_default_timezone_get());
        }
        if ($relative) {
            $test_now = $test_now->modify($time ?? 'now');
        }
        parent::__construct($test_now->format('Y-m-d H:i:s.u'), $timezone);
    }
    /**
     * Set a Chronos instance (real or mock) to be returned when a "now"
     * instance is created.  The provided instance will be returned
     * specifically under the following conditions:
     *   - A call to the static now() method, ex. Chronos::now()
     *   - When a null (or blank string) is passed to the constructor or parse(), ex. new Chronos(null)
     *   - When the string "now" is passed to the constructor or parse(), ex. new Chronos('now')
     *   - When a string containing the desired time is passed to Chronos::parse()
     *
     * Note the timezone parameter was left out of the examples above and
     * has no affect as the mock value will be returned regardless of its value.
     *
     * To clear the test instance call this method using the default
     * parameter of null.
     *
     * @param \Cake\Chronos\Chronos|string|null $testNow The instance to use for all future instances.
     */
    public static function set_test_now(Chronos|string|null $test_now = null): void
    {
        static::$test_now = is_string($test_now) ? static::parse($test_now) : $test_now;
    }
    /**
     * Get the Chronos instance (real or mock) to be returned when a "now"
     * instance is created.
     *
     * @return \Cake\Chronos\Chronos|null The current instance used for testing
     */
    public static function get_test_now(): ?Chronos
    {
        return static::$test_now;
    }
    /**
     * Determine if there is a valid test instance set. A valid test instance
     * is anything that is not null.
     *
     * @return bool True if there is a test instance, otherwise false
     */
    public static function has_test_now(): bool
    {
        return static::$test_now !== null;
    }
    /**
     * Determine if there is just a time in the time string
     *
     * @param string|null $time The time string to check.
     * @return bool true if there is a keyword, otherwise false
     */
    private static function is_time_expression(?string $time): bool
    {
        // Just a time
        if (is_string($time) && preg_match('/^[0-2]?[0-9]:[0-5][0-9](?::[0-5][0-9](?:\.[0-9]{1,6})?)?$/', $time)) {
            return true;
        }
        return false;
    }
    /**
     * Determine if there is a relative keyword in the time string, this is to
     * create dates relative to now for test instances. e.g.: next tuesday
     *
     * @param string|null $time The time string to check.
     * @return bool true if there is a keyword, otherwise false
     */
    public static function has_relative_keywords(?string $time): bool
    {
        if (self::is_time_expression($time)) {
            return true;
        }
        // skip common format with a '-' in it
        if ($time && preg_match('/[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}/', $time) !== 1) {
            return preg_match(static::$relative_pattern, $time) > 0;
        }
        return false;
    }
    /**
     * Get weekend days
     */
    public static function get_weekend_days(): array
    {
        return static::$weekend_days;
    }
    /**
     * Set weekend days
     *
     * @param array $days Which days are 'weekends'.
     */
    public static function set_weekend_days(array $days): void
    {
        static::$weekend_days = $days;
    }
    /**
     * Get the first day of week
     */
    public static function get_week_starts_at(): int
    {
        return static::$week_starts_at;
    }
    /**
     * Set the first day of week
     *
     * @param int $day The day the week starts with.
     */
    public static function set_week_starts_at(int $day): void
    {
        static::$week_starts_at = $day;
    }
    /**
     * Get the last day of week
     */
    public static function get_week_ends_at(): int
    {
        return static::$week_ends_at;
    }
    /**
     * Set the last day of week
     *
     * @param int $day The day the week ends with.
     */
    public static function set_week_ends_at(int $day): void
    {
        static::$week_ends_at = $day;
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
     * Create an instance from a DateTimeInterface
     *
     * @param \DateTimeInterface $other The datetime instance to convert.
     */
    public static function instance(DateTimeInterface $other): static
    {
        return new static($other);
    }
    /**
     * Create an instance from a string.  This is an alias for the
     * constructor that allows better fluent syntax as it allows you to do
     * Chronos::parse('Monday next week')->fn() rather than
     * (new Chronos('Monday next week'))->fn()
     *
     * @param \Cake\Chronos\ChronosDate|\Cake\Chronos\ChronosTime|\DateTimeInterface|string|int|null $time The strtotime compatible string to parse
     * @param \DateTimeZone|string|null $timezone The DateTimeZone object or timezone name.
     */
    public static function parse(Chronos_Date|Chronos_Time|DateTimeInterface|string|int|null $time = 'now', DateTimeZone|string|null $timezone = null): static
    {
        return new static($time, $timezone);
    }
    /**
     * Get an instance for the current date and time
     *
     * @param \DateTimeZone|string|null $timezone The DateTimeZone object or timezone name.
     */
    public static function now(DateTimeZone|string|null $timezone = null): static
    {
        return new static('now', $timezone);
    }
    /**
     * Create an instance for today
     *
     * @param \DateTimeZone|string|null $timezone The timezone to use.
     */
    public static function today(DateTimeZone|string|null $timezone = null): static
    {
        return new static('midnight', $timezone);
    }
    /**
     * Create an instance for tomorrow
     *
     * @param \DateTimeZone|string|null $timezone The DateTimeZone object or timezone name the new instance should use.
     */
    public static function tomorrow(DateTimeZone|string|null $timezone = null): static
    {
        return new static('tomorrow, midnight', $timezone);
    }
    /**
     * Create an instance for yesterday
     *
     * @param \DateTimeZone|string|null $timezone The DateTimeZone object or timezone name the new instance should use.
     */
    public static function yesterday(DateTimeZone|string|null $timezone = null): static
    {
        return new static('yesterday, midnight', $timezone);
    }
    /**
     * Create an instance for the greatest supported date.
     */
    public static function max_value(): static
    {
        return static::create_from_timestamp(PHP_INT_MAX);
    }
    /**
     * Create an instance for the lowest supported date.
     */
    public static function min_value(): static
    {
        $max = PHP_INT_SIZE === 4 ? PHP_INT_MAX : PHP_INT_MAX / 10;
        return static::create_from_timestamp(~$max);
    }
    /**
     * Create an instance from a specific date and time.
     *
     * If any of $year, $month or $day are set to null their now() values
     * will be used.
     *
     * If $hour is null it will be set to its now() value and the default values
     * for $minute, $second and $microsecond will be their now() values.
     * If $hour is not null then the default values for $minute, $second
     * and $microsecond will be 0.
     *
     * @param int|null $year The year to create an instance with.
     * @param int|null $month The month to create an instance with.
     * @param int|null $day The day to create an instance with.
     * @param int|null $hour The hour to create an instance with.
     * @param int|null $minute The minute to create an instance with.
     * @param int|null $second The second to create an instance with.
     * @param int|null $microsecond The microsecond to create an instance with.
     * @param \DateTimeZone|string|null $timezone The DateTimeZone object or timezone name the new instance should use.
     */
    public static function create(?int $year = null, ?int $month = null, ?int $day = null, ?int $hour = null, ?int $minute = null, ?int $second = null, ?int $microsecond = null, DateTimeZone|string|null $timezone = null): static
    {
        $now = static::now();
        $year ??= (int) $now->format('Y');
        $month ??= $now->format('m');
        $day ??= $now->format('d');
        if ($hour === null) {
            $hour = $now->format('H');
            $minute ??= $now->format('i');
            $second ??= $now->format('s');
            $microsecond ??= $now->format('u');
        } else {
            $minute ??= 0;
            $second ??= 0;
            $microsecond ??= 0;
        }
        $instance = static::create_from_format('Y-m-d H:i:s.u', sprintf('%s-%s-%s %s:%02s:%02s.%06s', 0, $month, $day, $hour, $minute, $second, $microsecond), $timezone);
        return $instance->add_years($year);
    }
    /**
     * Create an instance from just a date. The time portion is set to now.
     *
     * @param int|null $year The year to create an instance with.
     * @param int|null $month The month to create an instance with.
     * @param int|null $day The day to create an instance with.
     * @param \DateTimeZone|string|null $timezone The DateTimeZone object or timezone name the new instance should use.
     */
    public static function create_from_date(?int $year = null, ?int $month = null, ?int $day = null, DateTimeZone|string|null $timezone = null): static
    {
        return static::create($year, $month, $day, null, null, null, null, $timezone);
    }
    /**
     * Create an instance from just a time. The date portion is set to today.
     *
     * @param int|null $hour The hour to create an instance with.
     * @param int|null $minute The minute to create an instance with.
     * @param int|null $second The second to create an instance with.
     * @param int|null $microsecond The microsecond to create an instance with.
     * @param \DateTimeZone|string|null $timezone The DateTimeZone object or timezone name the new instance should use.
     */
    public static function create_from_time(?int $hour = null, ?int $minute = null, ?int $second = null, ?int $microsecond = null, DateTimeZone|string|null $timezone = null): static
    {
        return static::create(null, null, null, $hour, $minute, $second, $microsecond, $timezone);
    }
    /**
     * Create an instance from a specific format
     *
     * @param string $format The date() compatible format string.
     * @param string $time The formatted date string to interpret.
     * @param \DateTimeZone|string|null $timezone The DateTimeZone object or timezone name the new instance should use.
     * @throws \InvalidArgumentException
     */
    public static function create_from_format(string $format, string $time, DateTimeZone|string|null $timezone = null): static
    {
        if ($timezone !== null) {
            $date_time = parent::create_from_format($format, $time, $timezone ? static::safe_create_date_time_zone($timezone) : null);
        } else {
            $date_time = parent::create_from_format($format, $time);
        }
        static::$last_errors = DateTimeImmutable::get_last_errors();
        if (!$date_time) {
            $message = static::$last_errors ? implode(PHP_EOL, static::$last_errors['errors']) : 'Unknown error';
            throw new InvalidArgumentException($message);
        }
        return $date_time;
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
     * Creates an instance from an array of date and time values.
     *
     * The 'year', 'month' and 'day' values must all be set for a date. The time
     * values all default to 0.
     *
     * The 'timezone' value can be any format supported by `\DateTimeZone`.
     *
     * Allowed values:
     *  - year
     *  - month
     *  - day
     *  - hour
     *  - minute
     *  - second
     *  - microsecond
     *  - meridian ('am' or 'pm')
     *  - timezone
     *
     * @param array<int|string> $values Array of date and time values.
     */
    public static function create_from_array(array $values): static
    {
        $values += ['hour' => 0, 'minute' => 0, 'second' => 0, 'microsecond' => 0, 'timezone' => null];
        $formatted = '';
        if (isset($values['year'], $values['month'], $values['day']) && (is_numeric($values['year']) && is_numeric($values['month']) && is_numeric($values['day']))) {
            $formatted .= sprintf('%04d-%02d-%02d ', $values['year'], $values['month'], $values['day']);
        }
        if (isset($values['meridian']) && (int) $values['hour'] === 12) {
            $values['hour'] = 0;
        }
        if (isset($values['meridian'])) {
            $values['hour'] = strtolower((string) $values['meridian']) === 'am' ? (int) $values['hour'] : (int) $values['hour'] + 12;
        }
        $formatted .= sprintf('%02d:%02d:%02d.%06d', $values['hour'], $values['minute'], $values['second'], $values['microsecond']);
        assert(!is_int($values['timezone']), 'Timezone cannot be of type `int`');
        return static::parse($formatted, $values['timezone']);
    }
    /**
     * Create an instance from a timestamp
     *
     * @param float|int $timestamp The timestamp to create an instance from.
     * @param \DateTimeZone|string|null $timezone The DateTimeZone object or timezone name the new instance should use.
     */
    public static function create_from_timestamp(float|int $timestamp, DateTimeZone|string|null $timezone = null): static
    {
        $instance = PHP_VERSION_ID >= 80400 ? parent::create_from_timestamp($timestamp) : new static('@' . $timestamp);
        return $timezone ? $instance->set_timezone($timezone) : $instance;
    }
    /**
     * Creates a DateTimeZone from a string or a DateTimeZone
     *
     * @param \DateTimeZone|string|null $object The value to convert.
     */
    protected static function safe_create_date_time_zone(DateTimeZone|string|null $object): DateTimeZone
    {
        if ($object === null) {
            return new DateTimeZone(date_default_timezone_get());
        }
        if ($object instanceof DateTimeZone) {
            return $object;
        }
        return new DateTimeZone($object);
    }
    /**
     * Create a new DateInterval instance from specified values.
     *
     * @param int|null $years The year to use.
     * @param int|null $months The month to use.
     * @param int|null $weeks The week to use.
     * @param int|null $days The day to use.
     * @param int|null $hours The hours to use.
     * @param int|null $minutes The minutes to use.
     * @param int|null $seconds The seconds to use.
     * @param int|null $microseconds The microseconds to use.
     */
    public static function create_interval(?int $years = null, ?int $months = null, ?int $weeks = null, ?int $days = null, ?int $hours = null, ?int $minutes = null, ?int $seconds = null, ?int $microseconds = null): DateInterval
    {
        $spec = 'P';
        $rollover = static::rollover_time($microseconds, 1000000);
        $seconds = $seconds === null ? $rollover : $seconds + (int) $rollover;
        $rollover = static::rollover_time($seconds, 60);
        $minutes = $minutes === null ? $rollover : $minutes + (int) $rollover;
        $rollover = static::rollover_time($minutes, 60);
        $hours = $hours === null ? $rollover : $hours + (int) $rollover;
        $rollover = static::rollover_time($hours, 24);
        $days = $days === null ? $rollover : $days + (int) $rollover;
        if ($years) {
            $spec .= $years . 'Y';
        }
        if ($months) {
            $spec .= $months . 'M';
        }
        if ($weeks) {
            $spec .= $weeks . 'W';
        }
        if ($days) {
            $spec .= $days . 'D';
        }
        if ($hours || $minutes || $seconds) {
            $spec .= 'T';
            if ($hours) {
                $spec .= $hours . 'H';
            }
            if ($minutes) {
                $spec .= $minutes . 'M';
            }
            if ($seconds) {
                $spec .= $seconds . 'S';
            }
        }
        if ($microseconds && $spec === 'P') {
            $spec .= 'T0S';
        }
        $instance = new DateInterval($spec);
        if ($microseconds) {
            $instance->f = $microseconds / 1000000;
        }
        return $instance;
    }
    /**
     * Updates value to remaininger and returns rollover value for time
     * unit or null if no rollover.
     *
     * @param int|null $value Time unit value
     * @param int $max Time unit max value
     */
    protected static function rollover_time(?int &$value, int $max): ?int
    {
        if ($value === null || $value < $max) {
            return null;
        }
        $rollover = intdiv($value, $max);
        $value = $value % $max;
        return $rollover;
    }
    /**
     * Sets the date and time.
     *
     * @param int $year The year to set.
     * @param int $month The month to set.
     * @param int $day The day to set.
     * @param int $hour The hour to set.
     * @param int $minute The minute to set.
     * @param int $second The second to set.
     */
    public function set_date_time(int $year, int $month, int $day, int $hour, int $minute, int $second = 0): static
    {
        return $this->set_date($year, $month, $day)->set_time($hour, $minute, $second);
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
        return parent::set_date($year, $month, $day);
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
        return parent::set_iso_date($year, $week, $day_of_week);
    }
    /**
     * Sets the time.
     *
     * @param int $hours Hours of the time
     * @param int $minutes Minutes of the time
     * @param int $seconds Seconds of the time
     * @param int $microseconds Microseconds of the time
     */
    public function set_time(int $hours, int $minutes, int $seconds = 0, int $microseconds = 0): static
    {
        return parent::set_time($hours, $minutes, $seconds, $microseconds);
    }
    /**
     * Creates a new instance with date modified according to DateTimeImmutable::modifier().
     *
     * @param string $modifier Date modifier
     * @throws \InvalidArgumentException
     * @see https://www.php.net/manual/en/datetimeimmutable.modify.php
     */
    public function modify(string $modifier): static
    {
        $new = parent::modify($modifier);
        if ($new === false) {
            throw new InvalidArgumentException(sprintf('Unable to modify date using `%s`', $modifier));
        }
        return $new;
    }
    /**
     * Returns the difference between this instance and target.
     *
     * @param \DateTimeInterface $target Target instance
     * @param bool $absolute Whether the interval is forced to be positive
     */
    public function diff(DateTimeInterface $target, bool $absolute = false): DateInterval
    {
        return parent::diff($target, $absolute);
    }
    /**
     * Returns formatted date string according to DateTimeImmutable::format().
     *
     * @param string $format String format
     */
    public function format(string $format): string
    {
        return parent::format($format);
    }
    /**
     * Returns the timezone offset.
     */
    public function get_offset(): int
    {
        return parent::get_offset();
    }
    /**
     * Sets the date and time based on a Unix timestamp.
     *
     * @param int $timestamp Unix timestamp representing the date
     */
    public function set_timestamp(int $timestamp): static
    {
        return parent::set_timestamp($timestamp);
    }
    /**
     * Gets the Unix timestamp for this instance.
     */
    public function get_timestamp(): int
    {
        return parent::get_timestamp();
    }
    /**
     * Set the instance's timezone from a string or object
     *
     * @param \DateTimeZone|string $value The DateTimeZone object or timezone name to use.
     */
    public function set_timezone(DateTimeZone|string $value): static
    {
        return parent::set_timezone(static::safe_create_date_time_zone($value));
    }
    /**
     * Return time zone set for this instance.
     */
    public function get_timezone(): DateTimeZone
    {
        $tz = parent::get_timezone();
        if ($tz === false) {
            throw new RuntimeException('Time zone could not be retrieved.');
        }
        return $tz;
    }
    /**
     * Set the time by time string
     *
     * @param string $time Time as string.
     */
    public function set_time_from_time_string(string $time): static
    {
        $time = explode(':', $time);
        $hour = $time[0];
        $minute = $time[1] ?? 0;
        $second = $time[2] ?? 0;
        return $this->set_time((int) $hour, (int) $minute, (int) $second);
    }
    /**
     * Set the instance's timestamp
     *
     * @param int $value The timestamp value to set.
     */
    public function timestamp(int $value): static
    {
        return $this->set_timestamp($value);
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
     * Set the instance's hour
     *
     * @param int $value The hour value.
     */
    public function hour(int $value): static
    {
        return $this->set_time($value, $this->minute, $this->second);
    }
    /**
     * Set the instance's minute
     *
     * @param int $value The minute value.
     */
    public function minute(int $value): static
    {
        return $this->set_time($this->hour, $value, $this->second);
    }
    /**
     * Set the instance's second
     *
     * @param int $value The seconds value.
     */
    public function second(int $value): static
    {
        return $this->set_time($this->hour, $this->minute, $value);
    }
    /**
     * Set the instance's microsecond
     *
     * @param int $value The microsecond value.
     */
    public function microsecond(int $value): static
    {
        return $this->set_time($this->hour, $this->minute, $this->second, $value);
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
     * Add hours to the instance. Positive $value travels forward while
     * negative $value travels into the past.
     *
     * @param int $value The number of hours to add.
     */
    public function add_hours(int $value): static
    {
        return $this->modify("{$value} hour");
    }
    /**
     * Remove hours from the instance
     *
     * @param int $value The number of hours to remove.
     */
    public function sub_hours(int $value): static
    {
        return $this->add_hours(-$value);
    }
    /**
     * Add minutes to the instance. Positive $value travels forward while
     * negative $value travels into the past.
     *
     * @param int $value The number of minutes to add.
     */
    public function add_minutes(int $value): static
    {
        return $this->modify("{$value} minute");
    }
    /**
     * Remove minutes from the instance
     *
     * @param int $value The number of minutes to remove.
     */
    public function sub_minutes(int $value): static
    {
        return $this->add_minutes(-$value);
    }
    /**
     * Add seconds to the instance. Positive $value travels forward while
     * negative $value travels into the past.
     *
     * @param int $value The number of seconds to add.
     */
    public function add_seconds(int $value): static
    {
        return $this->modify("{$value} second");
    }
    /**
     * Remove seconds from the instance
     *
     * @param int $value The number of seconds to remove.
     */
    public function sub_seconds(int $value): static
    {
        return $this->add_seconds(-$value);
    }
    /**
     * Sets the time to 00:00:00
     */
    public function start_of_day(): static
    {
        return $this->modify('midnight');
    }
    /**
     * Sets the time to 23:59:59 or 23:59:59.999999
     * if `$microseconds` is true.
     *
     * @param bool $microseconds Whether to set microseconds
     */
    public function end_of_day(bool $microseconds = false): static
    {
        if ($microseconds) {
            return $this->modify('23:59:59.999999');
        }
        return $this->modify('23:59:59');
    }
    /**
     * Sets the date to the first day of the month and the time to 00:00:00
     */
    public function start_of_month(): static
    {
        return $this->modify('first day of this month midnight');
    }
    /**
     * Sets the date to end of the month and time to 23:59:59
     */
    public function end_of_month(): static
    {
        return $this->modify('last day of this month, 23:59:59');
    }
    /**
     * Sets the date to the first day of the year and the time to 00:00:00
     */
    public function start_of_year(): static
    {
        return $this->modify('first day of january midnight');
    }
    /**
     * Sets the date to end of the year and time to 23:59:59
     */
    public function end_of_year(): static
    {
        return $this->modify('last day of december, 23:59:59');
    }
    /**
     * Sets the date to the first day of the decade and the time to 00:00:00
     */
    public function start_of_decade(): static
    {
        $year = $this->year - $this->year % Chronos::YEARS_PER_DECADE;
        return $this->modify("first day of january {$year}, midnight");
    }
    /**
     * Sets the date to end of the decade and time to 23:59:59
     */
    public function end_of_decade(): static
    {
        $year = $this->year - $this->year % Chronos::YEARS_PER_DECADE + Chronos::YEARS_PER_DECADE - 1;
        return $this->modify("last day of december {$year}, 23:59:59");
    }
    /**
     * Sets the date to the first day of the century and the time to 00:00:00
     */
    public function start_of_century(): static
    {
        $year = $this->start_of_year()->year($this->year - 1 - ($this->year - 1) % Chronos::YEARS_PER_CENTURY + 1)->year;
        return $this->modify("first day of january {$year}, midnight");
    }
    /**
     * Sets the date to end of the century and time to 23:59:59
     */
    public function end_of_century(): static
    {
        $y = $this->year - 1 - ($this->year - 1) % Chronos::YEARS_PER_CENTURY + Chronos::YEARS_PER_CENTURY;
        $year = $this->end_of_year()->year($y)->year;
        return $this->modify("last day of december {$year}, 23:59:59");
    }
    /**
     * Sets the date to the first day of week (defined in $weekStartsAt) and the time to 00:00:00
     */
    public function start_of_week(): static
    {
        $date_time = $this;
        if ($date_time->day_of_week !== static::$week_starts_at) {
            $date_time = $date_time->previous(static::$week_starts_at);
        }
        return $date_time->start_of_day();
    }
    /**
     * Sets the date to end of week (defined in $weekEndsAt) and time to 23:59:59
     */
    public function end_of_week(): static
    {
        $date_time = $this;
        if ($date_time->day_of_week !== static::$week_ends_at) {
            $date_time = $date_time->next(static::$week_ends_at);
        }
        return $date_time->end_of_day();
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
        return $this->modify("next {$day}, midnight");
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
        return $this->modify("last {$day}, midnight");
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
        return $this->modify("first {$day} of this month, midnight");
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
        return $this->modify("last {$day} of this month, midnight");
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
        return $this->modify("first {$day} of january, midnight");
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
        return $this->modify("last {$day} of december, midnight");
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
     * @param \DateTimeInterface $other The instance to compare with.
     */
    public function equals(DateTimeInterface $other): bool
    {
        return $this == $other;
    }
    /**
     * Determines if the instance is not equal to another
     *
     * @param \DateTimeInterface $other The instance to compare with.
     */
    public function not_equals(DateTimeInterface $other): bool
    {
        return !$this->equals($other);
    }
    /**
     * Determines if the instance is greater (after) than another
     *
     * @param \DateTimeInterface $other The instance to compare with.
     */
    public function greater_than(DateTimeInterface $other): bool
    {
        return $this > $other;
    }
    /**
     * Determines if the instance is greater (after) than or equal to another
     *
     * @param \DateTimeInterface $other The instance to compare with.
     */
    public function greater_than_or_equals(DateTimeInterface $other): bool
    {
        return $this >= $other;
    }
    /**
     * Determines if the instance is less (before) than another
     *
     * @param \DateTimeInterface $other The instance to compare with.
     */
    public function less_than(DateTimeInterface $other): bool
    {
        return $this < $other;
    }
    /**
     * Determines if the instance is less (before) or equal to another
     *
     * @param \DateTimeInterface $other The instance to compare with.
     */
    public function less_than_or_equals(DateTimeInterface $other): bool
    {
        return $this <= $other;
    }
    /**
     * Determines if the instance is between two others
     *
     * @param \DateTimeInterface $start Start of target range
     * @param \DateTimeInterface $end End of target range
     * @param bool $equals Whether to include the beginning and end of range
     */
    public function between(DateTimeInterface $start, DateTimeInterface $end, bool $equals = true): bool
    {
        if ($start > $end) {
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
     * @param \DateTimeInterface $first The instance to compare with.
     * @param \DateTimeInterface $second The instance to compare with.
     * @param \DateTimeInterface ...$others Others instances to compare with.
     */
    public function closest(DateTimeInterface $first, DateTimeInterface $second, DateTimeInterface ...$others): static
    {
        $winner = $first;
        $closest_diff_in_seconds = $this->diff_in_seconds($first);
        foreach ([$second, ...$others] as $other) {
            $other_diff_in_seconds = $this->diff_in_seconds($other);
            if ($other_diff_in_seconds < $closest_diff_in_seconds) {
                $winner = $other;
                $closest_diff_in_seconds = $other_diff_in_seconds;
            }
        }
        if ($winner instanceof static) {
            return $winner;
        }
        return new static($winner);
    }
    /**
     * Get the farthest date from the instance.
     *
     * @param \DateTimeInterface $first The instance to compare with.
     * @param \DateTimeInterface $second The instance to compare with.
     * @param \DateTimeInterface ...$others Others instances to compare with.
     */
    public function farthest(DateTimeInterface $first, DateTimeInterface $second, DateTimeInterface ...$others): static
    {
        $winner = $first;
        $farthest_diff_in_seconds = $this->diff_in_seconds($first);
        foreach ([$second, ...$others] as $other) {
            $other_diff_in_seconds = $this->diff_in_seconds($other);
            if ($other_diff_in_seconds > $farthest_diff_in_seconds) {
                $winner = $other;
                $farthest_diff_in_seconds = $other_diff_in_seconds;
            }
        }
        if ($winner instanceof static) {
            return $winner;
        }
        return new static($winner);
    }
    /**
     * Get the minimum instance between a given instance (default now) and the current instance.
     *
     * @param \DateTimeInterface|null $other The instance to compare with.
     */
    public function min(?DateTimeInterface $other = null): static
    {
        $other ??= static::now($this->tz);
        $winner = $this->less_than($other) ? $this : $other;
        if ($winner instanceof static) {
            return $winner;
        }
        return new static($winner);
    }
    /**
     * Get the maximum instance between a given instance (default now) and the current instance.
     *
     * @param \DateTimeInterface|null $other The instance to compare with.
     */
    public function max(?DateTimeInterface $other = null): static
    {
        $other ??= static::now($this->tz);
        $winner = $this->greater_than($other) ? $this : $other;
        if ($winner instanceof static) {
            return $winner;
        }
        return new static($winner);
    }
    /**
     * Modify the current instance to the average of a given instance (default now) and the current instance.
     *
     * @param \DateTimeInterface|null $other The instance to compare with.
     */
    public function average(?DateTimeInterface $other = null): static
    {
        $other ??= static::now($this->tz);
        return $this->add_seconds((int) ($this->diff_in_seconds($other, false) / 2));
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
     */
    public function is_yesterday(): bool
    {
        return $this->to_date_string() === static::yesterday($this->tz)->to_date_string();
    }
    /**
     * Determines if the instance is today
     */
    public function is_today(): bool
    {
        return $this->to_date_string() === static::now($this->tz)->to_date_string();
    }
    /**
     * Determines if the instance is tomorrow
     */
    public function is_tomorrow(): bool
    {
        return $this->to_date_string() === static::tomorrow($this->tz)->to_date_string();
    }
    /**
     * Determines if the instance is within the next week
     */
    public function is_next_week(): bool
    {
        return $this->format('W o') === static::now($this->tz)->add_weeks(1)->format('W o');
    }
    /**
     * Determines if the instance is within the last week
     */
    public function is_last_week(): bool
    {
        return $this->format('W o') === static::now($this->tz)->sub_weeks(1)->format('W o');
    }
    /**
     * Determines if the instance is within the next month
     */
    public function is_next_month(): bool
    {
        return $this->format('m Y') === static::now($this->tz)->add_months(1)->format('m Y');
    }
    /**
     * Determines if the instance is within the last month
     */
    public function is_last_month(): bool
    {
        return $this->format('m Y') === static::now($this->tz)->sub_months(1)->format('m Y');
    }
    /**
     * Determines if the instance is within the next year
     */
    public function is_next_year(): bool
    {
        return $this->year === static::now($this->tz)->add_years(1)->year;
    }
    /**
     * Determines if the instance is within the last year
     */
    public function is_last_year(): bool
    {
        return $this->year === static::now($this->tz)->sub_years(1)->year;
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
     */
    public function is_future(): bool
    {
        return $this->greater_than(static::now($this->tz));
    }
    /**
     * Determines if the instance is in the past, ie. less (before) than now
     */
    public function is_past(): bool
    {
        return $this->less_than(static::now($this->tz));
    }
    /**
     * Determines if the instance is a leap year
     */
    public function is_leap_year(): bool
    {
        return $this->format('L') === '1';
    }
    /**
     * Checks if the passed in date is the same day as the instance current day.
     *
     * @param \DateTimeInterface $other The instance to check against.
     */
    public function is_same_day(DateTimeInterface $other): bool
    {
        if (!$other instanceof static) {
            $other = new static($other);
        }
        return $this->to_date_string() === $other->to_date_string();
    }
    /**
     * Returns whether the passed in date is the same month and year.
     *
     * @param \DateTimeInterface $other The instance to check against.
     */
    public function is_same_month(DateTimeInterface $other): bool
    {
        return $this->format('Y-m') === $other->format('Y-m');
    }
    /**
     * Returns whether passed in date is the same year.
     *
     * @param \DateTimeInterface $other The instance to check against.
     */
    public function is_same_year(DateTimeInterface $other): bool
    {
        return $this->format('Y') === $other->format('Y');
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
     * Returns true if this object represents a date within the current week
     */
    public function is_this_week(): bool
    {
        return static::now($this->get_timezone())->format('W o') === $this->format('W o');
    }
    /**
     * Returns true if this object represents a date within the current month
     */
    public function is_this_month(): bool
    {
        return static::now($this->get_timezone())->format('m Y') === $this->format('m Y');
    }
    /**
     * Returns true if this object represents a date within the current year
     */
    public function is_this_year(): bool
    {
        return static::now($this->get_timezone())->format('Y') === $this->format('Y');
    }
    /**
     * Check if its the birthday. Compares the date/month values of the two dates.
     *
     * @param \DateTimeInterface|null $other The instance to compare with or null to use current day.
     */
    public function is_birthday(?DateTimeInterface $other = null): bool
    {
        $other ??= static::now($this->tz);
        return $this->format('md') === $other->format('md');
    }
    /**
     * Returns true this instance happened within the specified interval
     *
     * @param string|int $timeInterval the numeric value with space then time type.
     *    Example of valid types: 6 hours, 2 days, 1 minute.
     */
    public function was_within_last(string|int $time_interval): bool
    {
        $now = new static();
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
        $now = new static();
        $interval = $now->modify('+' . $time_interval);
        $this_time = $this->format('U');
        return $this_time <= $interval->format('U') && $this_time >= $now->format('U');
    }
    /**
     * Get the difference by the given interval using a filter callable
     *
     * @param \DateInterval $interval An interval to traverse by
     * @param callable $callback The callback to use for filtering.
     * @param \DateTimeInterface|null $other The instance to difference from.
     * @param bool $absolute Get the absolute of the difference
     * @param int $options DatePeriod options, {@see https://www.php.net/manual/en/class.dateperiod.php}
     */
    public function diff_filtered(DateInterval $interval, callable $callback, ?DateTimeInterface $other = null, bool $absolute = true, int $options = 0): int
    {
        $start = $this;
        $end = $other ?? static::now($this->tz);
        $inverse = false;
        if ($end < $start) {
            $start = $end;
            $end = $this;
            $inverse = true;
        }
        $period = new DatePeriod($start, $interval, $end, $options);
        $vals = array_filter(iterator_to_array($period), fn(DateTimeInterface $date) => $callback(static::instance($date)));
        $diff = count($vals);
        return $inverse && !$absolute ? -$diff : $diff;
    }
    /**
     * Get the difference in years
     *
     * @param \DateTimeInterface|null $other The instance to difference from.
     * @param bool $absolute Get the absolute of the difference
     */
    public function diff_in_years(?DateTimeInterface $other = null, bool $absolute = true): int
    {
        $diff = $this->diff($other ?? static::now($this->tz), $absolute);
        return $diff->invert ? -$diff->y : $diff->y;
    }
    /**
     * Get the difference in months
     *
     * @param \DateTimeInterface|null $other The instance to difference from.
     * @param bool $absolute Get the absolute of the difference
     */
    public function diff_in_months(?DateTimeInterface $other = null, bool $absolute = true): int
    {
        $diff = $this->diff($other ?? static::now($this->tz), $absolute);
        $months = $diff->y * Chronos::MONTHS_PER_YEAR + $diff->m;
        return $diff->invert ? -$months : $months;
    }
    /**
     * Get the difference in months ignoring the timezone. This means the months are calculated
     * in the specified timezone without converting to UTC first. This prevents the day from changing
     * which can change the month.
     *
     * For example, if comparing `2019-06-01 Asia/Tokyo` and `2019-10-01 Asia/Tokyo`,
     * the result would be 4 months instead of 3 when using normal `DateTime::diff()`.
     *
     * @param \DateTimeInterface|null $other The instance to difference from.
     * @param bool $absolute Get the absolute of the difference
     */
    public function diff_in_months_ignore_timezone(?DateTimeInterface $other = null, bool $absolute = true): int
    {
        $utc_tz = new DateTimeZone('UTC');
        $source = new static($this->format('Y-m-d H:i:s.u'), $utc_tz);
        $other ??= static::now($this->tz);
        $other = new static($other->format('Y-m-d H:i:s.u'), $utc_tz);
        return $source->diff_in_months($other, $absolute);
    }
    /**
     * Get the difference in weeks
     *
     * @param \DateTimeInterface|null $other The instance to difference from.
     * @param bool $absolute Get the absolute of the difference
     */
    public function diff_in_weeks(?DateTimeInterface $other = null, bool $absolute = true): int
    {
        return (int) ($this->diff_in_days($other, $absolute) / Chronos::DAYS_PER_WEEK);
    }
    /**
     * Get the difference in days
     *
     * @param \DateTimeInterface|null $other The instance to difference from.
     * @param bool $absolute Get the absolute of the difference
     */
    public function diff_in_days(?DateTimeInterface $other = null, bool $absolute = true): int
    {
        $diff = $this->diff($other ?? static::now($this->tz), $absolute);
        return $diff->invert ? -(int) $diff->days : (int) $diff->days;
    }
    /**
     * Get the difference in days using a filter callable
     *
     * @param callable $callback The callback to use for filtering.
     * @param \DateTimeInterface|null $other The instance to difference from.
     * @param bool $absolute Get the absolute of the difference
     * @param int $options DatePeriod options, {@see https://www.php.net/manual/en/class.dateperiod.php}
     */
    public function diff_in_days_filtered(callable $callback, ?DateTimeInterface $other = null, bool $absolute = true, int $options = 0): int
    {
        return $this->diff_filtered(new DateInterval('P1D'), $callback, $other, $absolute, $options);
    }
    /**
     * Get the difference in hours using a filter callable
     *
     * @param callable $callback The callback to use for filtering.
     * @param \DateTimeInterface|null $other The instance to difference from.
     * @param bool $absolute Get the absolute of the difference
     * @param int $options DatePeriod options, {@see https://www.php.net/manual/en/class.dateperiod.php}
     */
    public function diff_in_hours_filtered(callable $callback, ?DateTimeInterface $other = null, bool $absolute = true, int $options = 0): int
    {
        return $this->diff_filtered(new DateInterval('PT1H'), $callback, $other, $absolute, $options);
    }
    /**
     * Get the difference in weekdays
     *
     * @param \DateTimeInterface|null $other The instance to difference from.
     * @param bool $absolute Get the absolute of the difference
     * @param int $options DatePeriod options, {@see https://www.php.net/manual/en/class.dateperiod.php}
     */
    public function diff_in_weekdays(?DateTimeInterface $other = null, bool $absolute = true, int $options = 0): int
    {
        return $this->diff_in_days_filtered(fn(Chronos $date) => $date->is_weekday(), $other, $absolute, $options);
    }
    /**
     * Get the difference in weekend days using a filter
     *
     * @param \DateTimeInterface|null $other The instance to difference from.
     * @param bool $absolute Get the absolute of the difference
     * @param int $options DatePeriod options, {@see https://www.php.net/manual/en/class.dateperiod.php}
     */
    public function diff_in_weekend_days(?DateTimeInterface $other = null, bool $absolute = true, int $options = 0): int
    {
        return $this->diff_in_days_filtered(fn(Chronos $date) => $date->is_weekend(), $other, $absolute, $options);
    }
    /**
     * Get the difference in hours
     *
     * @param \DateTimeInterface|null $other The instance to difference from.
     * @param bool $absolute Get the absolute of the difference
     */
    public function diff_in_hours(?DateTimeInterface $other = null, bool $absolute = true): int
    {
        return (int) ($this->diff_in_seconds($other, $absolute) / Chronos::SECONDS_PER_MINUTE / Chronos::MINUTES_PER_HOUR);
    }
    /**
     * Get the difference in minutes
     *
     * @param \DateTimeInterface|null $other The instance to difference from.
     * @param bool $absolute Get the absolute of the difference
     */
    public function diff_in_minutes(?DateTimeInterface $other = null, bool $absolute = true): int
    {
        return (int) ($this->diff_in_seconds($other, $absolute) / Chronos::SECONDS_PER_MINUTE);
    }
    /**
     * Get the difference in seconds
     *
     * @param \DateTimeInterface|null $other The instance to difference from.
     * @param bool $absolute Get the absolute of the difference
     */
    public function diff_in_seconds(?DateTimeInterface $other = null, bool $absolute = true): int
    {
        $other ??= static::now($this->tz);
        $value = $other->get_timestamp() - $this->get_timestamp();
        return $absolute ? abs($value) : $value;
    }
    /**
     * The number of seconds since midnight.
     */
    public function seconds_since_midnight(): int
    {
        return $this->diff_in_seconds($this->start_of_day());
    }
    /**
     * The number of seconds until 23:59:59.
     */
    public function seconds_until_end_of_day(): int
    {
        return $this->diff_in_seconds($this->end_of_day());
    }
    /**
     * Convenience method for getting the remaining time from a given time.
     *
     * @param \DateTimeInterface $other The date to get the remaining time from.
     * @return \DateInterval|bool The DateInterval object representing the difference between the two dates or FALSE on failure.
     */
    public static function from_now(DateTimeInterface $other): DateInterval|bool
    {
        $time_now = new static();
        return $time_now->diff($other);
    }
    /**
     * Get the difference in a human readable format.
     *
     * When comparing a value in the past to default now:
     * 1 hour ago
     * 5 months ago
     *
     * When comparing a value in the future to default now:
     * 1 hour from now
     * 5 months from now
     *
     * When comparing a value in the past to another value:
     * 1 hour before
     * 5 months before
     *
     * When comparing a value in the future to another value:
     * 1 hour after
     * 5 months after
     *
     * @param \DateTimeInterface|null $other The datetime to compare with.
     * @param bool $absolute removes time difference modifiers ago, after, etc
     */
    public function diff_for_humans(?DateTimeInterface $other = null, bool $absolute = false): string
    {
        return static::diff_formatter()->diff_for_humans($this, $other, $absolute);
    }
    /**
     * Converts the time zone to UTC and returns a string in RFC7231 format.
     * This replaced the deprecated and broken ``DATE_RFC7231`` formatting constant.
     */
    public function to_rfc7231string(): string
    {
        return $this->set_timezone('UTC')->format('D, d M Y H:i:s \G\M\T');
    }
    /**
     * Returns a DateTimeImmutable instance
     *
     * This method returns a PHP DateTimeImmutable without Chronos extensions.
     */
    public function to_native(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->format('Y-m-d H:i:s.u'), $this->get_timezone());
    }
    /**
     * Get a part of the object
     *
     * @param string $name The property name to read.
     * @return \DateTimeZone|string|float|int|bool The property value.
     * @throws \InvalidArgumentException
     */
    public function __get(string $name): string|float|int|bool|DateTimeZone
    {
        static $formats = ['year' => 'Y', 'yearIso' => 'o', 'month' => 'n', 'day' => 'j', 'hour' => 'G', 'minute' => 'i', 'second' => 's', 'micro' => 'u', 'microsecond' => 'u', 'dayOfWeek' => 'N', 'dayOfYear' => 'z', 'weekOfYear' => 'W', 'daysInMonth' => 't', 'timestamp' => 'U'];
        return match (true) {
            isset($formats[$name]) => (int) $this->format($formats[$name]),
            $name === 'dayOfWeekName' => $this->format('l'),
            $name === 'weekOfMonth' => (int) ceil($this->day / Chronos::DAYS_PER_WEEK),
            $name === 'age' => $this->diff_in_years(),
            $name === 'quarter' => (int) ceil($this->month / 3),
            $name === 'half' => $this->month <= 6 ? 1 : 2,
            $name === 'offset' => $this->get_offset(),
            $name === 'offsetHours' => $this->get_offset() / Chronos::SECONDS_PER_MINUTE / Chronos::MINUTES_PER_HOUR,
            $name === 'dst' => $this->format('I') === '1',
            $name === 'local' => $this->offset === $this->set_timezone(date_default_timezone_get())->offset,
            $name === 'utc' => $this->offset === 0,
            $name === 'timezone' || $name === 'tz' => $this->get_timezone(),
            $name === 'timezoneName' || $name === 'tzName' => $this->get_timezone()->get_name(),
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
        $timezone = $this->get_timezone();
        return ['hasFixedNow' => static::has_test_now(), 'time' => $this->format('Y-m-d H:i:s.u'), 'timezone' => $timezone->get_name()];
    }
}