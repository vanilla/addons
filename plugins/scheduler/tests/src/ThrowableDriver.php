<?php
/**
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license MIT
 */

namespace Vanilla\Scheduler\Test;

use Vanilla\Scheduler\Driver\DriverInterface;
use Vanilla\Scheduler\Driver\DriverSlipInterface;
use Vanilla\Scheduler\Driver\LocalDriverSlip;
use Vanilla\Scheduler\Job\JobExecutionStatus;
use Vanilla\Scheduler\Job\JobInterface;

/**
 * Class NonCompliantDriver.
 *
 * I look like a Driver, but not implementing the Concrete DriverInterface
 *
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 */
class ThrowableDriver implements DriverInterface {

    public function receive(JobInterface $job): DriverSlipInterface {
        return new LocalDriverSlip($job);
    }

    public function execute(DriverSlipInterface $driverSlip): JobExecutionStatus {
        nonExistentFunction();
    }
}
