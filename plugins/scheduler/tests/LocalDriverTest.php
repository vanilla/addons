<?php
/**
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license MIT
 */

declare(strict_types=1);

/**
 * Class LocalDriverTest.
 *
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 */
final class LocalDriverTest extends \PHPUnit\Framework\TestCase {

    protected function getNewContainer() {
        $container = new \Garden\Container\Container();
        $container
            ->setInstance(\Interop\Container\ContainerInterface::class, $container)
            //
            ->rule(\Psr\Log\LoggerInterface::class)
            ->setClass(\Vanilla\Logger::class)
            ->setShared(true)
        ;

        return $container;
    }

    protected function getNewLocalDriver() {
        $container = new \Garden\Container\Container();
        $container
            ->setInstance(\Interop\Container\ContainerInterface::class, $container)
            //
            ->rule(\Psr\Log\LoggerInterface::class)
            ->setClass(\Vanilla\Logger::class)
            ->setShared(true)
        ;

        $localDriver = $container->get(\Vanilla\Scheduler\Driver\LocalDriver::class);

        return $localDriver;
    }

    public function testReceiveExpectPass() {
        /* @var $container \Garden\Container\Container */
        $container = $this->getNewContainer();

        /* @var $echoJob \Vanilla\Scheduler\Test\EchoJob */
        $echoJob = $container->get(Vanilla\Scheduler\Test\EchoJob::class);

        /* @var $localDriver \Vanilla\Scheduler\Driver\LocalDriver */
        $localDriver = $container->get(\Vanilla\Scheduler\Driver\LocalDriver::class);

        $driverSlip = $localDriver->receive($echoJob);
        $this->assertNotNull($driverSlip);
    }

    public function testReceiveExpectException() {
        /* @var $container \Garden\Container\Container */
        $container = $this->getNewContainer();

        /* @var $nonDroveJob \Vanilla\Scheduler\Test\NonDroveJob */
        $nonDroveJob = $container->get(Vanilla\Scheduler\Test\NonDroveJob::class);

        /* @var $localDriver \Vanilla\Scheduler\Driver\LocalDriver */
        $localDriver = $container->get(\Vanilla\Scheduler\Driver\LocalDriver::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The job class \'Vanilla\Scheduler\Test\NonDroveJob\' doesn\'t implement LocalJobInterface.');

        $localDriver->receive($nonDroveJob);
    }

    public function testExecuteExpectException() {
        /* @var $container \Garden\Container\Container */
        $container = $this->getNewContainer();

        /* @var $nonCompliantDriverSlip \Vanilla\Scheduler\Test\NonCompliantDriverSlip */
        $nonCompliantDriverSlip = new \Vanilla\Scheduler\Test\NonCompliantDriverSlip();

        /* @var $localDriver \Vanilla\Scheduler\Driver\LocalDriver */
        $localDriver = $container->get(\Vanilla\Scheduler\Driver\LocalDriver::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("The class `Vanilla\Scheduler\Test\NonCompliantDriverSlip` doesn't implement LocalDriverSlip.");

        $localDriver->execute($nonCompliantDriverSlip);
    }

}
