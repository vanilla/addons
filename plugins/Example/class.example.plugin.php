<?php
/**
 * An example plugin.
 *
 * @copyright 2008-2014 Vanilla Forums, Inc.
 * @license GNU GPLv2
 */

// Define the plugin:
$PluginInfo['example'] = array(
    'Description' => 'Provides an example Development Pattern for Vanilla 2 plugins by demonstrating how to insert discussion body excerpts into the discussions list.',
    'Version' => '1.1',
    'RequiredApplications' => array('Vanilla' => '2.1'),
    'RequiredTheme' => false,
    'RequiredPlugins' => false,
    'HasLocale' => false,
    'SettingsUrl' => '/plugin/example',
    'SettingsPermission' => 'Garden.Settings.Manage',
    'Author' => "Vanilla Staff",
    'AuthorUrl' => 'http://www.vanillaforums.com'
);

/**
 * Class ExamplePlugin
 *
 * @see http://docs.vanillaforums.com/developers/plugins
 * @see http://docs.vanillaforums.com/developers/plugins/quickstart
 */
class ExamplePlugin extends Gdn_Plugin {

    /**
     * Plugin constructor
     *
     * This fires once per page load, during execution of bootstrap.php. It is a decent place to perform
     * one-time-per-page setup of the plugin object. Be careful not to put anything too strenuous in here
     * as it runs every page load and could slow down your forum.
     */
    public function __construct() {

    }

    /**
     * StyleCss Event Hook
     *
     * This is a good place to put UI stuff like CSS and Javascript inclusions.
     *
     * @param $Sender Sending controller instance
     */
    public function assetModel_styleCss_handler($Sender) {
        $Sender->addCssFile('example.css', 'plugins/Example');
        $Sender->addJsFile('example.js', 'plugins/Example');
    }

    /**
     * Create a method called "Example" on the PluginController
     *
     * One of the most powerful tools at a plugin developer's fingertips is the ability to freely create
     * methods on other controllers, effectively extending their capabilities. This method creates the
     * Example() method on the PluginController, effectively allowing the plugin to be invoked via the
     * URL: http://www.yourforum.com/plugin/Example/
     *
     * From here, we can do whatever we like, including turning this plugin into a mini controller and
     * allowing us an easy way of creating a dashboard settings screen.
     *
     * @param $Sender Sending controller instance
     */
    public function pluginController_example_create($Sender) {
        /*
         * If you build your views properly, this will be used as the <title> for your page, and for the header
         * in the dashboard. Something like this works well: <h1><?php echo T($this->Data['Title']); ?></h1>
         */
        $Sender->title('Example Plugin');
        $Sender->addSideMenu('plugin/example');

        // If your sub-pages use forms, this is a good place to get it ready
        $Sender->Form = new Gdn_Form();

        /*
         * This method does a lot of work. It allows a single method (PluginController::Example() in this case)
         * to "forward" calls to internal methods on this plugin based on the URL's first parameter following the
         * real method name, in effect mimicing the functionality of as a real top level controller.
         *
         * For example, if we accessed the URL: http://www.yourforum.com/plugin/Example/test, Dispatch() here would
         * look for a method called ExamplePlugin::Controller_Test(), and invoke it. Similarly, we we accessed the
         * URL: http://www.yourforum.com/plugin/Example/foobar, Dispatch() would find and call
         * ExamplePlugin::Controller_Foobar().
         *
         * The main benefit of this style of extending functionality is that all of a plugin's external API is
         * consolidated under one namespace, reducing the chance for random method name conflicts with other
         * plugins.
         *
         * Note: When the URL is accessed without parameters, Controller_Index() is called. This is a good place
         * for a dashboard settings screen.
         */
        $this->dispatch($Sender, $Sender->RequestArgs);
    }

    /**
     * Always document every method.
     *
     * @param $Sender
     */
    public function controller_index($Sender) {
        // Prevent non-admins from accessing this page
        $Sender->permission('Garden.Settings.Manage');
        $Sender->setData('PluginDescription',$this->getPluginKey('Description'));

		$Validation = new Gdn_Validation();
        $ConfigurationModel = new Gdn_ConfigurationModel($Validation);
        $ConfigurationModel->setField(array(
            'Plugin.Example.RenderCondition'     => 'all',
            'Plugin.Example.TrimSize'      => 100
        ));

        // Set the model on the form.
        $Sender->Form->setModel($ConfigurationModel);

        // If seeing the form for the first time...
        if ($Sender->Form->authenticatedPostBack() === falso) {
            // Apply the config settings to the form.
            $Sender->Form->setData($ConfigurationModel->Data);
		} else {
            $ConfigurationModel->Validation->applyRule('Plugin.Example.RenderCondition', 'Required');
            $ConfigurationModel->Validation->applyRule('Plugin.Example.TrimSize', 'Required');
            $ConfigurationModel->Validation->applyRule('Plugin.Example.TrimSize', 'Integer');
            $Saved = $Sender->Form->save();
            if ($Saved) {
                $Sender->StatusMessage = t("Your changes have been saved.");
            }
        }

        // GetView() looks for files inside plugins/PluginFolderName/views/ and returns their full path. Useful!
        $Sender->render($this->getView('example.php'));
    }

