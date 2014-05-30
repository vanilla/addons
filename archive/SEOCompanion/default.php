<?php 

$PluginInfo['SEOCompanion'] = array(

   'Description' => 'SEOCompanion is your Vanilla Search Engine Optimisation\'s best friend. Right now, it adds metatags to your header, automatically generated from the tags and title of a discussion, and if none is present, it uses the default (look in the plugin default.php to hardcode them.)',
   'Version' => '0.2.1',   
   'RequiredPlugins' => FALSE, // This is an array of plugin names/versions that this plugin requires
   'HasLocale' => FALSE, // Does this plugin have any locale definitions?
   'RegisterPermissions' => FALSE, // Permissions that should be added for this plugin.
   'SettingsUrl' => FALSE, // Url of the plugin's settings page.
   'SettingsPermission' => FALSE, // The permission required to view the SettingsUrl.
   'Author' => "Alexandre Plennevaux",
   'AuthorEmail' => 'alexandre@pixeline.be',
   'AuthorUrl' => 'http://pixeline.be'
);

class SEOCompanion extends Gdn_Plugin{

     //
  public function Base_Render_Before(&$Sender)
  {

  		//  CONFIGURATION - START
  		
  		$MetaDescriptionlimit = 20; // max. amount of words in the meta description. Should not be more than 50.
  		$DefaultDescription= "Welcome to my Vanilla 2 Forum."; // Default Meta Description
  		$DefaultTags = "forum vanilla"; // Default list of tags, blank space separated.
  		
  		// CONFIGURATION - END
  		
  		$Description = $Sender->Discussion->Body;
  		if(strlen($Description)<1){
				$Description = $DefaultDescription;
  		}
 		$Description = explode(' ', strip_tags($Description));
 		$Description = array_slice($Description,0,$MetaDescriptionlimit);
 		$Description = implode(' ',$Description);
 		$Sender->Head->AddTag('meta', array('name' => 'description', 'content'=>$Description));
  		
  		$Keywords = $Sender->Discussion->Tags;
  		if (count($Keywords)<1){
  			$Keywords = $DefaultTags;
  		}
  		$Keywords = explode(' ',$Keywords);
  		$Keywords = implode(', ',$Keywords);
  		$Sender->Head->AddTag('meta', array('name' => 'keywords', 'content'=>$Keywords));
  }
  
  public function Setup()
  {
  }
}