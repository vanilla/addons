<?php if (!defined('APPLICATION')) exit();
/**
 * A plugin that integrates the php debug bar.
 *
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2003 Vanilla Forums, Inc
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPLv2
 * @since 2.0
 */

use DebugBar\StandardDebugBar;

// Define the plugin:
$PluginInfo['debugbar'] = array(
    'Name' => 'Debug Bar',
    'Description' => 'The debug bar shows debuggin information at the bottom of the page.',
    'Version' => '1.1.0',
    'RequiredApplications' => false,
    'RequiredTheme' => false,
    'RequiredPlugins' => false, // This is an array of plugin names/versions that this plugin requires
    'HasLocale' => false, // Does this plugin have any locale definitions?
    'RegisterPermissions' => array('Plugins.Debugger.View' => 'Garden.Settings.View'),
    'Author' => "Todd Burry",
    'AuthorEmail' => 'todd@vanillaforums.com',
    'AuthorUrl' => 'http://vanillaforums.com',
    'MobileFriendly' => true,
);

// Install the debugger database.
$tmp = Gdn::FactoryOverwrite(TRUE);
Gdn::FactoryInstall(Gdn::AliasDatabase, 'DatabaseDebugbar', __DIR__.'/class.databasedebugbar.php', Gdn::FactorySingleton, array('Database'));
Gdn::FactoryOverwrite($tmp);
unset($tmp);

class DebugbarPlugin extends Gdn_Plugin {
    /// Properties ///

    /**
     * @var \DebugBar\DebugBar
     */
    protected $debugBar;

    /// Methods ///

    public function __construct() {
        parent::__construct();
        require_once __DIR__.'/vendor/autoload.php';
    }

    /**
     * @return \DebugBar\DebugBar Returns the debug bar instance.
     */
    public function debugBar() {
        if ($this->debugBar === null) {
            $this->debugBar = new StandardDebugBar();
//            $this->debugBar->addCollector(new DebugBar\DataCollector\TimeDataCollector());
        }
        return $this->debugBar;
    }

    public function jsRenderer() {
        $baseurl = Gdn::Request()->WebRoot().'/plugins/debugbar/vendor/maximebf/debugbar/src/DebugBar/Resources';
        return $this->debugBar()->getJavascriptRenderer($baseurl);
    }

    /// Event Handlers ///

    public function Base_AfterBody_Handler($sender) {
        $body = $this->jsRenderer()->render();
        echo $body;
    }

    /**
     * @param Gdn_Controller $sender
     */
    public function Base_Render_Before($sender) {
        static $called = false;

        if ($called)
            return;

        $bar = $this->debugBar();
        $bar['time']->stopMeasure('controller');
        $bar['time']->startMeasure('render', 'Render');

        if (!$sender->Head)
            return;

        $head = $this->jsRenderer()->renderHead();

        $sender->AddAsset('Head', $head, 'debugbar-head');
        $called = true;
    }

    public function Gdn_Dispatcher_BeforeControllerMethod_Handler($sender) {
        static $called = false;

        if ($called)
            return;

        $bar = $this->debugBar();
        $bar['time']->stopMeasure('dispatch');
        $bar['time']->startMeasure('controller', 'Controller');
        $called = true;
    }

    public function Gdn_PluginManager_AfterStart_Handler($sender) {
        $bar = $this->debugBar();
        $bar['time']->startMeasure('dispatch', 'Dispatch');

        $db = Gdn::Database();
        if (method_exists($db, 'addCollector')) {
            $db->addCollector($this->debugBar());
        }
    }
}
