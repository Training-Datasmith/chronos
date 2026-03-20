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

use DateTime;
/**
 * Provides string formatting methods for datetime instances.
 *
 * Expects implementing classes to define static::$toStringFormat
 *
 * @internal
 */
trait Formatting_Trait
{
    /**
     * Resets the __toString() format to ``DEFAULT_TO_STRING_FORMAT``.
     */
    public static function reset_to_string_format(): void
    {
        static::set_to_string_format(static::DEFAULT_TO_STRING_FORMAT);
    }
    /**
     * Sets the __toString() format.
     *
     * @param string $format See ``format()`` for accepted specifiers.
     */
    public static function set_to_string_format(string $format): void
    {
        static::$to_string_format = $format;
    }
    /**
     * Returns a formatted string specified by ``setToStringFormat()``
     * or the default ``DEFAULT_TO_STRING_FORMAT`` format.
     */
    public function __toString(): string
    {
        return $this->format(static::$to_string_format);
    }
    /**
     * Format the instance as date
     */
    public function to_date_string(): string
    {
        return $this->format('Y-m-d');
    }
    /**
     * Format the instance as a readable date
     */
    public function to_formatted_date_string(): string
    {
        return $this->format('M j, Y');
    }
    /**
     * Format the instance as time
     */
    public function to_time_string(): string
    {
        return $this->format('H:i:s');
    }
    /**
     * Format the instance as date and time
     */
    public function to_date_time_string(): string
    {
        return $this->format('Y-m-d H:i:s');
    }
    /**
     * Format the instance with day, date and time
     */
    public function to_day_date_time_string(): string
    {
        return $this->format('D, M j, Y g:i A');
    }
    /**
     * Format the instance as ATOM
     */
    public function to_atom_string(): string
    {
        return $this->format(DateTime::ATOM);
    }
    /**
     * Format the instance as COOKIE
     */
    public function to_cookie_string(): string
    {
        return $this->format(DateTime::COOKIE);
    }
    /**
     * Format the instance as ISO8601
     */
    public function to_iso8601string(): string
    {
        return $this->format(DateTime::ATOM);
    }
    /**
     * Format the instance as RFC822
     *
     * @link https://tools.ietf.org/html/rfc822
     */
    public function to_rfc822string(): string
    {
        return $this->format(DateTime::RFC822);
    }
    /**
     * Format the instance as RFC850
     *
     * @link https://tools.ietf.org/html/rfc850
     */
    public function to_rfc850string(): string
    {
        return $this->format(DateTime::RFC850);
    }
    /**
     * Format the instance as RFC1036
     *
     * @link https://tools.ietf.org/html/rfc1036
     */
    public function to_rfc1036string(): string
    {
        return $this->format(DateTime::RFC1036);
    }
    /**
     * Format the instance as RFC1123
     *
     * @link https://tools.ietf.org/html/rfc1123
     */
    public function to_rfc1123string(): string
    {
        return $this->format(DateTime::RFC1123);
    }
    /**
     * Format the instance as RFC2822
     *
     * @link https://tools.ietf.org/html/rfc2822
     */
    public function to_rfc2822string(): string
    {
        return $this->format(DateTime::RFC2822);
    }
    /**
     * Format the instance as RFC3339
     *
     * @link https://tools.ietf.org/html/rfc3339
     */
    public function to_rfc3339string(): string
    {
        return $this->format(DateTime::RFC3339);
    }
    /**
     * Format the instance as RSS
     */
    public function to_rss_string(): string
    {
        return $this->format(DateTime::RSS);
    }
    /**
     * Format the instance as W3C
     */
    public function to_w3c_string(): string
    {
        return $this->format(DateTime::W3C);
    }
    /**
     * Returns a UNIX timestamp.
     *
     * @return string UNIX timestamp
     */
    public function to_unix_string(): string
    {
        return $this->format('U');
    }
    /**
     * Returns the quarter
     *
     * Deprecated 3.3.0: The $range parameter is deprecated. Use toQuarterRange() for quarter ranges.
     *
     * @param bool $range Range.
     * @return array|int 1, 2, 3, or 4 quarter of year or array if $range true
     */
    public function to_quarter(bool $range = false): int|array
    {
        $quarter = (int) ceil((int) $this->format('m') / 3);
        if ($range === false) {
            return $quarter;
        }
        trigger_error('Using toQuarter() with `$range=true` is deprecated. Use `toQuarterRange()` instead.', E_USER_DEPRECATED);
        return $this->to_quarter_range();
    }
    /**
     * Returns the quarter range
     *
     * @return array{0: string, 1: string} Array with start and end date of quarter in Y-m-d format
     */
    public function to_quarter_range(): array
    {
        /** @var int<1, 4> $quarter */
        $quarter = (int) ceil((int) $this->format('m') / 3);
        $year = $this->format('Y');
        return match ($quarter) {
            1 => [$year . '-01-01', $year . '-03-31'],
            2 => [$year . '-04-01', $year . '-06-30'],
            3 => [$year . '-07-01', $year . '-09-30'],
            4 => [$year . '-10-01', $year . '-12-31'],
        };
    }
    /**
     * Returns ISO 8601 week number of year, weeks starting on Monday
     *
     * @return int ISO 8601 week number of year
     */
    public function to_week(): int
    {
        return (int) $this->format('W');
    }
}