<?php
/**
 * @author Isis Graziatto <isis.g@vanillaforums.com>
 * @copyright 2009-2020 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

namespace VanillaTests\CivilTongueEx\Library;

use CivilTongueEx\Library\ContentFilter;
use PHPUnit\Framework\TestCase;
use VanillaTests\SiteTestTrait;

/**
 * Class ContentFilterTest
 * @package VanillaTests\CivilTongueEx\Library
 */
class ContentFilterTest extends TestCase {
    use SiteTestTrait;

    /**
     * @return array
     */
    public static function getAddons(): array {
        return ['vanilla', 'civiltongueex'];
    }

    /**
     * Create a new ContentFilter instance for testing.
     */
    public function setUp(): void {
        parent::setUp();

        $this->contentFilter = $this->container()->get(ContentFilter::class);
        $this->contentFilter->setWords('bacon;Bacon;');
        $this->contentFilter->setReplacement('****');
    }

    /**
     * Test replace() method in ContentFilter
     */
    public function testReplace() {
        $expected = 'Pigs are adorable and **** should never be a thing.';
        $result = $this->contentFilter->replace('Pigs are adorable and bacon should never be a thing.');
        $this->AssertSame($expected, $result);
    }
}
