<?php
/**
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license MIT
 */

namespace Vanilla\Scheduler\Test;

use Psr\Log\LoggerInterface;

/**
 * Class NonCompliantJob.
 *
 * I look like a Job, but not implementing the JobInterface
 *
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 */
class NonCompliantJob {
    protected $logger;
    protected $message;

    public function __construct(LoggerInterface $logger) {
        $this->logger = $logger;
    }

    public function setMessage(array $message) {
        $this->message = $message;
    }

    public function run() {
        $this->logger->info(get_class($this)." :: ".var_export($this->message, true));
    }
}
