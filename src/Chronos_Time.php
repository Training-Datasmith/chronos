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

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use Stringable;
/**
 * @phpstan-consistent-constructor
 */
class Chronos_Time implements Stringable
{
    /**
     * @var int
     */
    protected const TICKS_PER_MICROSECOND = 1;
    /**
     * @var int
     */
    protected const TICKS_PER_SECOND = 1000000;
    /**
     * @var int
     */
    protected const TICKS_PER_MINUTE = self::TICKS_PER_SECOND * 60;
    /**
     * @var int
     */
    protected const TICKS_PER_HOUR = self::TICKS_PER_MINUTE * 60;
    /**
     * @var int
     */
    protected const TICKS_PER_DAY = self::TICKS_PER_HOUR * 24;
    /**
     * Default format to use for __toString method.
     *
     * @var string
     */
    public const DEFAULT_TO_STRING_FORMAT = 'H:i:s';
    /**
     * Format to use for __toString method.
     */
    protected static string $to_string_format = self::DEFAULT_TO_STRING_FORMAT;
    protected int $ticks;
    /**
     * Copies time from onther instance or from time string in the format HH[:.]mm or HH[:.]mm[:.]ss.u.
     *
     * Defaults to server time.
     *
     * @param \Cake\Chronos\ChronosTime|\DateTimeInterface|string|null $time Time
     * @param \DateTimeZone|string|null $timezone The timezone to use for now
     */
    public function __construct(Chronos_Time|DateTimeInterface|string|null $time = null, DateTimeZone|string|null $timezone = null)
    {
        if ($time === null) {
            $time = Chronos::get_test_now() ?? Chronos::now();
            if ($timezone !== null) {
                $time = $time->set_timezone($timezone);
            }
            $this->ticks = static::parse_string($time->format('H:i:s.u'));
        } elseif (is_string($time)) {
            $this->ticks = static::parse_string($time);
        } elseif ($time instanceof Chronos_Time) {
            $this->ticks = $time->ticks;
        } else {
            $this->ticks = static::parse_string($time->format('H:i:s.u'));
        }
    }
    /**
     * Copies time from onther instance or from string in the format HH[:.]mm or HH[:.]mm[:.]ss.u
     *
     * Defaults to server time.
     *
     * @param \Cake\Chronos\ChronosTime|\DateTimeInterface|string $time Time
     * @param \DateTimeZone|string|null $timezone The timezone to use for now
     */
    public static function parse(Chronos_Time|DateTimeInterface|string|null $time = null, DateTimeZone|string|null $timezone = null): static
    {
        return new static($time, $timezone);
    }
    /**
     * @param string $time Time string in the format HH[:.]mm or HH[:.]mm[:.]ss.u
     */
    protected static function parse_string(string $time): int
    {
        if (!preg_match('/^\s*(\d{1,2})[:.](\d{1,2})(?|[:.](\d{1,2})[.](\d+)|[:.](\d{1,2}))?\s*$/', $time, $matches)) {
            throw new InvalidArgumentException(sprintf('Time string `%s` is not in expected format `HH[:.]mm` or `HH[:.]mm[:.]ss.u`.', $time));
        }
        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];
        $seconds = (int) ($matches[3] ?? 0);
        $microseconds = (int) substr($matches[4] ?? '', 0, 6);
        if ($hours > 24 || $minutes > 59 || $seconds > 59 || $microseconds > 999999) {
            throw new InvalidArgumentException(sprintf('Time string `%s` contains invalid values.', $time));
        }
        $ticks = $hours * self::TICKS_PER_HOUR;
        $ticks += $minutes * self::TICKS_PER_MINUTE;
        $ticks += $seconds * self::TICKS_PER_SECOND;
        $ticks += $microseconds * self::TICKS_PER_MICROSECOND;
        return $ticks % self::TICKS_PER_DAY;
    }
    /**
     * Returns instance set to server time.
     *
     * @param \DateTimeZone|string|null $timezone The timezone to use for now
     */
    public static function now(DateTimeZone|string|null $timezone = null): static
    {
        return new static(null, $timezone);
    }
    /**
     * Returns instance set to midnight.
     */
    public static function midnight(): static
    {
        return new static('00:00:00');
    }
    /**
     * Returns instance set to noon.
     */
    public static function noon(): static
    {
        return new static('12:00:00');
    }
    /**
     * Returns instance set to end of day - either
     * 23:59:59 or 23:59:59.999999 if `$microseconds` is true
     *
     * @param bool $microseconds Whether to set microseconds or not
     */
    public static function end_of_day(bool $microseconds = false): static
    {
        if ($microseconds) {
            return new static('23:59:59.999999');
        }
        return new static('23:59:59');
    }
    /**
     * Returns clock microseconds.
     */
    public function get_microseconds(): int
    {
        return intdiv($this->ticks % self::TICKS_PER_SECOND, self::TICKS_PER_MICROSECOND);
    }
    /**
     * Sets clock microseconds.
     *
     * @param int $microseconds Clock microseconds
     */
    public function set_microseconds(int $microseconds): static
    {
        $base_ticks = $this->ticks - $this->ticks % self::TICKS_PER_SECOND;
        $new_ticks = static::mod($base_ticks + $microseconds * self::TICKS_PER_MICROSECOND, self::TICKS_PER_DAY);
        $clone = clone $this;
        $clone->ticks = $new_ticks;
        return $clone;
    }
    /**
     * Return clock seconds.
     */
    public function get_seconds(): int
    {
        $seconds_ticks = $this->ticks % self::TICKS_PER_MINUTE - $this->ticks % self::TICKS_PER_SECOND;
        return intdiv($seconds_ticks, self::TICKS_PER_SECOND);
    }
    /**
     * Set clock seconds.
     *
     * @param int $seconds Clock seconds
     */
    public function set_seconds(int $seconds): static
    {
        $base_ticks = $this->ticks - ($this->ticks % self::TICKS_PER_MINUTE - $this->ticks % self::TICKS_PER_SECOND);
        $new_ticks = static::mod($base_ticks + $seconds * self::TICKS_PER_SECOND, self::TICKS_PER_DAY);
        $clone = clone $this;
        $clone->ticks = $new_ticks;
        return $clone;
    }
    /**
     * Returns clock minutes.
     */
    public function get_minutes(): int
    {
        $minutes_ticks = $this->ticks % self::TICKS_PER_HOUR - $this->ticks % self::TICKS_PER_MINUTE;
        return intdiv($minutes_ticks, self::TICKS_PER_MINUTE);
    }
    /**
     * Set clock minutes.
     *
     * @param int $minutes Clock minutes
     */
    public function set_minutes(int $minutes): static
    {
        $base_ticks = $this->ticks - ($this->ticks % self::TICKS_PER_HOUR - $this->ticks % self::TICKS_PER_MINUTE);
        $new_ticks = static::mod($base_ticks + $minutes * self::TICKS_PER_MINUTE, self::TICKS_PER_DAY);
        $clone = clone $this;
        $clone->ticks = $new_ticks;
        return $clone;
    }
    /**
     * Returns clock hours.
     */
    public function get_hours(): int
    {
        $hours_in_ticks = $this->ticks - $this->ticks % self::TICKS_PER_HOUR;
        return intdiv($hours_in_ticks, self::TICKS_PER_HOUR);
    }
    /**
     * Set clock hours.
     *
     * @param int $hours Clock hours
     */
    public function set_hours(int $hours): static
    {
        $base_ticks = $this->ticks - ($this->ticks - $this->ticks % self::TICKS_PER_HOUR);
        $new_ticks = static::mod($base_ticks + $hours * self::TICKS_PER_HOUR, self::TICKS_PER_DAY);
        $clone = clone $this;
        $clone->ticks = $new_ticks;
        return $clone;
    }
    /**
     * Sets clock time.
     *
     * @param int $hours Clock hours
     * @param int $minutes Clock minutes
     * @param int $seconds Clock seconds
     * @param int $microseconds Clock microseconds
     */
    public function set_time(int $hours = 0, int $minutes = 0, int $seconds = 0, int $microseconds = 0): static
    {
        $ticks = $hours * self::TICKS_PER_HOUR + $minutes * self::TICKS_PER_MINUTE + $seconds * self::TICKS_PER_SECOND + $microseconds * self::TICKS_PER_MICROSECOND;
        $ticks = static::mod($ticks, self::TICKS_PER_DAY);
        $clone = clone $this;
        $clone->ticks = $ticks;
        return $clone;
    }
    /**
     * @param int $a Left side
     * @param int $a Right side
     */
    protected static function mod(int $a, int $b): int
    {
        if ($a < 0) {
            return $a % $b + $b;
        }
        return $a % $b;
    }
    /**
     * Formats string using the same syntax as `DateTimeImmutable::format()`.
     *
     * As this uses DateTimeImmutable::format() to format the string, non-time formatters
     * will still be interpreted. Be sure to escape those characters first.
     *
     * @param string $format Format string
     */
    public function format(string $format): string
    {
        return $this->to_date_time_immutable()->format($format);
    }
    /**
     * Reset the format used to the default when converting to a string
     */
    public static function reset_to_string_format(): void
    {
        static::set_to_string_format(static::DEFAULT_TO_STRING_FORMAT);
    }
    /**
     * Set the default format used when converting to a string
     *
     * @param string $format The format to use in future __toString() calls.
     */
    public static function set_to_string_format(string $format): void
    {
        static::$to_string_format = $format;
    }
    /**
     * Format the instance as a string using the set format
     */
    public function __toString(): string
    {
        return $this->format(static::$to_string_format);
    }
    /**
     * Returns whether time is equal to target time.
     *
     * @param \Cake\Chronos\ChronosTime $target Target time
     */
    public function equals(Chronos_Time $target): bool
    {
        return $this->ticks === $target->ticks;
    }
    /**
     * Returns whether time is greater than target time.
     *
     * @param \Cake\Chronos\ChronosTime $target Target time
     */
    public function greater_than(Chronos_Time $target): bool
    {
        return $this->ticks > $target->ticks;
    }
    /**
     * Returns whether time is greater than or equal to target time.
     *
     * @param \Cake\Chronos\ChronosTime $target Target time
     */
    public function greater_than_or_equals(Chronos_Time $target): bool
    {
        return $this->ticks >= $target->ticks;
    }
    /**
     * Returns whether time is less than target time.
     *
     * @param \Cake\Chronos\ChronosTime $target Target time
     */
    public function less_than(Chronos_Time $target): bool
    {
        return $this->ticks < $target->ticks;
    }
    /**
     * Returns whether time is less than or equal to target time.
     *
     * @param \Cake\Chronos\ChronosTime $target Target time
     */
    public function less_than_or_equals(Chronos_Time $target): bool
    {
        return $this->ticks <= $target->ticks;
    }
    /**
     * Returns whether time is between time range.
     *
     * @param \Cake\Chronos\ChronosTime $start Start of target range
     * @param \Cake\Chronos\ChronosTime $end End of target range
     * @param bool $equals Whether to include the beginning and end of range
     */
    public function between(Chronos_Time $start, Chronos_Time $end, bool $equals = true): bool
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
     * Returns an `DateTimeImmutable` instance set to this clock time.
     *
     * @param \DateTimeZone|string|null $timezone Time zone the DateTimeImmutable instance will be in
     */
    public function to_date_time_immutable(DateTimeZone|string|null $timezone = null): DateTimeImmutable
    {
        $timezone = is_string($timezone) ? new DateTimeZone($timezone) : $timezone;
        return (new DateTimeImmutable(timezone: $timezone))->set_time($this->get_hours(), $this->get_minutes(), $this->get_seconds(), $this->get_microseconds());
    }
    /**
     * Returns an `DateTimeImmutable` instance set to this clock time.
     *
     * Alias of `toDateTimeImmutable()`.
     *
     * @param \DateTimeZone|string|null $timezone Time zone the DateTimeImmutable instance will be in
     */
    public function to_native(DateTimeZone|string|null $timezone = null): DateTimeImmutable
    {
        return $this->to_date_time_immutable($timezone);
    }
}