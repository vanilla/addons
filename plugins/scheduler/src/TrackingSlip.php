<?php
/**
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license MIT
 */

namespace Vanilla\Scheduler;

use Vanilla\Scheduler\Driver\DriverSlipInterface;
use Vanilla\Scheduler\Job\JobExecutionStatus;

/**
 * Class TrackingSlip
 *
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 */
class TrackingSlip implements TrackingSlipInterface {

    /**
     * @var string
     */
    protected $jobInterface;

    /**
     * @var DriverSlipInterface
     */
    protected $driverSlip;

    /**
     * TrackingSlip constructor.
     *
     * @param string $jobInterface
     * @param DriverSlipInterface $driverSlip
     */
    public function __construct(string $jobInterface, DriverSlipInterface $driverSlip) {
        $this->jobInterface = $jobInterface;
        $this->driverSlip = $driverSlip;
    }

    /**
     * Get Id
     *
     * @return string
     */
    public function getId(): string {
        $class = $this->jobInterface;
        $id = $this->driverSlip->getId();
        return $class."-".$id;
    }

    /**
     * Get Status
     *
     * @return \Vanilla\Scheduler\Job\JobExecutionStatus
     */
    public function getStatus(): JobExecutionStatus {
        return $this->driverSlip->getStatus();
    }

    /**
     * Get Driver Slip
     *
     * @return \Vanilla\Scheduler\Driver\DriverSlipInterface
     */
    public function getDriverSlip(): DriverSlipInterface {
        return $this->driverSlip;
    }

    /**
     * Get JobInterface name
     * @return string
     */
    public function getJobInterface() {
        return $this->jobInterface;
    }

    /**
     * Get Extended Status
     *
     * @return array
     */
    public function getExtendedStatus(): array {
        return $this->driverSlip->getExtendedStatus();
    }
}
