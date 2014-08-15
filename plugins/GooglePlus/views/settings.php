<?php if (!defined('APPLICATION')) exit(); ?>
<style type="text/css">
.Configuration {
   margin: 0 20px 20px;
   background: #f5f5f5;
   float: left;
}
.ConfigurationForm {
   padding: 20px;
   float: left;
}
#Content form .ConfigurationForm ul {
   padding: 0;
}
#Content form .ConfigurationForm input.Button {
   margin: 0;
}
.ConfigurationHelp {
   border-left: 1px solid #aaa;
   margin-left: 400px;
   padding: 20px;
}
.ConfigurationHelp img {
   width: 99%;
}
.ConfigurationHelp a img {
    border: 1px solid #aaa;
}
.ConfigurationHelp a:hover img {
    border: 1px solid #777;
}
.ConfigurationHelp ol {
    list-style-type: decimal;
    padding-left: 20px;
}
input.CopyInput {
   font-family: monospace;
   color: #000;
   width: 240px;
   font-size: 12px;
   padding: 4px 3px;
}
</style>
   <div class="Help Aside">
      <?php
      echo '<h2>', T('Need More Help?'), '</h2>';
      echo '<ul>';
      echo Wrap(Anchor('Using OAuth 2.0 for Login (OpenID Connect)', 'https://developers.google.com/accounts/docs/OAuth2Login'), 'li');
      echo Wrap(Anchor('Google Developers Console', 'https://console.developers.google.com/'), 'li');
      echo '</ul>';
      ?>
   </div>
<h1><?php echo $this->Data('Title'); ?></h1>
<div class="Configuration">
   <div class="ConfigurationForm">
   <p><strong>Google Plus Settings</strong></p>
<?php
$Cf = $this->ConfigurationModule;

$Cf->Render();
?>
 </div>
   <div class="ConfigurationHelp">
      <p><strong>How to set up Google Plus</strong></p>
	  
      <ol>
        <li>Got to https://code.google.com/apis/console</li>
		<li>Create a project</li>
		<li>Select credentials under APIS & Auth</li>
		<li>Create New ClientID</li>
		<li>Select Web Application as Type</li>
		<li>Authorized Javascript Origins URL will be your Vanilla Forums URL:  <input type="text" class="CopyInput" value="<?php echo rtrim(Gdn::Request()->Domain(), '/').'/'; ?>" /></li>
		<li>Authorized redirect URL will be:  <input type="text" class="CopyInput" value="<?php echo rtrim(Gdn::Request()->Domain(), '/').'/entry/googleplus'; ?>" /></li>
		<li>Copy over Client ID and Secret into appropriate fields in Vanilla Dashboard.</li>
      </ol>
      <p><?php echo Anchor(Img('/plugins/GooglePlus/design/Google_Developers_Console.png', array('style' => 'max-width: 529px;')), '/plugins/Twitter/design/help-consumervalues.png', array('target' => '_blank')); ?></p>
   </div>
</div>   