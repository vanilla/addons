<?php if (!defined('APPLICATION')) exit(); ?>
<div class="Whispers-Form">
   <?php
   $Conversations = $this->Conversations;

   echo $this->Form->CheckBox('Whisper', T('Whisper'));
   //'<h3>'.T('Whisper').' <span class="Optional">'.T('(optional)').'</span></h3>';
   echo '<div id="WhisperForm">';

   if (count($Conversations) > 0) {
      
      echo '<ul>';

      foreach ($Conversations as $Conversation) {
         $Participants = GetValue('Participants', $Conversation);
         $ConversationName = '';
         foreach ($Participants as $User) {
            $ConversationName = ConcatSep(', ', $ConversationName, htmlspecialchars(GetValue('Name', $User)));
         }

         echo '<li>'.$this->Form->Radio('ConversationID', $ConversationName, array('Value' => GetValue('ConversationID', $Conversation))).'</li>';
      }
      echo '<li>'.$this->Form->Radio('ConversationID', T('New Whisper'), array('Value' => '')).'</li>';

      echo '</ul>';
   }

   echo Wrap($this->Form->TextBox('To', array('MultiLine' => TRUE, 'class' => 'MultiComplete')), 'div', array('class' => 'TextBoxWrapper'));
   
   echo '</div>';
   ?>
</div>