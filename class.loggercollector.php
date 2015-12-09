<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2015 Vanilla Forums Inc.
 * @license GPLv2
 */

/**
 * Collects application logged events into a the debug bar.
 */
class LoggerCollector implements \LoggerInterface {
    use \Psr\Log\LoggerAwareTrait;
    use \Psr\Log\LoggerTrait;

    /**
     * Initialize a new instance of a {@link LoggerCollector} class.
     *
     * @param PsrLoggerInterface $logger The logger to forward all logs to.
     */
    public function __construct(\Psr\Log\LoggerInterface $logger = null) {
        $this->setLogger($logger);
    }

    /**
     * {@inheritdoc}
     */
    public function log($level, $message, array $context = []) {
        $message = formatString($message, $context);

        if (!empty($context['event'])) {
            $message = "{$context['event']}: $message";
        }
        $this->logger->log($level, $message, $context);
    }
}
