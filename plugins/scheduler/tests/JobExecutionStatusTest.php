<?php
/**
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license MIT
 */

declare(strict_types=1);

/**
 * Class JobExecutionStatusTest.
 *
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 */
final class JobExecutionStatusTest extends \PHPUnit\Framework\TestCase {

    public function testAbandoned() {
        $this->assertTrue(\Vanilla\Scheduler\Job\JobExecutionStatus::abandoned()->is(\Vanilla\Scheduler\Job\JobExecutionStatus::abandoned()));
    }

    public function testComplete() {
        $this->assertTrue(\Vanilla\Scheduler\Job\JobExecutionStatus::complete()->is(\Vanilla\Scheduler\Job\JobExecutionStatus::complete()));
    }

    public function testError() {
        $this->assertTrue(\Vanilla\Scheduler\Job\JobExecutionStatus::error()->is(\Vanilla\Scheduler\Job\JobExecutionStatus::error()));
    }

    public function testFailed() {
        $this->assertTrue(\Vanilla\Scheduler\Job\JobExecutionStatus::failed()->is(\Vanilla\Scheduler\Job\JobExecutionStatus::failed()));
    }

    public function testInvalid() {
        $this->assertTrue(\Vanilla\Scheduler\Job\JobExecutionStatus::invalid()->is(\Vanilla\Scheduler\Job\JobExecutionStatus::invalid()));
    }

    public function testMismatch() {
        $this->assertTrue(\Vanilla\Scheduler\Job\JobExecutionStatus::mismatch()->is(\Vanilla\Scheduler\Job\JobExecutionStatus::mismatch()));
    }

    public function testProgress() {
        $this->assertTrue(\Vanilla\Scheduler\Job\JobExecutionStatus::progress()->is(\Vanilla\Scheduler\Job\JobExecutionStatus::progress()));
    }

    public function testReceived() {
        $this->assertTrue(\Vanilla\Scheduler\Job\JobExecutionStatus::received()->is(\Vanilla\Scheduler\Job\JobExecutionStatus::received()));
    }

    public function testRetry() {
        $this->assertTrue(\Vanilla\Scheduler\Job\JobExecutionStatus::retry()->is(\Vanilla\Scheduler\Job\JobExecutionStatus::retry()));
    }

    public function testStackExecutionError() {
        $this->assertTrue(\Vanilla\Scheduler\Job\JobExecutionStatus::stackExecutionError()->is(\Vanilla\Scheduler\Job\JobExecutionStatus::stackExecutionError()));
    }

    public function testLooseStatus() {
        $status = \Vanilla\Scheduler\Job\JobExecutionStatus::retry()->getStatus();
        $this->assertTrue(\Vanilla\Scheduler\Job\JobExecutionStatus::looseStatus($status)->is(\Vanilla\Scheduler\Job\JobExecutionStatus::retry()));
    }

    public function testLooseStatusExpectFail() {
        $status = \Vanilla\Scheduler\Job\JobExecutionStatus::retry()->getStatus();
        $this->assertFalse(\Vanilla\Scheduler\Job\JobExecutionStatus::looseStatus($status)->is(\Vanilla\Scheduler\Job\JobExecutionStatus::complete()));
    }
}
