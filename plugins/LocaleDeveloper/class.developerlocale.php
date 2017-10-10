<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class DeveloperLocale extends Gdn_Locale {
    public $_CapturedDefinitions = [];

    /**
     * Gets all of the definitions in the current locale.
     *
     * return array
     */
    public function allDefinitions() {
        $result = array_merge($this->_Definition, $this->_CapturedDefinitions);
        return $result;
    }

    public function capturedDefinitions() {
        return $this->_CapturedDefinitions;
    }

    public static function guessPrefix() {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        foreach ($trace as $i => $row) {
            if (strcasecmp($row['function'], 't') === 0) {
                if ($trace[$i + 1]['function'] == 'Plural')
                    return self::prefixFromPath($trace[$i + 1]['file']);
                else
                    return self::prefixFromPath($row['file']);
            }
            if (strcasecmp($row['function'], 'Translate') === 0) {
                if (!in_array(basename($row['file']), ['functions.general.php', 'class.gdn.php'])) {
                    return self::prefixFromPath($row['file']);
                }
            }
        }

        return FALSE;
    }

    public static function prefixFromPath($path) {
        $result = '';

        if (preg_match('`/plugins/([^/]+)`i', $path, $matches)) {
            $plugin = strtolower($matches[1]);

            if (in_array($plugin, ['buttonbar', 'fileupload', 'facebook', 'twitter', 'quotes', 'signatures', 'splitmerge', 'tagging', 'nbbc'])) {
                $result .= 'core';
            } else
                $result .= $plugin.'_plugin';
        } elseif (preg_match('`/library/`i', $path, $matches)) {
            $result .= 'core';
        } elseif (preg_match('`/applications/([^/]+)`i', $path, $matches)) {
            $app = strtolower($matches[1]);

            if (in_array($app, ['conversations', 'vanilla', 'dashboard'])) {
                // This is a core app.
                $result .= 'core';
            } else {
                $result .= $app.'_application';
            }
        } elseif (preg_match('`/themes/([^/]+)`i', $path, $matches)) {
            $result = FALSE;
        }
        return $result;
    }

    public function translate($code, $default = FALSE) {
        $result = parent::translate($code, $default);

        if (!$code || substr($code, 0, 1) == '@')
            return $result;

        $prefix = self::guessPrefix();

        if (!$prefix) {
            return $result;
        }

        if ($prefix == 'unknown') {
            decho($code);
            decho(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
            die();
        }

        if (Gdn_Theme::inSection('Dashboard'))
            $prefix = 'dash_'.$prefix;
        else
            $prefix = 'site_'.$prefix;

        $this->_CapturedDefinitions[$prefix][$code] = $result;

        return $result;
    }
}
