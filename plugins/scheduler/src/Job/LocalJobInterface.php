<?php
/**
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Vanilla\Scheduler\Job;

/**
 * Queue job interface.
 *
 * Interface for a runnable job payload.
 *
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 */
interface LocalJobInterface extends JobInterface {

    /**
     * Do what the Job needs to do
     */
    public function run(): JobExecutionStatus;
}
