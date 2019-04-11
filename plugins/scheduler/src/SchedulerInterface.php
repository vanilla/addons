<?php
/**
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Vanilla\Scheduler;

/**
 * Scheduler Interface
 *
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 */
interface SchedulerInterface {

    /**
     * Add driver
     *
     * @param string $driverType
     * @return bool
     */
    public function addDriver(string $driverType): bool;

    /**
     * Get driver
     *
     * @return array
     */
    public function getDrivers(): array;

    /**
     * Set dispatch event name
     *
     * @param string $eventName
     * @return bool
     */
    public function setDispatchEventName(string $eventName): bool;

    /**
     * Get dispatch event name
     *
     * @return string
     */
    public function getDispatchEventName(): string;

    /**
     * Set dispatched event name
     *
     * @param string $eventName
     * @return bool
     */
    public function setDispatchedEventName(string $eventName): bool;

    /**
     * Get dispatched event name
     *
     * @return string
     */
    public function getDispatchedEventName(): string;

    /**
     * Add a job to be scheduled
     *
     * @param string $jobType
     * @param array $message
     *
     * @return TrackingSlipInterface
     */
    public function addJob(string $jobType, $message = []): TrackingSlipInterface;
}
