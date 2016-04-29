<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2016 Vanilla Forums Inc.
 * @license Proprietary
 */

namespace Vanilla\DebugBar;

/**
 * Customizes the default debug bar exceptions collector.
 */
class ExceptionsCollector extends \DebugBar\DataCollector\ExceptionsCollector {

    /**
     * Returns exception data as an array.
     *
     * @param Exception $e The exception to format.
     * @return array Returns the exception info as an array.
     */
    public function formatExceptionData(\Exception $e) {
        $row = parent::formatExceptionData($e);

        if ($e instanceof \ErrorException) {
            $constants = [
                E_ERROR => 'E_ERROR',
                E_WARNING => 'E_WARNING',
                E_PARSE => 'E_PARSE',
                E_NOTICE => 'E_NOTICE',
                E_CORE_ERROR => 'E_CORE_ERROR',
                E_COMPILE_ERROR => 'E_COMPILE_ERROR',
                E_COMPILE_WARNING => 'E_COMPILE_WARNING',
                E_USER_ERROR => 'E_USER_ERROR',
                E_USER_WARNING => 'E_USER_WARNING',
                E_USER_NOTICE => 'E_USER_NOTICE',
                E_STRICT => 'E_STRICT',
                E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
                E_DEPRECATED => 'E_DEPRECATED',
                E_USER_DEPRECATED => 'E_USER_DEPRECATED',
            ];

            if (isset($constants[$e->getCode()])) {
                $row['type'] = $constants[$e->getCode()];
            }
        }

        return $row;
    }
}