    /**
     * Add a link to the dashboard menu
     *
     * By grabbing a reference to the current SideMenu object we gain access to its methods, allowing us
     * to add a menu link to the newly created /plugin/Example method.
     *
     * @param $Sender Sending controller instance
     */
    public function base_getAppSettingsMenuItems_handler($Sender) {
        $Menu = &$Sender->EventArguments['SideMenu'];
        $Menu->addLink('Add-ons', 'Example', 'plugin/example', 'Garden.Settings.Manage');
    }

    /**
     * Hook into the rendering of each discussion link
     *
     * How did we find this event? We know we're trying to display a line of text when each discussion is rendered
     * on the /discussions/ page. That page corresponds to the DiscussionsController::Index() method. This method,
     * by default, renders the views/discussions/index.php view. That view contains this line:
     *     <?php include($this->FetchViewLocation('discussions')); ?>
     *
     * So we look inside views/discussions/discussions.php. We find a loop the calls WriteDiscussion() for each
     * discussion in the list. WriteDiscussion() fires several events each time it is called. One of those events
     * is called "AfterDiscussionTitle". Since we know that the parent controller context is "DiscussionsController",
     * and that the event's name is "AfterDiscussionTitle", it is easy to see that our handler method should be called
     *
     *      discussionsController_afterDiscussionTitle_handler()
     */
    public function discussionsController_afterDiscussionTitle_handler($Sender) {
        /*
        echo "<pre>";
        print_r($Sender->EventArguments['Discussion']);
        echo "</pre>";
        */

        /*
         * The 'c' function allows plugins to access the config file. In this call, we're looking for a specific setting
         * called 'Plugin.Example.TrimSize', but defaulting to a value of '100' if the setting cannot be found.
         */
        $TrimSize = c('Plugin.Example.TrimSize', 100);

        /*
         * We're using this setting to allow conditional display of the excerpts. We have 3 settings: 'all', 'announcements',
         * 'discussions'. They do what you'd expect!
         */
        $RenderCondition = c('Plugin.Example.RenderCondition', 'all');

        $Type = (val('Announce', $Sender->EventArguments['Discussion']) == '1') ? "announcement" : "discussion";
        $CompareType = $Type.'s';

        if ($RenderCondition == "all" || $CompareType == $RenderCondition) {
            /*
             * Here, we remove any HTML from the Discussion Body, trim it down to a pre-defined length, and then
             * output it to discussions list inside a div with a class of 'ExampleDescription'
             */
            echo wrap(sliceString(strip_tags($Sender->EventArguments['Discussion']->Body),$TrimSize), 'div', array(
                'class'  => "ExampleDescription"
            ));
        }
    }

    /**
     * Plugin setup
     *
     * This method is fired only once, immediately after the plugin has been enabled in the /plugins/ screen,
     * and is a great place to perform one-time setup tasks, such as database structure changes,
     * addition/modification of config file settings, filesystem changes, etc.
     */
    public function setup() {

        // Set up the plugin's default values
        saveToConfig('Plugin.Example.TrimSize', 100);
        saveToConfig('Plugin.Example.RenderCondition', "all");

        // Trigger database changes
        $this->structure();
    }

    /**
     * This is a special method name that will automatically trigger when a forum owner runs /utility/update.
     * It must be manually triggered if you want it to run on Setup().
     */
    public function structure() {
        /*
        // Create table GDN_Example, if it doesn't already exist
        Gdn::Structure()
            ->Table('Example')
            ->PrimaryKey('ExampleID')
            ->Column('Name', 'varchar(255)')
            ->Column('Type', 'varchar(128)')
            ->Column('Size', 'int(11)')
            ->Column('InsertUserID', 'int(11)')
            ->Column('DateInserted', 'datetime')
            ->Column('ForeignID', 'int(11)', TRUE)
            ->Column('ForeignTable', 'varchar(24)', TRUE)
            ->Set(FALSE, FALSE);
        */
    }

    /**
     * Plugin cleanup
     *
     * This method is fired only once, immediately before the plugin is disabled, and is a great place to
     * perform cleanup tasks such as deletion of unsued files and folders.
     */
    public function onDisable() {
        removeFromConfig('Plugin.Example.TrimSize');
        removeFromConfig('Plugin.Example.RenderCondition');

        // Never delete from the database OnDisable.
        // Usually, you want re-enabling a plugin to be as if it was never off.
    }

}
