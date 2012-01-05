<?php if (!defined('APPLICATION')) exit();

echo '<h1>'.$this->Data('Title').'</h1>';

echo '<div class="Info">'.
   T('Continuing this discussion in private puts all new comments into a private conversation.',
   'Continuing this discussion in private puts all new comments into a private conversation. 
    <b>Only</b> users that you select below will be able to view and add new comments. This does not affect the comments already in this discussion.').
   '</div>';

echo $this->Form->Open();
echo $this->Form->Errors();

echo '<div class="P Info2">'.
   T('Select the people you want in the conversation.').
   '</div>';

echo '<ul class="CheckBoxList">';

$CheckedIDs = $this->Form->GetValue('UserID', array());
if (!is_array($CheckedIDs))
   $CheckedIDs = array();

foreach ($this->Data('Users') as $User) {
   $Label = htmlspecialchars($User['Name']);
   
   $CssClass = '';
   if ($User['UserID'] == $this->Data('Discussion.InsertUserID')) {
      $CssClass = 'Discussion-Starter';
      
      $Type = $this->Data('Discussion.Type');
      if (!$Type)
         $Type = 'Discussion';
      
      $Label .= ' ('.T("started the $Type", 'Started the discussion').')';
   }
   
   $Attributes = array('Value' => $User['UserID']);
   $Checked = in_array($User['UserID'], $CheckedIDs);
   if ($Checked)
      $Attributes['checked'] = 'checked';
   
   echo '<li class="'.$CssClass.'"><div class="P">'.
      $this->Form->CheckBox('UserID[]', $Label, $Attributes);
   echo '</div></li>';
}

echo '</ul>';

echo '<div class="Buttons">'.
   $this->Form->Button(T('Continue')).
   '</div>';

echo $this->Form->Close();