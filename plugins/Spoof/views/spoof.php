<?php if (!defined('APPLICATION')) exit();
echo $this->Form->Open();
echo $this->Form->Errors();
?>
<ul>
   <li>
      <?php
      echo $this->Form->Label('Username or UserID to Spoof', 'UserReference');
      echo $this->Form->TextBox('UserReference');
      ?>
   </li>
   <li>
      <?php
      echo $this->Form->Label('Your Email', 'Email');
      echo $this->Form->TextBox('Email');
      ?>
   </li>
   <li>
      <?php
      echo $this->Form->Label('Your Password', 'Password');
      echo $this->Form->Input('Password', 'password');
      ?>
   </li>
</ul>
<?php
echo $this->Form->Close('Go');