<?php if (!defined('APPLICATION')) exit();
/**
 * A plugin that integrates the php debug bar.
 *
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2003 Vanilla Forums, Inc
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPLv2
 * @since 2.0
 */

use DebugBar\DebugBar;
use DebugBar\DataCollector\MessagesCollector;
use DebugBar\DataCollector\ExceptionsCollector;

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
$tmp = Gdn::factoryOverwrite(true);
Gdn::factoryInstall(Gdn::AliasDatabase, 'DatabaseDebugbar', __DIR__.'/class.databasedebugbar.php', Gdn::FactorySingleton, array('Database'));
Gdn::factoryOverwrite($tmp);
unset($tmp);

/**
 * Handles integration of the [PHP Debug Bar](http://phpdebugbar.com/) with Vanilla.
 */
class DebugbarPlugin extends Gdn_Plugin {
    /// Properties ///

    /**
     * @var \DebugBar\DebugBar
     */
    protected $debugBar;


    /**
     * @var LoggerCollector
     */
    protected $logger;

    /// Methods ///

    /**
     * Initialize a new instance of the {@link \DebugBarPlugin} class.
     */
    public function __construct() {
        parent::__construct();
        require_once __DIR__.'/vendor/autoload.php';
    }

    /**
     * Add the application traces to a message collector.
     *
     * @param MessagesCollector $messages The collector to add the messages to.
     * @param ExceptionsCollector $exceptions The collector to add exceptions to.
     */
    public function addTraces(MessagesCollector $messages, ExceptionsCollector $exceptions) {
        $traces = trace();
        if (!is_array($traces)) {
            return;
        }

        foreach ($traces as $info) {
            list($message, $type) = $info;

            if ($message instanceof \Exception) {
                $exceptions->addException($message);
                continue;
            }

            if (!is_string($message)) {
                $message = $messages->getDataFormatter()->formatVar([$message]);
            }
            switch ($type) {
                case TRACE_ERROR:
                    $messages->error($message);
                    break;
                case TRACE_INFO:
                    $messages->info($message);
                    break;
                case TRACE_NOTICE:
                    $messages->notice($message);
                    break;
                case TRACE_WARNING:
                    $messages->warning($message);
                    break;
                default:
                    $messages->debug("$type: $message");
            }
        }
    }

    /**
     * Get the debug bar for the application.
     *
     * @return \DebugBar\DebugBar Returns the debug bar instance.
     */
    public function debugBar() {
        if ($this->debugBar === null) {
            $this->debugBar = new DebugBar();

            $this->debugBar->addCollector(new \DebugBar\DataCollector\PhpInfoCollector());
            $this->debugBar->addCollector(new \DebugBar\DataCollector\MessagesCollector());
            $this->debugBar->addCollector(new \DebugBar\DataCollector\RequestDataCollector());
            $this->debugBar->addCollector(new \DebugBar\DataCollector\TimeDataCollector());
            $this->debugBar->addCollector(new \DebugBar\DataCollector\MemoryCollector());
            $this->debugBar->addCollector(new \DebugBar\DataCollector\ExceptionsCollector());

            $db = Gdn::database();
            if (method_exists($db, 'addCollector')) {
                $db->addCollector($this->debugBar);
            }

            $logger = new LoggerCollector($this->debugBar['messages']);
            Logger::setLogger($logger);

            $this->debugBar->addCollector(new \DebugBar\DataCollector\ConfigCollector([], 'data'));
        }
        return $this->debugBar;
    }

    /**
     * Get the javascript script includer for the debug bar.
     *
     * @return \DebugBar\JavascriptRenderer Returns the javascript script includer for the debug bar.
     */
    public function jsRenderer() {
        $baseurl = Gdn::request()->assetRoot().'/plugins/debugbar/vendor/maximebf/debugbar/src/DebugBar/Resources';
        return $this->debugBar()->getJavascriptRenderer($baseurl);
    }

    /// Event Handlers ///

    /**
     * Add the debug bar's javascript after the body.
     */
    public function base_afterBody_handler() {
        $body = $this->jsRenderer()->render();
        echo $body;
    }

    /**
     * Finish off the controller timing and add the debug bar asset to the page.
     *
     * @param Gdn_Controller $sender The event sender.
     */
    public function base_render_before($sender) {
        static $called = false;

        if ($called) {
            return;
        }

        $bar = $this->debugBar();
        $bar['time']->stopMeasure('controller');

        $this->addTraces($bar['messages'], $bar['exceptions']);
        $bar['data']->setData($sender->Data);


        $bar['time']->startMeasure('render', 'Render');

        if (!$sender->Head) {
            return;
        }

        $head = $this->jsRenderer()->renderHead();

        $sender->AddAsset('Head', $head, 'debugbar-head');
        $called = true;
    }

    /**
     * Start the timing of the controller method.
     */
    public function gdn_dispatcher_beforeControllerMethod_handler() {
        static $called = false;

        if ($called) {
            return;
        }

        $bar = $this->debugBar();
        $bar['time']->stopMeasure('dispatch');
        $bar['time']->startMeasure('controller', 'Controller');
        $called = true;
    }

    public function gdn_dispatcher_appStartup_handler() {
//        $logger = new LoggerCollector();
//        Logger::setLogger($logger);
//        $this->debugBar()->addCollector($logger);
    }

    /**
     * Start the debug bar timings as soon as possible.
     */
    public function gdn_pluginManager_afterStart_handler() {
        $bar = $this->debugBar();
        $bar['time']->startMeasure('dispatch', 'Dispatch');
    }
}
