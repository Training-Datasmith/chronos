<?php
declare(strict_types=1);

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

namespace Cake\Chronos\Test\TestCase\DateTime;

use Cake\Chronos\Chronos;
use Cake\Chronos\Test\TestCase\TestCase;
use DateTimeZone;
use Generator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;

class CreateFromTimestampTest extends TestCase
{
    private const NON_FINITE_MESSAGE = 'Timestamp must be a finite number, `%s` given.';

    public function testCreateReturnsDatingInstance()
    {
        $d = Chronos::createFromTimestamp(Chronos::create(1975, 5, 21, 22, 32, 5)->timestamp);
        $this->assertDateTime($d, 1975, 5, 22, 2, 32, 5);
        $this->assertSame('+00:00', $d->tzName);
    }

    public function testCreateFromTimestampUsesUTC()
    {
        $d = Chronos::createFromTimestamp(0);

        $this->assertSame(1970, $d->year);
        $this->assertSame(0, $d->offset);
        $this->assertSame('+00:00', $d->tzName);
    }

    public function testCreateFromTimestampWithDateTimeZone()
    {
        $d = Chronos::createFromTimestamp(0, new DateTimeZone('UTC'));
        $this->assertSame('UTC', $d->tzName);
        $this->assertDateTime($d, 1970, 1, 1, 0, 0, 0);
    }

    public function testCreateFromTimestampWithString()
    {
        $d = Chronos::createFromTimestamp(0, 'America/Toronto');
        // We know Toronto is -5 since no DST in Jan
        $this->assertSame(1969, $d->year);
        $this->assertSame(-5 * 3600, $d->offset);
        $this->assertSame('America/Toronto', $d->tzName);
    }

    /**
     * @return Generator<string, array{float, bool, DateTimeZone|string|null, string}>
     */
    public static function nonFiniteTimestampProvider(): Generator
    {
        yield 'neg, no test-now' => [-INF, false, null, '-INF'];
        yield 'pos, no test-now' => [INF, false, null, 'INF'];
        yield 'nan, no test-now' => [NAN, false, null, 'NAN'];

        yield 'neg, test-now' => [-INF, true, null, '-INF'];
        yield 'pos, test-now' => [INF, true, null, 'INF'];
        yield 'nan, test-now' => [NAN, true, null, 'NAN'];

        yield 'neg, zone string' => [-INF, true, 'UTC', '-INF'];
        yield 'pos, zone object' => [INF, true, new DateTimeZone('UTC'), 'INF'];
        yield 'nan, zone name' => [NAN, true, 'America/Toronto', 'NAN'];
    }

    #[DataProvider('nonFiniteTimestampProvider')]
    public function testCreateFromTimestampRejectsNonFinite(
        float $timestamp,
        bool $useTestNow,
        DateTimeZone|string|null $timezone,
        string $expectedInMessage,
    ): void {
        if ($useTestNow) {
            Chronos::setTestNow(Chronos::create(2024, 7, 1, 14, 30, 0, 0, 'UTC'));
        } else {
            Chronos::setTestNow(null);
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(self::NON_FINITE_MESSAGE, $expectedInMessage));

        if ($timezone === null) {
            Chronos::createFromTimestamp($timestamp);
        } else {
            Chronos::createFromTimestamp($timestamp, $timezone);
        }
    }

    public function testCreateFromTimestampAcceptsFractionalFivePointFive(): void
    {
        Chronos::setTestNow(null);
        $d = Chronos::createFromTimestamp(5.5);

        $this->assertSame('1970-01-01 00:00:05.500000', $d->format('Y-m-d H:i:s.u'));
        $this->assertSame(500000, $d->microsecond);
        $this->assertSame('+00:00', $d->tzName);
        $this->assertSame(5, $d->timestamp);
    }

