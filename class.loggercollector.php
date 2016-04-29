<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2016 Vanilla Forums Inc.
 * @license GPLv2
 */
use DebugBar\DataCollector\MessagesCollector;

/**
 * Collects application logged events into a the debug bar.
 */
class LoggerCollector implements \Psr\Log\LoggerInterface {
    use \Psr\Log\LoggerTrait;

    /**
     * @var MessagesCollector Where the messages are put.
     */
    private $messages;

    /**
     * Initialize a new instance of a {@link LoggerCollector} class.
     *
     * @param PsrLoggerInterface $messages The logger to forward all logs to.
     */
    public function __construct(MessagesCollector $messages = null) {
        $this->setMessages($messages);
    }

    /**
     * {@inheritdoc}
     */
    public function log($level, $message, array $context = []) {
        $message = formatString($message, $context);

        if (!empty($context['event'])) {
            $message = "{$context['event']}: $message";
            unset($context['event']);
        }
        $this->messages->log($level, $message, $context);
    }

    /**
     * Get the messages.
     *
     * @return MessagesCollector Returns the messages.
     */
    public function getMessages() {
        return $this->messages;
    }

    /**
     * Set the messages.
     *
     * @param MessagesCollector $messages
     * @return LoggerCollector Returns `$this` for fluent calls.
     */
    public function setMessages($messages) {
        $this->messages = $messages;
        return $this;
    }
}
