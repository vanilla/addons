<?php
/**
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license MIT
 */

namespace Vanilla\Scheduler\Driver;

use Vanilla\Scheduler\TrackingSlipInterface;
use Vanilla\Scheduler\Job\JobExecutionStatus;

/**
 * Interface DriverJobInterface.
 *
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 */
interface DriverSlipInterface extends TrackingSlipInterface {

    /**
     * Execute
     *
     * @return JobExecutionStatus
     */
    public function execute(): JobExecutionStatus;

    /**
     * Set Stack Execution Problem
     *
     * @param string $msg
     * @return bool
     */
    public function setStackExecutionFailed(string $msg): bool;
}
