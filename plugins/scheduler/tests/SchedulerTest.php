<?php
/**
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license MIT
 */

declare(strict_types=1);

/**
 * Class SchedulerTest
 *
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 */
final class SchedulerTest extends \PHPUnit\Framework\TestCase {

    const DISPATCH_EVENT = 'dispatchEvent';
    const DISPATCHED_EVENT = 'dispatchedEvent';

    /**
     * @return \Garden\Container\Container
     * @throws \Garden\Container\ContainerException
     * @throws \Garden\Container\NotFoundException
     */
    protected function getNewContainer() {
        $container = new Garden\Container\Container();
        $container
            ->setInstance(\Interop\Container\ContainerInterface::class, $container)
            //
            ->rule(\Psr\Log\LoggerInterface::class)
            ->setClass(\Vanilla\Logger::class)
            ->setShared(true)
            // Not really needed
            ->rule(\Garden\EventManager::class)
            ->setShared(true)
            ->rule(\Vanilla\Scheduler\SchedulerInterface::class)
            ->setClass(\Vanilla\Scheduler\DummyScheduler::class)
            ->setShared(true)
        ;

        $this->assertTrue($container != null);

        $dummyScheduler = $container->get(\Vanilla\Scheduler\SchedulerInterface::class);
        $this->assertTrue(get_class($dummyScheduler) == \Vanilla\Scheduler\DummyScheduler::class);

        $bool = $dummyScheduler->addDriver(\Vanilla\Scheduler\Job\LocalJobInterface::class, \Vanilla\Scheduler\Driver\LocalDriver::class);
        $this->assertTrue($bool);

        $bool = $dummyScheduler->setDispatchEventName(self::DISPATCH_EVENT);
        $this->assertTrue($bool);

        $bool = $dummyScheduler->setDispatchedEventName(self::DISPATCHED_EVENT);
        $this->assertTrue($bool);

        return $container;
    }

    public function testGetFullyConfiguredSchedulerFromContainerExpectPass() {
        /* @var $dummyScheduler \Vanilla\Scheduler\SchedulerInterface */
        $dummyScheduler = $this->getNewContainer()->get(\Vanilla\Scheduler\SchedulerInterface::class);

        $this->assertTrue(get_class($dummyScheduler) == \Vanilla\Scheduler\DummyScheduler::class);
        $this->assertEquals(self::DISPATCH_EVENT, $dummyScheduler->getDispatchEventName());
        $this->assertEquals(self::DISPATCHED_EVENT, $dummyScheduler->getDispatchedEventName());
        $this->assertEquals(1, count($dummyScheduler->getDrivers()));
    }

