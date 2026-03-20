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

use DateTimeInterface;
/**
 * Handles formatting differences in text.
 *
 * Provides a swappable component for other libraries to leverage.
 * when localizing or customizing the difference output.
 *
 * @internal
 */
class Difference_Formatter implements Difference_Formatter_Interface
{
    /**
     * The text translator object
     */
    protected Translator $translate;
    /**
     * Constructor.
     *
     * @param \Cake\Chronos\Translator|null $translate The text translator object.
     */
    public function __construct(?Translator $translate = null)
    {
        $this->translate = $translate ?: new Translator();
    }
    /**
     * @inheritDoc
     */
    public function diff_for_humans(Chronos_Date|DateTimeInterface $first, Chronos_Date|DateTimeInterface|null $second = null, bool $absolute = false): string
    {
        $is_now = $second === null;
        if ($second === null) {
            if ($first instanceof Chronos_Date) {
                $second = new Chronos_Date(Chronos::now());
            } else {
                $second = Chronos::now($first->get_timezone());
            }
        }
        assert($first instanceof Chronos_Date && $second instanceof Chronos_Date || $first instanceof DateTimeInterface && $second instanceof DateTimeInterface);
        $diff_interval = $first->diff($second);
        switch (true) {
            case $diff_interval->y > 0:
                $unit = 'year';
                $count = $diff_interval->y;
                break;
            case $diff_interval->m >= 2:
                $unit = 'month';
                $count = $diff_interval->m;
                break;
            case $diff_interval->days >= Chronos::DAYS_PER_WEEK * 3:
                $unit = 'week';
                $count = (int) ($diff_interval->days / Chronos::DAYS_PER_WEEK);
                break;
            case $diff_interval->d > 0:
                $unit = 'day';
                $count = $diff_interval->d;
                break;
            case $diff_interval->h > 0:
                $unit = 'hour';
                $count = $diff_interval->h;
                break;
            case $diff_interval->i > 0:
                $unit = 'minute';
                $count = $diff_interval->i;
                break;
            default:
                $count = $diff_interval->s;
                $unit = 'second';
                break;
        }
        $time = $this->translate->plural($unit, $count, ['count' => $count]);
        if ($absolute) {
            return $time;
        }
        $is_future = $diff_interval->invert === 1;
        $trans_id = $is_now ? $is_future ? 'from_now' : 'ago' : ($is_future ? 'after' : 'before');
        // Some langs have special pluralization for past and future tense.
        $try_key_exists = $unit . '_' . $trans_id;
        if ($this->translate->exists($try_key_exists)) {
            $time = $this->translate->plural($try_key_exists, $count, ['count' => $count]);
        }
        return $this->translate->singular($trans_id, ['time' => $time]);
    }
}