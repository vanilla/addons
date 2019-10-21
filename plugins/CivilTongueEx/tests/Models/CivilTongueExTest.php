<?php
/**
 * @author Patrick Kelly <patrick.k@vanillaforums.com>
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license https://opensource.org/licenses/GPL-2.0 GPL-2.0
 */

namespace VanillaTests\Models;

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
    public static function setupBeforeClass() {
        self::$addons = ['vanilla', 'civiltongueex'];
        static::siteTestBeforeClass();
    }

    /**
     * Instantiate the plugin, config.
     *
     * @throws \Garden\Container\ContainerException
     * @throws \Garden\Container\NotFoundException
     */
    public function setUp() {
        parent::setUp();
        $this->plugin = new \CivilTonguePlugin();
        $this->config = self::$container->get(\Gdn_Configuration::class);
        $this->words = $this->config->get('Plugins.CivilTongue.Words', '');
    }

    /**
     * Undo changes to config.
     */
    public function tearDown() {
        $this->config->set('Plugins.CivilTongue.Words', $this->words, true, false);
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
        $this->config->set('Plugins.CivilTongue.Words', $patternList, true, false);
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
            ['poop;PoOp;$hit;a$$', 'this is poop the text.', 'this is **** the text.'],
        ];
        return $provider;
    }
}
