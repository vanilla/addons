<?php
/**
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license MIT
 */

declare(strict_types=1);

/**
 * Class PluginTest.
 *
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 */
final class PluginTest extends \PHPUnit\Framework\TestCase {

    public function testContainerInitHandler() {
        /** @var $container \Garden\Container\Container */
        $container = new Garden\Container\Container();

        $container
            ->setInstance(\Interop\Container\ContainerInterface::class, $container)
            //
            ->rule(\Psr\Log\LoggerInterface::class)
            ->setClass(\Vanilla\Logger::class)
            ->setShared(true)
            // Not really needed
            ->rule(\Garden\EventManager::class)
            ->setShared(true);

        $this->assertTrue($container != null);

        $plugin = new SchedulerPlugin();
        $plugin->container_init_handler($container);

        /* @var $dummyScheduler \Vanilla\Scheduler\SchedulerInterface */
        $dummyScheduler = $container->get(\Vanilla\Scheduler\SchedulerInterface::class);

        $this->assertTrue(get_class($dummyScheduler) == \Vanilla\Scheduler\DummyScheduler::class);
        $this->assertEquals(SchedulerPlugin::DISPATCH_EVENT, $dummyScheduler->getDispatchEventName());
        $this->assertEquals(SchedulerPlugin::DISPATCHED_EVENT, $dummyScheduler->getDispatchedEventName());
        $this->assertEquals(1, count($dummyScheduler->getDrivers()));
    }
}
