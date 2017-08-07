<?php if (!defined('APPLICATION')) exit();

class FacebookIDPlugin extends Gdn_Plugin {
   /** @var array */
   public $FacebookIDs = [];

   public function userInfoModule_onBasicInfo_handler($sender, $args) {
      if (Gdn::session()->checkPermission('Plugins.FacebookID.View')) {
         // Grab the facebook ID.
         $facebookID = Gdn::sql()->getWhere(
            'UserAuthentication',
            ['ProviderKey' => 'facebook', 'UserID' => $sender->User->UserID]
         )->value('ForeignUserKey', t('n/a'));

         echo '<dt class="Value">'.t('Facebook ID').'</dt><dd>'.$facebookID.'</dd>';
      }
   }

   /**
    * Show FacebookID on comments.
    */
   public function base_commentInfo_handler($sender, $args) {
      if (!Gdn::session()->checkPermission('Plugins.FacebookID.View'))
         return;

      if (!isset($sender->Data['Discussion']))
         return;

      if (!$this->FacebookIDs)
         $this->FacebookIDs = $this->getFacebookIDs([$sender->Data['Discussion'], $sender->Data['Comments']], 'InsertUserID');


      $userID = getValue('InsertUserID',$sender->EventArguments['Object'],'0');
      $facebookID = getValue($userID, $this->FacebookIDs, t('n/a'));
      echo '<span>'.t('Facebook ID').': '.$facebookID.'</span> ';
   }

   /**
    * Show FacebookID on discussions (OP).
    */
   public function base_discussionInfo_handler($sender, $args) {
      $this->base_commentInfo_handler($sender, $args);
   }

   /**
    *
    * @param Gdn_Controller $sender
    * @param <type> $args
    * @return <type>
    */
   public function userController_render_before($sender, $args) {
      if (!in_array($sender->RequestMethod, ['index', 'browse']))
         return;
      if (!Gdn::session()->checkPermission('Plugins.FacebookID.View'))
         return;
   }

   public function getFacebookIDs($datas, $userIDColumn) {
      $userIDs = [];
      foreach ($datas as $data) {
         if ($userID = getValue($userIDColumn, $data))
            $userIDs[] = $userID;
         else {
            $iDs = array_column($data, $userIDColumn);
            $userIDs = array_merge($userIDs, $iDs);
         }
      }

      $fbIDs = Gdn::sql()
         ->whereIn('UserID', array_unique($userIDs))
         ->getWhere(
         'UserAuthentication',
         ['ProviderKey' => 'facebook'])->resultArray();

      $result = array_column($fbIDs, 'ForeignUserKey', 'UserID');
      return $result;
   }

}
