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

    public function test_StatusAbandoned_Expect_Pass() {
        $this->assertTrue(\Vanilla\Scheduler\Job\JobExecutionStatus::abandoned()->is(\Vanilla\Scheduler\Job\JobExecutionStatus::abandoned()));
    }

    public function test_StatusComplete_Expect_Pass() {
        $this->assertTrue(\Vanilla\Scheduler\Job\JobExecutionStatus::complete()->is(\Vanilla\Scheduler\Job\JobExecutionStatus::complete()));
    }

    public function test_StatusError_Expect_Pass() {
        $this->assertTrue(\Vanilla\Scheduler\Job\JobExecutionStatus::error()->is(\Vanilla\Scheduler\Job\JobExecutionStatus::error()));
    }

    public function test_StatusFailed_Expect_Pass() {
        $this->assertTrue(\Vanilla\Scheduler\Job\JobExecutionStatus::failed()->is(\Vanilla\Scheduler\Job\JobExecutionStatus::failed()));
    }

    public function test_StatusInvalid_Expect_Pass() {
        $this->assertTrue(\Vanilla\Scheduler\Job\JobExecutionStatus::invalid()->is(\Vanilla\Scheduler\Job\JobExecutionStatus::invalid()));
    }

    public function test_StatusMismatch_Expect_Pass() {
        $this->assertTrue(\Vanilla\Scheduler\Job\JobExecutionStatus::mismatch()->is(\Vanilla\Scheduler\Job\JobExecutionStatus::mismatch()));
    }

    public function test_StatusProgress_Expect_Pass() {
        $this->assertTrue(\Vanilla\Scheduler\Job\JobExecutionStatus::progress()->is(\Vanilla\Scheduler\Job\JobExecutionStatus::progress()));
    }

    public function test_StatusReceived_Expect_Pass() {
        $this->assertTrue(\Vanilla\Scheduler\Job\JobExecutionStatus::received()->is(\Vanilla\Scheduler\Job\JobExecutionStatus::received()));
    }

    public function test_StatusRetry_Expect_Pass() {
        $this->assertTrue(\Vanilla\Scheduler\Job\JobExecutionStatus::retry()->is(\Vanilla\Scheduler\Job\JobExecutionStatus::retry()));
    }

    public function test_StatusStackExecutionError_Expect_Pass() {
        $this->assertTrue(\Vanilla\Scheduler\Job\JobExecutionStatus::stackExecutionError()->is(\Vanilla\Scheduler\Job\JobExecutionStatus::stackExecutionError()));
    }

    public function test_StatusLooseStatus_Expect_Pass() {
        $status = \Vanilla\Scheduler\Job\JobExecutionStatus::retry()->getStatus();
        $this->assertTrue(\Vanilla\Scheduler\Job\JobExecutionStatus::looseStatus($status)->is(\Vanilla\Scheduler\Job\JobExecutionStatus::retry()));
    }

    public function test_Status_WithLooseStatus_Expect_Fail() {
        $status = \Vanilla\Scheduler\Job\JobExecutionStatus::retry()->getStatus();
        $this->assertFalse(\Vanilla\Scheduler\Job\JobExecutionStatus::looseStatus($status)->is(\Vanilla\Scheduler\Job\JobExecutionStatus::complete()));
    }
}
