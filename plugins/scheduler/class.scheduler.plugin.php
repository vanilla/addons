<?php

/**
 * Class SchedulerPlugin.
 */
class SchedulerPlugin extends \Gdn_Plugin {

    const DISPATCH_EVENT = 'SchedulerDispatch';
    const DISPATCHED_EVENT = 'SchedulerDispatched';

    /**
     * Inject scheduler into the container.
     * Add default LocalJobDriver.
     *
     * @param \Garden\Container\Container $container
     */
    public function container_init_handler(\Garden\Container\Container $container) {
        $container
            ->rule(\Vanilla\Scheduler\SchedulerInterface::class)
            ->setClass(\Vanilla\Scheduler\DummyScheduler::class)
            ->addCall('addDriver', [
                \Vanilla\Scheduler\Job\LocalJobInterface::class,
                \Vanilla\Scheduler\Driver\LocalDriver::class,
            ])
            ->addCall('setDispatchEventName', [self::DISPATCH_EVENT])
            ->addCall('setDispatchedEventName', [self::DISPATCHED_EVENT])
            ->setShared(true)
        ;
    }
}
