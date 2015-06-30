<?php if (!defined('APPLICATION')) exit();

require_once 'class.geoip.import.php';
require_once 'class.geoip.query.php';

// Define the plugin:
$PluginInfo['GeoIP'] = array(
    'Name' => 'Carmen Sandiego (GeoIP)',
    'Description' => 'Provides Geo IP location functionality. This product includes GeoLite2 data created by MaxMind, available from <a href="http://www.maxmind.com">http://www.maxmind.com</a>.',
    'Version' => '0.0.1',
    'RequiredApplications' => array('Vanilla' => '2.0.10'),
    'RequiredTheme' => FALSE,
    'RequiredPlugins' => FALSE,
    'HasLocale' => FALSE,
    'SettingsUrl' => '/plugin/geoip',
    'SettingsPermission' => 'Garden.AdminUser.Only',
    'Author' => "Deric D. Davis",
    'AuthorEmail' => 'deric.d@vanillaforums.com',
    'AuthorUrl' => 'http://www.vanillaforums.com'
);

class GeoipPlugin extends Gdn_Plugin {


    private $pdo;



    private $query;
    private $import;


    public function __construct() {

        // Make sure GeoIP tools are installed:
        if (!function_exists('geoip_record_by_name')) {
            trigger_error("GeoIP lib is not installed on this server!", E_USER_ERROR);
            return false;
        }

        // Instantiate Query Object:
        $this->query  = new GeoipQuery();

        // Instantiate Import Object:
        $this->import = new GeoipImport();
    }

    public function Base_Render_Before($Sender) {
        $Sender->AddJsFile('js/example.js');
    }

    public function AssetModel_StyleCss_Handler($Sender) {
        $Sender->AddCssFile('design/flags.css');
    }

    public function PluginController_GeoIP_Create($Sender) {

        $Sender->Title('Carmen Sandiego Plugin (GeoIP)');
        $Sender->AddSideMenu('plugin/geoip');
        $Sender->Form = new Gdn_Form();

        $this->Dispatch($Sender, $Sender->RequestArgs);
        $this->Render('geoip');

        return true;
    }

    public function Controller_index($Sender) {

        // echo "<p>Do Login:".C('Plugin.GeoIP.doLogin')."</p>";

        $Sender->Permission('Garden.Settings.Manage');
        $Sender->SetData('PluginDescription',$this->GetPluginKey('Description'));

        $Validation = new Gdn_Validation();
        $ConfigurationModel = new Gdn_ConfigurationModel($Validation);
        $ConfigurationModel->SetField(array(
            'Plugin.GeoIP.doLogin'       => false,
            'Plugin.GeoIP.doDiscussions' => false,
        ));

        // Set the model on the form.
        $Sender->Form->SetModel($ConfigurationModel);

        // If seeing the form for the first time...
        if ($Sender->Form->AuthenticatedPostBack() === false) {
            // Apply the config settings to the form.
            $Sender->Form->SetData($ConfigurationModel->Data);

        } else {

            // @todo Set proper validation rules.
            //$ConfigurationModel->Validation->ApplyRule('Plugin.Example.RenderCondition', 'Required');
            //$ConfigurationModel->Validation->ApplyRule('Plugin.Example.TrimSize', 'Required');
            //$ConfigurationModel->Validation->ApplyRule('Plugin.Example.TrimSize', 'Integer');

            $Saved = $Sender->Form->Save();
            if ($Saved) {
                $Sender->StatusMessage = T("Your changes have been saved.");
            }
        }


        $Sender->Render($this->GetView('geoip.php'));
    }

    public function Controller_import($Sender) {

        echo "<p>Importing GeoIP CSV into MySQL!</p>\n";

        $imported = $this->import();
        if ($imported == false) {
            trigger_error("Failed to Import GeoIP data into MySQL in ".__METHOD__."()!", E_USER_WARNING);
            return false;
        }

        exit(__METHOD__);
    }


    /**
     * Load GeoIP data upon login.
     *
     * @param $Sender Referencing object
     * @param array $Args Arguments provided
     * @return bool Returns true on success, false on failure.
     */
    public function UserModel_AfterSignIn_Handler($Sender, $Args=[]) {

        // Check IF feature is enabled for this plugin:
        if (C('Plugin.GeoIP.doLogin')==false) {
            return false;
        }

        $userID = Gdn::Session()->User->UserID;
        if (empty($user)) {
            return false;
        }

        $this->setUserMetaGeo($userID);

        return true;
    }

