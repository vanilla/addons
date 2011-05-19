<?php if (!defined('APPLICATION')) exit(); ?>
<h1><?php echo $this->Data('Title'); ?></h1>
<?php
echo $this->Form->Open(), $this->Form->Errors();
?>
<ul>
   <li>
     <?php
     echo $this->Form->Label('AuthenticationKey', 'AuthenticationKey'),
     '<div class="Info">'.T('The client ID uniqely identifies the site.', 'The client ID uniqely identifies the site. You can generate a new ID with the button at the bottom of this page.').'</div>',
      $this->Form->TextBox('AuthenticationKey');
     ?>
   </li>
   <li>
     <?php
     echo $this->Form->Label('AssociationSecret', 'AssociationSecret'),
     '<div class="Info">'.T('The secret secures the sign in process.', 'The secret secures the sign in process. Do <b>NOT</b> give the secret out to anyone.').'</div>',
      $this->Form->TextBox('AssociationSecret');
     ?>
   </li>
   <li>
     <?php
     echo $this->Form->Label('Site Name', 'Name'),
     '<div class="Info">'.T('Enter a short name for the site.', 'Enter a short name for the site. This is displayed the signin buttons.').'</div>',
      $this->Form->TextBox('Name');
     ?>
   </li>
   <li>
     <?php
     echo $this->Form->Label('Authenticate Url', 'AuthenticateUrl'),
     '<div class="Info">'.T('The location of the jsonp formatted authentication data.').'</div>',
     $this->Form->TextBox('AuthenticateUrl', array('class' => 'InputBox BigInput'));
     ?>
   </li>
   <li>
     <?php
     echo $this->Form->Label('Sign In Url', 'SignInUrl'),
     '<div class="Info">'.T('The url that users use to sign in.').'</div>',
      $this->Form->TextBox('SignInUrl', array('class' => 'InputBox BigInput'));
     ?>
   </li>
   <li>
     <?php
     echo $this->Form->Label('Register Url', 'RegisterUrl'),
     '<div class="Info">'.T('The url that users go to to register for a new account.').'</div>',
      $this->Form->TextBox('RegisterUrl', array('class' => 'InputBox BigInput'));
     ?>
   </li>
</ul>

<?php
echo '<div class="Buttons">';
echo $this->Form->Button('Save');
echo $this->Form->Button('Generate Client ID and Secret', array('Name' => $this->Form->EscapeFieldName('Generate')));
echo '</div>';

echo $this->Form->Close();