    public function testCreateFromTimestampAcceptsFractionalOnePointTwoFive(): void
    {
        Chronos::setTestNow(null);
        $d = Chronos::createFromTimestamp(1.25);

        $this->assertSame('1970-01-01 00:00:01.250000', $d->format('Y-m-d H:i:s.u'));
        $this->assertSame(250000, $d->microsecond);
    }

    public function testCreateFromTimestampAcceptsZeroFloat(): void
    {
        Chronos::setTestNow(null);
        $d = Chronos::createFromTimestamp(0.0);

        $this->assertSame('1970-01-01 00:00:00.000000', $d->format('Y-m-d H:i:s.u'));
        $this->assertSame(0, $d->timestamp);
    }

    public function testCreateFromTimestampAcceptsNegativeFractionWithTestNow(): void
    {
        Chronos::setTestNow(Chronos::create(2024, 7, 1, 14, 30, 0, 0, 'UTC'));
        $d = Chronos::createFromTimestamp(-1.5);

        $this->assertSame('1969-12-31 23:59:58.500000', $d->format('Y-m-d H:i:s.u'));
        $this->assertSame(500000, $d->microsecond);
        $this->assertSame('+00:00', $d->tzName);
        $this->assertSame(0, $d->offset);
        $this->assertSame(-2, $d->getTimestamp());
    }

    public function testCreateFromTimestampAcceptsNegativeIntegerWithTestNow(): void
    {
        Chronos::setTestNow(Chronos::create(2024, 7, 1, 14, 30, 0, 0, 'UTC'));
        $d = Chronos::createFromTimestamp(-1000);

        $this->assertSame('1969-12-31 23:43:20.000000', $d->format('Y-m-d H:i:s.u'));
        $this->assertSame(0, $d->microsecond);
        $this->assertSame('+00:00', $d->tzName);
        $this->assertSame(0, $d->offset);
        $this->assertSame(-1000, $d->getTimestamp());
    }

    public function testCreateFromTimestampAbsoluteNegativeWithUtcAndTestNow(): void
    {
        Chronos::setTestNow(Chronos::create(2024, 7, 1, 14, 30, 0, 0, 'UTC'));
        $d = Chronos::createFromTimestamp(-1.5, 'UTC');

        $this->assertSame('1969-12-31 23:59:58.500000', $d->format('Y-m-d H:i:s.u'));
        $this->assertSame('UTC', $d->tzName);
        $this->assertSame(0, $d->offset);
        $this->assertSame(-2, $d->getTimestamp());
    }

    public function testRelativeStringsStillHonorTestNow(): void
    {
        Chronos::setTestNow(Chronos::create(2024, 7, 1, 14, 30, 0, 0, 'UTC'));

        $plusTwoDays = Chronos::parse('+2 days', 'UTC');
        $this->assertSame('2024-07-03 14:30:00', $plusTwoDays->format('Y-m-d H:i:s'));

        $minusOneHour = Chronos::parse('-1 hour', 'UTC');
        $this->assertSame('2024-07-01 13:30:00', $minusOneHour->format('Y-m-d H:i:s'));
    }

    public function testCreateFromTimestampAcceptsNegativeFractionWithoutTestNow(): void
    {
        Chronos::setTestNow(null);
        $d = Chronos::createFromTimestamp(-1.5);

        $this->assertSame('1969-12-31 23:59:58.500000', $d->format('Y-m-d H:i:s.u'));
        $this->assertSame(500000, $d->microsecond);
    }

    public function testCreateFromTimestampAcceptsFractionalWithTorontoZone(): void
    {
        Chronos::setTestNow(Chronos::create(2024, 7, 1, 14, 30, 0, 0, 'UTC'));
        $d = Chronos::createFromTimestamp(5.5, 'America/Toronto');

        $this->assertSame('1969-12-31 19:00:05.500000', $d->format('Y-m-d H:i:s.u'));
        $this->assertSame(500000, $d->microsecond);
        $this->assertSame('America/Toronto', $d->tzName);
        $this->assertSame(-5 * 3600, $d->offset);
    }
}
