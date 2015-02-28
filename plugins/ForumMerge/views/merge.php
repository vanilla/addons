<?php if (!defined('APPLICATION')) exit();
echo $this->Form->Open();
echo $this->Form->Errors();
?>
<ul>
   <li>
      <?php
      echo $this->Form->Label('Database');
      echo $this->Form->TextBox('Database');
      ?>
   </li>
   <li>
      <?php
      echo $this->Form->Label('Table Prefix', 'Prefix');
      echo $this->Form->TextBox('Prefix');
      ?>
   </li>
   <!--<li>
      <?php
      echo $this->Form->CheckBox('MergeCategories', T('Merge categories'));
      ?>
   </li>-->
</ul>
<?php
echo $this->Form->Close('Begin');