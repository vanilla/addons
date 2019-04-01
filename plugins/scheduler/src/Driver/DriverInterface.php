<?php
/**
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license MIT
 */

namespace Vanilla\Scheduler\Driver;

use Vanilla\Scheduler\Job\JobExecutionStatus;
use Vanilla\Scheduler\Job\JobInterface;

/**
 * Interface DriverInterface
 *
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 */
interface DriverInterface {

    /**
     * Receive a Job
     *
     * @param JobInterface $job
     * @return DriverSlipInterface
     */
    public function receive(JobInterface $job): DriverSlipInterface;

    /**
     * Execute a Driver job
     *
     * @param \Vanilla\Scheduler\Driver\DriverSlipInterface $driverSlip
     *
     * @return \Vanilla\Scheduler\Job\JobExecutionStatus
     */
    public function execute(DriverSlipInterface $driverSlip): JobExecutionStatus;
}
