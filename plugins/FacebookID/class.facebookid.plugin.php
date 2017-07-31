<?php if (!defined('APPLICATION')) exit();

class FacebookIDPlugin extends Gdn_Plugin {
   /** @var array */
   public $FacebookIDs = [];

   public function UserInfoModule_OnBasicInfo_Handler($sender, $args) {
      if (Gdn::Session()->CheckPermission('Plugins.FacebookID.View')) {
         // Grab the facebook ID.
         $facebookID = Gdn::SQL()->GetWhere(
            'UserAuthentication',
            ['ProviderKey' => 'facebook', 'UserID' => $sender->User->UserID]
         )->Value('ForeignUserKey', T('n/a'));

         echo '<dt class="Value">'.T('Facebook ID').'</dt><dd>'.$facebookID.'</dd>';
      }
   }

   /**
    * Show FacebookID on comments.
    */
   public function Base_CommentInfo_Handler($sender, $args) {
      if (!Gdn::Session()->CheckPermission('Plugins.FacebookID.View'))
         return;

      if (!isset($sender->Data['Discussion']))
         return;

      if (!$this->FacebookIDs)
         $this->FacebookIDs = $this->GetFacebookIDs([$sender->Data['Discussion'], $sender->Data['Comments']], 'InsertUserID');


      $userID = GetValue('InsertUserID',$sender->EventArguments['Object'],'0');
      $facebookID = GetValue($userID, $this->FacebookIDs, T('n/a'));
      echo '<span>'.T('Facebook ID').': '.$facebookID.'</span> ';
   }

   /**
    * Show FacebookID on discussions (OP).
    */
   public function Base_DiscussionInfo_Handler($sender, $args) {
      $this->Base_CommentInfo_Handler($sender, $args);
   }

   /**
    *
    * @param Gdn_Controller $sender
    * @param <type> $args
    * @return <type>
    */
   public function UserController_Render_Before($sender, $args) {
      if (!in_array($sender->RequestMethod, ['index', 'browse']))
         return;
      if (!Gdn::Session()->CheckPermission('Plugins.FacebookID.View'))
         return;
   }

   public function GetFacebookIDs($datas, $userIDColumn) {
      $userIDs = [];
      foreach ($datas as $data) {
         if ($userID = GetValue($userIDColumn, $data))
            $userIDs[] = $userID;
         else {
            $iDs = array_column($data, $userIDColumn);
            $userIDs = array_merge($userIDs, $iDs);
         }
      }

      $fbIDs = Gdn::SQL()
         ->WhereIn('UserID', array_unique($userIDs))
         ->GetWhere(
         'UserAuthentication',
         ['ProviderKey' => 'facebook'])->ResultArray();

      $result = array_column($fbIDs, 'ForeignUserKey', 'UserID');
      return $result;
   }

}