    public function testAddUnknownDriverExpectException() {
        /* @var $dummyScheduler \Vanilla\Scheduler\SchedulerInterface */
        $dummyScheduler = $this->getNewContainer()->get(\Vanilla\Scheduler\SchedulerInterface::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The class `Vanilla\Scheduler\Test\UnknownDriver` cannot be found.');

        $dummyScheduler->addDriver(\Vanilla\Scheduler\Job\LocalJobInterface::class, \Vanilla\Scheduler\Test\UnknownDriver::class);
    }

    public function testAddNonCompliantDriverExpectException() {
        /* @var $dummyScheduler \Vanilla\Scheduler\SchedulerInterface */
        $dummyScheduler = $this->getNewContainer()->get(\Vanilla\Scheduler\SchedulerInterface::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("The class `Vanilla\Scheduler\Test\NonCompliantDriver` doesn't implement DriverInterface.");

        $dummyScheduler->addDriver(\Vanilla\Scheduler\Job\LocalJobInterface::class, \Vanilla\Scheduler\Test\NonCompliantDriver::class);
    }

    public function testAddEchoJobExpectPass() {
        /* @var $dummyScheduler \Vanilla\Scheduler\SchedulerInterface */
        $dummyScheduler = $this->getNewContainer()->get(\Vanilla\Scheduler\SchedulerInterface::class);

        $trackingSlip = $dummyScheduler->addJob(\Vanilla\Scheduler\Test\EchoJob::class);

        $this->assertNotNull($trackingSlip);
        $this->assertTrue($trackingSlip->getStatus()->is(\Vanilla\Scheduler\Job\JobExecutionStatus::received()));
    }

    public function testAddUnknownJobExpectException() {
        /* @var $dummyScheduler \Vanilla\Scheduler\SchedulerInterface */
        $dummyScheduler = $this->getNewContainer()->get(\Vanilla\Scheduler\SchedulerInterface::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The class `Vanilla\Scheduler\Test\UnknownJob` cannot be found.');

        $dummyScheduler->addJob(\Vanilla\Scheduler\Test\UnknownJob::class);
    }

    public function testAddNonCompliantJobExpectException() {
        /* @var $dummyScheduler \Vanilla\Scheduler\SchedulerInterface */
        $dummyScheduler = $this->getNewContainer()->get(\Vanilla\Scheduler\SchedulerInterface::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("The job class `Vanilla\Scheduler\Test\NonCompliantJob` doesn't implement JobInterface.");

        $dummyScheduler->addJob(\Vanilla\Scheduler\Test\NonCompliantJob::class);
    }

    public function testAddNonDroveJobExpectException() {
        /* @var $dummyScheduler \Vanilla\Scheduler\SchedulerInterface */
        $dummyScheduler = $this->getNewContainer()->get(\Vanilla\Scheduler\SchedulerInterface::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("DummyScheduler couldn't find an appropriate driver to handle the job class `Vanilla\Scheduler\Test\NonDroveJob`.");

        $dummyScheduler->addJob(\Vanilla\Scheduler\Test\NonDroveJob::class);
    }

    public function testDispatchWithNoJobExpectEmptyArray() {
        /** @var $eventManager \Garden\EventManager */
        $eventManager = $this->getNewContainer()->get(\Garden\EventManager::class);

        $eventManager->bind(self::DISPATCHED_EVENT, function ($trackingSlips) {
            $this->assertTrue(count($trackingSlips) == 0);
        });

        $eventManager->fire(self::DISPATCH_EVENT);
    }

    /**
     * @runInSeparateProcess
     */
    public function testDispatchedWithOneJobExpectOneSlip() {
        ob_start();

        /** @var $container \Garden\Container\Container */
        $container = $this->getNewContainer();

        /* @var $dummyScheduler \Vanilla\Scheduler\SchedulerInterface */
        $dummyScheduler = $container->get(\Vanilla\Scheduler\SchedulerInterface::class);

        $trackingSlip = $dummyScheduler->addJob(\Vanilla\Scheduler\Test\EchoJob::class);
        $this->assertNotNull($trackingSlip);
        $this->assertTrue($trackingSlip->getStatus()->is(\Vanilla\Scheduler\Job\JobExecutionStatus::received()));

        /** @var $eventManager \Garden\EventManager */
        $eventManager = $container->get(\Garden\EventManager::class);

        $eventManager->bind(self::DISPATCHED_EVENT, function ($trackingSlips) {
            /** @var $trackingSlips \Vanilla\Scheduler\TrackingSlip[] */
            $this->assertTrue(count($trackingSlips) == 1);
            $this->assertContains('localDriverId', $trackingSlips[0]->getId());
            $complete = \Vanilla\Scheduler\Job\JobExecutionStatus::complete();
            $this->assertTrue($trackingSlips[0]->getStatus()->is($complete));
            $this->assertTrue($trackingSlips[0]->getExtendedStatus()['status']->is($complete));
        });

        $eventManager->fire(self::DISPATCH_EVENT);
    }

    /**
     * @runInSeparateProcess
     */
    public function testDispatchedWithOneFailedJobExpectStackExecutionErrorStatus() {
        ob_start();

        /** @var $container \Garden\Container\Container */
        $container = $this->getNewContainer();

        /* @var $dummyScheduler \Vanilla\Scheduler\SchedulerInterface */
        $dummyScheduler = $container->get(\Vanilla\Scheduler\SchedulerInterface::class);

        $trackingSlip = $dummyScheduler->addJob(\Vanilla\Scheduler\Test\ThrowableEchoJob::class);
        $this->assertNotNull($trackingSlip);
        $this->assertTrue($trackingSlip->getStatus()->is(\Vanilla\Scheduler\Job\JobExecutionStatus::received()));

        /** @var $eventManager \Garden\EventManager */
        $eventManager = $container->get(\Garden\EventManager::class);

        $eventManager->bind(self::DISPATCHED_EVENT, function ($trackingSlips) {
            /** @var $trackingSlips \Vanilla\Scheduler\TrackingSlip[] */
            $this->assertTrue(count($trackingSlips) == 1);
            $this->assertContains('localDriverId', $trackingSlips[0]->getId());
            $stackExecutionError = \Vanilla\Scheduler\Job\JobExecutionStatus::stackExecutionError();
            $this->assertTrue($trackingSlips[0]->getStatus()->is($stackExecutionError));
            $this->assertTrue($trackingSlips[0]->getExtendedStatus()['status']->is($stackExecutionError));
            $this->assertNotNull($trackingSlips[0]->getExtendedStatus()['error']);
        });

        $eventManager->fire(self::DISPATCH_EVENT);
    }


    /**
     * @runInSeparateProcess
     */
    public function testTrackingSlipIsReferenceOfTrackingSlipsExpectPass() {
        ob_start();

        /** @var $container \Garden\Container\Container */
        $container = $this->getNewContainer();

        /* @var $dummyScheduler \Vanilla\Scheduler\SchedulerInterface */
        $dummyScheduler = $container->get(\Vanilla\Scheduler\SchedulerInterface::class);

        $trackingSlip = $dummyScheduler->addJob(\Vanilla\Scheduler\Test\EchoJob::class);

        /** @var $eventManager \Garden\EventManager */
        $eventManager = $container->get(\Garden\EventManager::class);

        $eventManager->bind(self::DISPATCHED_EVENT, function ($trackingSlips) use ($trackingSlip) {
            /** @var $trackingSlips \Vanilla\Scheduler\TrackingSlip[] */
            $this->assertTrue($trackingSlips[0] === $trackingSlip);
        });

        $eventManager->fire(self::DISPATCH_EVENT);
    }

    /**
     * @runInSeparateProcess
     */
    public function testDriverNotHandlingErrorExpectException() {
        ob_start();

        /** @var $container \Garden\Container\Container */
        $container = $this->getNewContainer();

        /* @var $dummyScheduler \Vanilla\Scheduler\SchedulerInterface */
        $dummyScheduler = $container->get(\Vanilla\Scheduler\SchedulerInterface::class);

        $bool = $dummyScheduler->addDriver(\Vanilla\Scheduler\Job\LocalJobInterface::class, \Vanilla\Scheduler\Test\ThrowableDriver::class);
        $this->assertTrue($bool);

        $trackingSlip = $dummyScheduler->addJob(\Vanilla\Scheduler\Test\ThrowableEchoJob::class);
        $this->assertNotNull($trackingSlip);
        $this->assertTrue($trackingSlip->getStatus()->is(\Vanilla\Scheduler\Job\JobExecutionStatus::received()));

        /** @var $eventManager \Garden\EventManager */
        $eventManager = $container->get(\Garden\EventManager::class);

        /** @var $eventManager \Garden\EventManager */
        $eventManager = $container->get(\Garden\EventManager::class);

        $eventManager->bind(self::DISPATCHED_EVENT, function ($trackingSlips) {
            /** @var $trackingSlips \Vanilla\Scheduler\TrackingSlip[] */
            $this->assertTrue(count($trackingSlips) == 1);
            $this->assertContains('localDriverId', $trackingSlips[0]->getId());
            $stackExecutionError = \Vanilla\Scheduler\Job\JobExecutionStatus::stackExecutionError();
            $this->assertTrue($trackingSlips[0]->getStatus()->is($stackExecutionError));
            $this->assertTrue($trackingSlips[0]->getExtendedStatus()['status']->is($stackExecutionError));
            $this->assertNotNull($trackingSlips[0]->getExtendedStatus()['error']);
        });

        $eventManager->fire(self::DISPATCH_EVENT);
    }
}
