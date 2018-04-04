<?php
/**
 * An example plugin.
 *
 * @copyright 2008-2014 Vanilla Forums, Inc.
 * @license GNU GPLv2
 */

/**
 * Class ExamplePlugin
 *
 * This plugin excerpt every discussion/announcement body
 * before adding it under their title in the discussions list.
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
     * @param $sender Sending controller instance
     */
    public function assetModel_styleCss_handler($sender) {
        $sender->addCssFile('example.css', 'plugins/Example');
    }

    /**
     * Add javascript file before discussions rendering.
     *
     * @param $sender Sending controller instance
     */
    public function discussionsController_render_before($sender) {
        $sender->addJsFile('example.js', 'plugins/Example');
    }

    /**
     * Create a method called "Example" on the PluginController
     *
     * One of the most powerful tools at a plugin developer's fingertips is the ability to freely create
     * methods on other controllers, effectively extending their capabilities. This method creates the
     * example() method on the PluginController, effectively allowing the plugin to be invoked via the
     * URL: http://www.yourforum.com/plugin/example/
     *
     * From here, we can do whatever we like, including turning this plugin into a mini controller and
     * allowing us an easy way of creating a dashboard settings screen.
     *
     * @param $sender Sending controller instance
     */
    public function pluginController_example_create($sender) {
        /*
         * If you build your views properly, this will be used as the <title> for your page, and for the header
         * in the dashboard. Something like this works well: <h1><?php echo t($this->Data['Title']); ?></h1>
         */
        $sender->title('Example Plugin');
        $sender->setHighlightRoute('plugin/example');

        // If your sub-pages use forms, this is a good place to get it ready
        $sender->Form = new Gdn_Form();

        /*
         * This method does a lot of work. It allows a single method (PluginController::example() in this case)
         * to "forward" calls to internal methods on this plugin based on the URL's first parameter following the
         * real method name, in effect mimicing the functionality of as a real top level controller.
         *
         * For example, if we accessed the URL: http://www.yourforum.com/plugin/Example/test, dispatch() here would
         * look for a method called ExamplePlugin::controller_Test(), and invoke it. Similarly, we we accessed the
         * URL: http://www.yourforum.com/plugin/Example/foobar, dispatch() would find and call
         * ExamplePlugin::controller_Foobar().
         *
         * The main benefit of this style of extending functionality is that all of a plugin's external API is
         * consolidated under one namespace, reducing the chance for random method name conflicts with other
         * plugins.
         *
         * Note: When the URL is accessed without parameters, controller_Index() is called. This is a good place
         * for a dashboard settings screen.
         */
        $this->dispatch($sender, $sender->RequestArgs);
    }

    /**
     * Always document every method.
     *
     * @param $sender
     */
    public function controller_index($sender) {
        // Prevent non-admins from accessing this page
        $sender->permission('Garden.Settings.Manage');
        $sender->setData('PluginDescription', $this->getPluginKey('Description'));

		$validation = new Gdn_Validation();
        $configurationModel = new Gdn_ConfigurationModel($validation);
        $configurationModel->setField([
            'example.RenderCondition' => 'all',
            'example.TrimSize' => 100
        ]);

        // Set the model on the form.
        $sender->Form->setModel($configurationModel);

        // If seeing the form for the first time...
        if ($sender->Form->authenticatedPostBack() === false) {
            // Apply the config settings to the form.
            $sender->Form->setData($configurationModel->Data);
		} else {
            $configurationModel->Validation->applyRule('example.RenderCondition', 'Required');
            $configurationModel->Validation->applyRule('example.TrimSize', 'Required');
            $configurationModel->Validation->applyRule('example.TrimSize', 'Integer');
            $saved = $sender->Form->save();
            if ($saved) {
                $sender->informMessage(t("Your changes have been saved."));
            }
        }

        // Search for the example.php view in the plugins view subfolder.
        $sender->render('example', '', 'plugins/example');
    }

    /**
     * Add a link to the dashboards site navigation module.
     *
     * @param $sender Sending controller instance.
     */
    public function dashboardNavModule_init_handler($sender) {
        $sender->addLinkIf(
            'Garden.Settings.Manage',
            t('Example'),
            '/plugin/example',
            'add-ons.example',
            'nav-example'
        );
    }

    /**
     * Hook into the rendering of each discussion link
     *
     * How did we find this event? We know we're trying to display a line of text when each discussion is rendered
     * on the /discussions/ page. That page corresponds to the DiscussionsController::index() method. This method,
     * by default, renders the views/discussions/index.php view. That view contains this line:
     *     <?php include($this->fetchViewLocation('discussions')); ?>
     *
     * So we look inside views/discussions/discussions.php. We find a loop that calls writeDiscussion() for each
     * discussion in the list. writeDiscussion() fires several events each time it is called. One of those events
     * is called "AfterDiscussionTitle". Since we know that the parent controller context is "DiscussionsController",
     * and that the event's name is "AfterDiscussionTitle", it is easy to see that our handler method should be called
     *
     *      discussionsController_afterDiscussionTitle_handler()
     */
    public function discussionsController_afterDiscussionTitle_handler($sender) {
        /*
        echo "<pre>";
        print_r($sender->EventArguments['Discussion']);
        echo "</pre>";
        */

        /*
         * The 'c' function allows plugins to access the config file. In this call, we're looking for a specific setting
         * called 'example.TrimSize', but defaulting to a value of '100' if the setting cannot be found.
         */
        $trimSize = c('example.TrimSize', 100);

        /*
         * We're using this setting to allow conditional display of the excerpts. We have 3 settings: 'all', 'announcements',
         * 'discussions'. They do what you'd expect!
         */
        $renderCondition = c('example.RenderCondition', 'all');

        $type = (val('Announce', $sender->EventArguments['Discussion']) == '1') ? "announcement" : "discussion";
        $compareType = $type.'s';

        if ($renderCondition == "all" || $compareType == $renderCondition) {
            /*
             * Here, we remove any HTML from the Discussion Body, trim it down to a pre-defined length, re-encode htmlentities
             * and then output it to discussions list inside a div with a class of 'ExampleDescription'
             */
            $discussionBody = htmlentities( // Restore HTML entities
                sliceString(
                    html_entity_decode( // Convert HTML entities to single characters before cutting
                        strip_tags(
                            $sender->EventArguments['Discussion']->Body
                        )
                    ),
                    $trimSize
                )
            );

            echo wrap($discussionBody, 'div', [
                'class'  => "ExampleDescription"
            ]);
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
        saveToConfig('example.TrimSize', 100);
        saveToConfig('example.RenderCondition', "all");

        // Trigger database changes
        $this->structure();
    }

    /**
     * This is a special method name that will automatically trigger when a forum owner runs /utility/update.
     * It must be manually triggered if you want it to run on setup().
     */
    public function structure() {
        /*
        // Create table GDN_Example, if it doesn't already exist
        Gdn::structure()
            ->table('Example')
            ->primaryKey('ExampleID')
            ->column('Name', 'varchar(255)')
            ->column('Type', 'varchar(128)')
            ->column('Size', 'int(11)')
            ->column('InsertUserID', 'int(11)')
            ->column('DateInserted', 'datetime')
            ->column('ForeignID', 'int(11)', true)
            ->column('ForeignTable', 'varchar(24)', true)
            ->set(false, false);
        */
    }

    /**
     * Plugin cleanup
     *
     * This method is fired only once, immediately before the plugin is disabled, and is a great place to
     * perform cleanup tasks such as deletion of unsued files and folders.
     */
    public function onDisable() {
        removeFromConfig('example.TrimSize');
        removeFromConfig('example.RenderCondition');

        // Never delete from the database OnDisable.
        // Usually, you want re-enabling a plugin to be as if it was never off.
    }

}
