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
      <li>
         <?php echo $this->Form->Label('Legacy Slug', 'LegacySlug'); ?>
         <div class="Info"><?php echo T('ForumMerge.Merge.LegacySlug', 'Optional. Enter a slug used to associate all content from this source.  All sources should use a unique slug.  This will be used for legacy redirects.  If specified, all incoming ForeignIDs for categories, comments and discussions will be lost.'); ?></div>
         <?php echo $this->Form->TextBox('LegacySlug'); ?>
      </li>
      <!--<li>
      <?php
      echo $this->Form->CheckBox('MergeCategories', T('Merge categories'));
      ?>
   </li>-->
   </ul>
<?php
echo $this->Form->Close('Begin');
