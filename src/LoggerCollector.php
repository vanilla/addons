<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2016 Vanilla Forums Inc.
 * @license GPLv2
 */

namespace Vanilla\DebugBar;

use DebugBar\DataCollector\MessagesCollector;

/**
 * Collects application logged events into a the debug bar.
 */
class LoggerCollector extends MessagesCollector {
    /**
     * Initialize a new instance of a {@link LoggerCollector} class.
     *
     * @param string $name The name of the collector.
     */
    public function __construct($name = 'messages') {
        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    public function log($level, $message, array $context = []) {
        $message = formatString($message, $context);

        if (is_array($message)) {
            $context = $message + $context;
            $message = '{...}';
        }
        $row = array(
            'message' => $message,
            'label' => $level,
            'time' => microtime(true)
        );

        if (!empty($context['event'])) {
            $row['event'] = $context['event'];
            unset($context['event']);
        }
        if (!empty($context['timestamp'])) {
            $timestamp = $context['timestamp'];
            unset($context['timestamp']);
            $date = \Gdn_Format::dateFull($timestamp);
            $context['dateTime'] = $date;
            $context['timestamp'] = $timestamp;
        }

        if (!empty($context)) {
            $row['context'] = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        $this->messages[] = $row;
    }

    public function getWidgets() {
        $name = $this->getName();
        return array(
            "$name" => array(
                'icon' => 'list-alt',
                "widget" => "PhpDebugBar.Widgets.VanillaLoggerWidget",
                "map" => "$name.messages",
                "default" => "[]"
            ),
            "$name:badge" => array(
                "map" => "$name.count",
                "default" => "null"
            )
        );
    }
}
