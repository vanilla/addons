<?php
/**
 * @author Patrick Kelly <patrick.k@vanillaforums.com>
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license https://opensource.org/licenses/GPL-2.0 GPL-2.0
 */

namespace VanillaTests\Models;

use CivilTongueEx\Library\ContentFilter;
use PHPUnit\Framework\TestCase;
use VanillaTests\SiteTestTrait;

/**
 * Class CivilTongueExTest
 */
class CivilTongueExTest extends TestCase {
    use SiteTestTrait {
        setupBeforeClass as private siteTestBeforeClass;
    }

    /** @var  \CivilTonguePlugin */
    private $plugin;

    /** @var \Gdn_Configuration */
    private $config;

    /** @var string patterns saved in config. */
    private $words;

    /**
     * Add CivilTongueEx plugin to addons.
     */
    public static function setupBeforeClass(): void {
        self::$addons = ['vanilla', 'civiltongueex'];
        static::siteTestBeforeClass();
    }

    /**
     * Instantiate the plugin, config.
     *
     * @throws \Garden\Container\ContainerException Error while retrieving the entry.
     * @throws \Garden\Container\NotFoundException No entry was found for this identifier.
     */
    public function setUp(): void {
        parent::setUp();
        $this->contentFilter = $this->container()->get(ContentFilter::class);
        $this->plugin = new \CivilTonguePlugin($this->contentFilter);
        $this->config = self::$container->get(\Gdn_Configuration::class);
        $this->words = $this->contentFilter->setWords($this->config->get('Plugins.CivilTongue.Words', ''));
    }

    /**
     * Undo changes to config.
     */
    public function tearDown(): void {
        $this->contentFilter->setWords($this->words);
        parent::tearDown();
    }

    /**
     * Test finding and replacing patterns with the CivilTongue plugin.
     *
     * @param string $patternList List of words to be replaced.
     * @param string $text Sample text to be filtered.
     * @param string $expected The text expected after it is filtered.
     * @dataProvider providePatternList
     *
     */
    public function testReplacePatterns(string $patternList, string $text, string $expected) {
        $this->contentFilter->setWords($patternList);
        $this->plugin->setReplacement('****');
        $output = $this->plugin->replace($text);
        $this->assertEquals($expected, $output);
    }

    /**
     * Provide patterns, test text and expected results to the test.
     *
     * @return array Provider data.
     */
    public function providePatternList() {
        $provider = [
            'General' => ['poop;$hit;a$$', 'This poop is the text.', 'This **** is the text.'],
            'TextBeginsWithSwear' => ['poop;$hit;a$$', 'poop the text', '**** the text'],
            'TextEndsWithSwear' => ['poop;$hit;a$$', 'The text is poop', 'The text is ****'],
            'SwearEndsWithDollarSign' => ['poop;$hit;a$$', 'The text is a$$', 'The text is ****'],
            'SwearStartsWithDollarSign' => ['poop;$hit;a$$', '$hit the text', '**** the text'],
            'SwearHasDollarSign' => ['poop;$hit;a$$', '$hithead the text', '$hithead the text'],
            'SwearHasCamelCase' => ['poop;$hit;a$$', 'PoOp the text', '**** the text'],
        ];
        return $provider;
    }
}
