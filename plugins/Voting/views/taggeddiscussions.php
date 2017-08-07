<?php if (!defined('APPLICATION')) exit();
include($this->fetchViewLocation('helper_functions', 'discussions', 'vanilla'));
?>
<div class="TaggedHeading"><?php printf("Questions tagged with '%s'", $this->Tag); ?></div>
<?php if ($this->DiscussionData->numRows() > 0) { ?>
<ul class="DataList Discussions">
   <?php include($this->fetchViewLocation('discussions')); ?>
</ul>
<?php
   echo $this->Pager->toString('more');
} else {
   ?>
   <div class="Empty"><?php printf(t('No items tagged with %s.'), $this->Tag); ?></div>
   <?php
}
