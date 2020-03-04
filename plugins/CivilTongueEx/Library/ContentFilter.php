<?php
/**
 * @author Isis Graziatto<isis.g@vanillaforums.com>
 * @copyright 2009-2020 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

namespace CivilTongueEx\Library;

use Vanilla\Plugins\ContentFilterInterface;

/**
 * Class ContentFilter
 * @package CivilTongueEx\Library
 */
class ContentFilter implements ContentFilterInterface {

    /** @var string? */
    private $replacement;

    /** @var string? */
    private $words;

    /**
     * Replace black-listed words according to pattern
     *
     * @param string $text
     * @return mixed
     */
    public function replace($text = '') {
        if (!isset($text)) {
            return $text;
        }

        $patterns = $this->getPatterns();
        $result = preg_replace($patterns, $this->replacement, $text);
        return $result;
    }

    /**
     * Get patterns
     *
     * @return array
     */
    public function getPatterns() {
        static $patterns = null;

        if ($patterns === null) {
            $patterns = [];
            $words = $this->words;
            if ($words !== null) {
                $explodedWords = explode(';', $words);
                foreach ($explodedWords as $word) {
                    if (trim($word)) {
                        $patterns[] = '`(?<![\pL])'.preg_quote(trim($word), '`').'(?![\pL])`isu';
                    }
                }
            }
        }
        return $patterns;
    }

    /**
     * @return string
     */
    public function getReplacement() {
        return $this->replacement;
    }

    /**
     * @return string
     */
    public function getWords() {
        return $this->words;
    }

    /**
     * @param string $replacement
     */
    public function setReplacement($replacement) {
        $this->replacement = $replacement;
    }

    /**
     * @param string $words
     */
    public function setWords($words) {
        $this->words = $words;
    }
}
