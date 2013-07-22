<?php if (!defined('APPLICATION')) exit();

/**
 * CountriesOnline plugin will display a user count per country from the last 
 * five (or n) minutes in a side panel.
 *
 * Note that it depends on the GeoIP module to function correctly; without it, 
 * only a simple count of online members will be displayed.
 *
 * @todo add options to plugin dashboard 
 * @todo JavaScript toggle if more than ten countries in panel
 */

$PluginInfo['CountriesOnline'] = array(
	'Name' => 'CountriesOnline',
	'Description' => "Display the user count per country from the last five (or n) minutes. This requires the Geoip module to be installed on the server.",
	'Version' => '1.0',
	'Author' => "Dane MacMillan",
	'RequiredTheme' => false, 
	'RequiredPlugins' => false,
	'HasLocale' => false,
	'SettingsUrl' => false, 
);

class CountriesOnlinePlugin extends Gdn_Plugin 
{
	// Default is five minute threshold--raise higher to include more stats. 
	protected $default_time_threshold;

	public function __construct() 
	{
		$this->default_time_threshold = 5 * 60;
	}
	
	/**
	 * Call in module here, to have it placed in the panel on every page that 
	 * has the panel area. No need to create custom controllers, so this is 
	 * good enough for the task. 
	 */
	public function Base_Render_Before(&$Sender) 
	{
		$Session = Gdn::Session();   
		$Controller = $Sender->ControllerName;

		// Only show on these pages 
		$ShowOnController = array(
			'discussioncontroller',
			'categoriescontroller',
			'discussionscontroller',
			'profilecontroller',
			'activitycontroller'
		);
		
		// Only display this info to signed in members.
		if ($Session->IsValid() 
		&& InArrayI($Controller, $ShowOnController)) 
		{
			$Sender->AddCssFile($this->GetResource('design/countriesonline.css', FALSE, FALSE));
			include_once(PATH_PLUGINS . DS .'CountriesOnline'. DS .'class.countriesonlinemodule.php');
			
			$CountriesOnlineModule = new CountriesOnlineModule($Sender);
			$time_threshold = C('Plugin.CountriesOnline.TimeThreshold', $this->default_time_threshold);
			$CountriesOnlineModule->GetData($time_threshold);
			$Sender->AddModule($CountriesOnlineModule);
		}
	}

	/**
	 * First time setup of CountriesOnline plugin.
	 */
	public function Setup() 
	{
		// Time threshold, in seconds, for signed in users grouped by country.  
		SaveToConfig('Plugin.CountriesOnline.TimeThreshold', $this->default_time_threshold);

		$Structure = Gdn::Structure();		
		$Structure->Table('CountriesOnline')
			->Column('UserID', 'int(11)', false, 'primary')
			->Column('CountryCode', 'char(2)', 'VF')
			->Column('Timestamp', 'int(10)', false, 'key')
		->Set(false, false); 
	}
	
	public function OnDisable() 
	{
		RemoveFromConfig('Plugin.CountriesOnline.TimeThreshold');
	}
}