<?php

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
     * Replace black listed words according to pattern
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
     *
     *
     * @return array
     */
    public function getPatterns() {
        // Get config.
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

    public function getReplacement() {
        return $this->replacement;
    }

    public function getWords() {
        return $this->words;
    }

    public function setReplacement($replacement) {
        $this->replacement = $replacement;
    }

    public function setWords($words) {
        $this->words = $words;
    }
}
