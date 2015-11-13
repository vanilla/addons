<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2015 Vanilla Forums Inc.
 * @license GPLv2
 */

use DebugBar\DataCollector\MessagesCollector;


/**
 * Collects application logged events into a the debug bar.
 */
class LoggerCollector extends MessagesCollector implements LoggerInterface {


    /**
     * Initialize a new instance of a {@link LoggerCollector} class.
     *
     * @param string $name The name of the collector.
     */
    public function __construct($name = 'log') {
        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    public function log($level, $message, array $context = []) {
        $message = formatString($message, $context);
        parent::log($level, $message, $context);
    }
}
