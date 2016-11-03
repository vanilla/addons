<?php if (!defined('APPLICATION')) exit(); ?>
<div class="FormTitleWrapper ChangeAuthorForm">
   <?php
   echo wrap($this->data('Title'), 'h1', ['class' => 'H']);

   echo '<div class="FormWrapper">';
   echo $this->Form->open();
   echo $this->Form->errors();

   echo '<div class="P">';
   echo $this->Form->label('New Author', 'Author');
   echo wrap($this->Form->textBox('Author', ['class' => 'MultiComplete']), 'div', ['class' => 'TextBoxWrapper']);
   echo '</div>';

   echo $this->Form->close('Change Author', '', ['class' => 'Button Primary']);
   echo '</div>';
   ?>
</div>