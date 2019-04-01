<?php
/**
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Vanilla\Scheduler\Job;

/**
 * Queue job interface.
 *
 * Interface for a runnable job payload.
 *
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 */
interface JobInterface {

    /**
     * Set job Message
     *
     * @param array $message
     */
    public function setMessage(array $message);
}
