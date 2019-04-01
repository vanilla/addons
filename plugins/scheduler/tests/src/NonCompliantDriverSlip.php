<?php
/**
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license MIT
 */

namespace Vanilla\Scheduler\Test;

use Vanilla\Scheduler\Driver\DriverSlipInterface;
use Vanilla\Scheduler\Job\JobExecutionStatus;

/**
 * Class NonCompliantDriverSlip.
 *
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 */
class NonCompliantDriverSlip implements DriverSlipInterface {

    public function execute(): JobExecutionStatus {
        return JobExecutionStatus::failed();
    }

    public function getId(): string {
        return "null";
    }

    public function getStatus(): JobExecutionStatus {
        return JobExecutionStatus::failed();
    }

    public function getExtendedStatus(): array {
        return ['status' => $this->getStatus()];
    }

    public function setStackExecutionFailed(string $msg): bool {
        return false;
    }
}
