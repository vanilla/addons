<?php if (!defined('APPLICATION')) { exit(); } ?>
$Session = Gdn::session();
$CancelUrl = '/discussions';
if (c('Vanilla.Categories.Use') && is_object($this->Category)) {
    $CancelUrl = '/categories/'.urlencode($this->Category->UrlCode);
}

?>
<div id="DiscussionForm" class="FormTitleWrapper DiscussionForm">
   <?php
		if ($this->deliveryType() == DELIVERY_TYPE_ALL) {
		    echo wrap($this->data('Title'), 'h1', array('class' => 'H'));
		}

        echo '<div class="FormWrapper">';
        echo $this->Form->open();
        echo $this->Form->errors();
        $this->fireEvent('BeforeFormInputs');

       if ($this->ShowCategorySelector === true) {
           echo '<div class="P">';
               echo '<div class="Category">';
               echo $this->Form->label('Category', 'CategoryID'), ' ';
               echo $this->Form->categoryDropDown('CategoryID', array('Value' => val('CategoryID', $this->Category), 'CategoryData' => $this->data('AllowedCategories')));
               echo '</div>';
           echo '</div>';
       }

        echo '<div class="P">';
            echo $this->Form->label('Question', 'Name');
            echo wrap($this->Form->textBox('Name', array('maxlength' => 100, 'class' => 'InputBox BigInput')), 'div', array('class' => 'TextBoxWrapper'));
        echo '</div>';

        $this->fireEvent('BeforeBodyInput');
        echo '<div class="P">';
         echo $this->Form->bodyBox('Body', array('Table' => 'Discussion', 'FileUpload' => true));
        echo '</div>';

        $this->fireEvent('AfterDiscussionFormOptions');

        echo '<div class="Buttons">';
        $this->fireEvent('BeforeFormButtons');
        echo $this->Form->button((property_exists($this, 'Discussion')) ? 'Save' : 'Ask Question', array('class' => 'Button Primary DiscussionButton'));
        if (!property_exists($this, 'Discussion') || !is_object($this->Discussion) || (property_exists($this, 'Draft') && is_object($this->Draft))) {
            echo ' '.$this->Form->button('Save Draft', array('class' => 'Button Warning DraftButton'));
        }
        echo ' '.$this->Form->button('Preview', array('class' => 'Button Warning PreviewButton'));
        echo ' '.anchor(t('Edit'), '#', 'Button WriteButton Hidden')."\n";
        $this->fireEvent('AfterFormButtons');
        echo ' '.anchor(t('Cancel'), $CancelUrl, 'Button Cancel');
        echo '</div>';
        echo $this->Form->close();
        echo '</div>';
   ?>
</div>
