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
use Vanilla\Scheduler\Job\LocalJobInterface;

/**
 * Class VoidDriver
 *
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 */
class VoidDriver implements DriverInterface {

    public function receive(JobInterface $job): DriverSlipInterface {
        return new LocalDriverSlip($job);
    }

    public function execute(DriverSlipInterface $driverSlip): JobExecutionStatus {
        return JobExecutionStatus::complete();
    }

    public function getSupportedInterfaces(): array {
        return [];
    }
}
