<?php if (!defined('APPLICATION')) exit();
echo $this->Form->Open();
echo $this->Form->Errors();
?>
<div class="header-block">
   <h1><?php echo t('Spoof'); ?></h1>
</div>
<ul>
   <li class="form-group">
      <?php
      echo $this->Form->labelWrap('Username or UserID to Spoof', 'UserReference');
      echo $this->Form->textBoxWrap('UserReference');
      ?>
   </li>
   <li class="form-group">
      <?php
      echo $this->Form->labelWrap('Your Email', 'Email');
      echo $this->Form->textBoxWrap('Email');
      ?>
   </li>
   <li class="form-group">
      <?php echo $this->Form->labelWrap('Your Password', 'Password'); ?>
      <div class="input-wrap">
         <?php echo $this->Form->Input('Password', 'password'); ?>
      </div>
   </li>
</ul>
<?php echo $this->Form->Close('Go'); ?>
