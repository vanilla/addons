<?php
/**
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license MIT
 */

namespace Vanilla\Scheduler;

use Vanilla\Scheduler\Job\JobExecutionStatus;

/**
 * Interface TrackingSlipInterface
 *
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 */
interface TrackingSlipInterface {

    /**
     * Get the job Id
     *
     * @return string The job Id
     */
    public function getId(): string;

    /**
     * Get the job status
     */
    public function getStatus(): JobExecutionStatus;


    /**
     * @return array
     */
    public function getExtendedStatus(): array;
}