    public function Base_AuthorInfo_Handler($Sender, $Args=[]) {

        // Check IF feature is enabled for this plugin:
        if (C('Plugin.GeoIP.doDiscussions')==false) {
            return false;
        }

        // Get IP based on context:
        if (!empty($Args['Comment']->InsertIPAddress)) { // If author is from comment.
            $targetIP = $Args['Comment']->InsertIPAddress;
        } else if (!empty($Args['Discussion']->InsertIPAddress)) { // If author is from discussion.
            $targetIP = $Args['Discussion']->InsertIPAddress;
        } else {
            return false;
        }

        // Make sure target IP is in local cache:
        if (!isset($this->query->localCache[$targetIP])) {
            return false;
        }

        // Get Country Code:
        if (!empty($this->query->localCache[$targetIP])) {
            $countryCode  = strtolower($this->query->localCache[$targetIP]['country_iso_code']);
            $countryName  = $this->query->localCache[$targetIP]['country_name'];

            // Echo Image:
            if (!empty($countryCode)) {
                echo Img("/plugins/GeoIP/design/flags/{$countryCode}.png", ['alt'=>"({$countryName})", 'title'=>$countryName]);
            }
        }

        return;
    }

    public function DiscussionController_BeforeDiscussionDisplay_Handler($Sender, $Args=[]) {

        // Check IF feature is enabled for this plugin:
        if (C('Plugin.GeoIP.doDiscussions')==false) {
            return false;
        }

        // Create list of IPs from this discussion we want to look up.
        $ipList = [$Args['Discussion']->InsertIPAddress]; // Add discussion IP.
        foreach ($Sender->Data('Comments')->result() as $comment) {
            if(empty($comment->InsertIPAddress)) continue;
            $ipList[] = $comment->InsertIPAddress;
        }

        // Get IP information for given IP list:
        $this->query->get($ipList);
//echo "<pre>Data ".print_r($data,true)."</pre>\n";
//echo "<pre>localCache: ".print_r($this->query->localCache,true)."</pre>\n";

        return true;
    }

    /**
     * Import GeoIP City Lite from Max Mind into MySQL.
     *
     * @return bool Returns TRUE on Success, FALSE on failure.
     */
    private function import() {
        // Do Import:
        return $this->import->run();;
    }


    /**
     * Sets the user GeoIP information to UserMeta data.
     *
     * @param $userID
     * @return bool
     */
    private function setUserMetaGeo($userID) {
        if (empty($userID) OR !is_numeric($userID)) {
            tigger_error("Invalid UserID passed to ".__METHOD__."()");
            return false;
        }

        $userInfo = GDN::UserModel()->GetID($userID);
        if (empty($userInfo) OR (!is_array($userID) && !is_object($userInfo))) {
            trigger_error("Could not load user info for given UserID={$userID} in ".__METHOD__."()!", E_USER_WARNING);
            return false;
        }

        $userIP = $userInfo->LastIPAddress;
        if (empty($userIP)) {
            trigger_error("No IP address on record for target userID={$userID} in ".__METHOD__."()!", E_USER_NOTICE);
            return false;
        }
        //echo "<p>User IP: '{$userIP}'</p>\n";

        $ipInfo = self::ipQuery($userIP,true,true);
        if (empty($ipInfo)) {
            trigger_error("Failed to get IP info in ".__METHOD__."()");
            return false;
        }
        //echo "<pre>IP Info ".print_r($ipInfo,true)."</pre>\n";

        GDN::userMetaModel()->setUserMeta($userID, 'geo_country', $ipInfo['country_code']);
        GDN::userMetaModel()->setUserMeta($userID, 'geo_latitude', $ipInfo['latitude']);
        GDN::userMetaModel()->setUserMeta($userID, 'geo_longitude', $ipInfo['longitude']);
        GDN::userMetaModel()->setUserMeta($userID, 'geo_city', utf8_encode($ipInfo['city']));
        GDN::userMetaModel()->setUserMeta($userID, 'geo_updated', time());

        return true;
    }

    /**
     * Gets user's GeoIP based information from user-meta data.
     *
     * @param $userID Target user's ID number.
     * @return array|bool Returns array of information or false on failure.
     */
    private function getUserMetaGeo($userID, $field='geo_%') {
        if (empty($userID) OR !is_numeric($userID)) {
            tigger_error("Invalid UserID passed to ".__METHOD__."()", E_USER_WARNING);
            return false;
        }

        $meta  = GDN::userMetaModel()->getUserMeta($userID, $field);
        if (empty($meta)) {
            return false;
        }

        $output = [];
        foreach ($meta as $var => $value) {
            if (substr($var, 0, strlen('geo_')) == 'geo_') { // Make sure only to return geo_ info...
                // $output[substr($var,strlen('geo_'))] = $value;
                $output[$var] = $value;
            }
        }

        return $output;
    }


} // Closes GeoipPlugin.
