<?php
/**
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license MIT
 */

namespace Vanilla\Scheduler\Test;

use Psr\Log\LoggerInterface;
use Vanilla\Scheduler\Job\JobExecutionStatus;
use Vanilla\Scheduler\Job\LocalJobInterface;

/**
 * Class ThrowableEchoJob
 *
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 */
class ThrowableEchoJob implements LocalJobInterface {
    protected $logger;
    protected $message;

    public function __construct(LoggerInterface $logger) {
        $this->logger = $logger;
    }

    public function setMessage(array $message) {
        $this->message = $message;
    }

    public function run(): JobExecutionStatus {
        nonExistentFunction();
    }
}
