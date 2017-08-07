<?php if (!defined('APPLICATION')) exit();
echo $this->Form->open();
echo $this->Form->errors();
?>
   <ul>
      <li>
         <?php
         echo $this->Form->label('Database');
         echo $this->Form->textBox('Database');
         ?>
      </li>
      <li>
         <?php
         echo $this->Form->label('Table Prefix', 'Prefix');
         echo $this->Form->textBox('Prefix');
         ?>
      </li>
      <li>
         <?php echo $this->Form->label('Legacy Slug', 'LegacySlug'); ?>
         <div class="Info"><?php echo t('ForumMerge.Merge.LegacySlug', 'Optional. Enter a slug used to associate all content from this source.  All sources should use a unique slug.  This will be used for legacy redirects.  If specified, all incoming ForeignIDs for categories, comments and discussions will be lost.'); ?></div>
         <?php echo $this->Form->textBox('LegacySlug'); ?>
      </li>
      <!--<li>
      <?php
      echo $this->Form->checkBox('MergeCategories', t('Merge categories'));
      ?>
   </li>-->
   </ul>
<?php
echo $this->Form->close('Begin');
