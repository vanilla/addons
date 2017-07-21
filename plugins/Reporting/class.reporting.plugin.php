<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Vanilla Forums Inc.
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
*/

class ReportingPlugin extends Gdn_Plugin {

   const BUTTON_TYPE_REPORT = 'report';
   const BUTTON_TYPE_AWESOME = 'awesome';

   public function __construct() {
      parent::__construct();
      $this->ReportEnabled = C('Plugins.Reporting.ReportEnabled', TRUE);
      $this->AwesomeEnabled = C('Plugins.Reporting.AwesomeEnabled', TRUE);
   }

   /*
    * Plugin control
    */
   public function PluginController_Reporting_Create($sender) {
      $sender->Form = new Gdn_Form();
      $this->Dispatch($sender, $sender->RequestArgs);
   }

   /**
    * Add to dashboard menu.
    * @param DashboardNavModule $sender
    */
   public function dashboardNavModule_init_handler($sender) {
      $sender->addLinkToSectionIf('Garden.Settings.Manage', 'Moderation', t('Community Reporting'), 'plugin/reporting', 'moderation.community-reporting');
   }

   /**
   * Settings screen placeholder
   *
   * @param mixed $sender
   */
   public function Controller_Index($sender) {
      Gdn_Theme::section('Moderation');
      $sender->Permission('Garden.Settings.Manage');
      $sender->Title('Community Reporting');
      $sender->AddCssFile('reporting.css', 'plugins/Reporting');

      // Check to see if the admin is toggling a feature
      $feature = GetValue('1', $sender->RequestArgs);
      $command = GetValue('2', $sender->RequestArgs);
      $transientKey = Gdn::request()->get('TransientKey');
      if (Gdn::Session()->ValidateTransientKey($transientKey)) {
         if (in_array($feature, ['awesome', 'report'])) {
            SaveToConfig(
               'Plugins.Reporting.'.ucfirst($feature).'Enabled',
               $command == 'disable' ? FALSE : TRUE
            );

            redirectTo('plugin/reporting');
         }
      }

      $categoryModel = new CategoryModel();
      $sender->SetData('Plugins.Reporting.Data', [
         'ReportEnabled'   => $this->ReportEnabled,
         'AwesomeEnabled'  => $this->AwesomeEnabled
      ]);

      $sender->Render($this->GetView('settings.php'));
   }

   /**
   * Handle report actions
   *
   * @param mixed $sender
   */
   public function Controller_Report($sender) {
      if (!($userID = Gdn::Session()->UserID))
         throw new Exception(T('Cannot report content while not logged in.'));

      $userName = Gdn::Session()->User->Name;

      $arguments = $sender->RequestArgs;
      if (sizeof($arguments) != 4)
         throw new Exception(sprintf(T("Incorrect arg-count. Doesn't look like a legit request. Got %s arguments, expected 4."),sizeof($arguments)));

      list($eventType, $context, $elementID, $encodedURL) = $arguments;
      $uRL = base64_decode(str_replace('-','=',$encodedURL));

      $reportElementModelName = ucfirst($context).'Model';
      if (!class_exists($reportElementModelName))
         throw new Exception(T('Cannot report on an entity with no model.'));

      // Ok we're good to go for sure now

      $reportElementModel = new $reportElementModelName();
      $reportElement = $reportElementModel->GetID($elementID);

      $elementTitle = Gdn_Format::Text(GetValue('Name', $reportElement, NULL), FALSE);
      $elementExcerpt = Gdn_Format::Text(GetValue('Body', $reportElement, NULL), FALSE);
      if (!is_null($elementExcerpt)) {
         $original = strlen($elementExcerpt);
         $elementExcerpt = substr($elementExcerpt, 0, 140);
         if ($original > strlen($elementExcerpt))
            $elementExcerpt .= "...";
      }

      if (is_null($elementTitle))
         $elementTitle = $elementExcerpt;

      $elementShortTitle = (strlen($elementTitle) <= 143) ? $elementTitle : substr($elementTitle, 0, 140).'...';

      $elementAuthorID = GetValue('InsertUserID', $reportElement);
      $elementAuthor = Gdn::UserModel()->GetID($elementAuthorID);
      $elementAuthorName = GetValue('Name', $elementAuthor);

      $regardingAction = C('Plugins.Reporting.ReportAction', FALSE);
      $regardingActionSupplement = C('Plugins.Reporting.ReportActionSupplement', FALSE);

      $reportingData = [
         'Type'            => 'report',
         'Context'         => $context,
         'Element'         => $reportElement,
         'ElementID'       => $elementID,
         'ElementTitle'    => $elementTitle,
         'ElementExcerpt'  => $elementExcerpt,
         'ElementAuthor'   => $elementAuthor,
         'URL'             => $uRL,
         'UserID'          => $userID,
         'UserName'        => $userName,
         'Action'          => $regardingAction,
         'Supplement'      => $regardingActionSupplement
      ];

      if ($sender->Form->AuthenticatedPostBack()) {
         $regardingTitle = sprintf(T("Reported: '{RegardingTitle}' by %s"), $elementAuthorName);
         $reportingData['Title'] = $regardingTitle;
         $reportingData['Reason'] = $sender->Form->GetValue('Plugin.Reporting.Reason');

         $this->EventArguments['Report'] = &$reportingData;
         $this->FireEvent('BeforeRegarding');

         $regarding = Gdn::Regarding()
            ->That($reportingData['Context'], $reportingData['ElementID'], $reportingData['Element'])
            ->ReportIt()
            ->ForCollaboration($reportingData['Action'], $reportingData['Supplement'])
            ->Entitled($reportingData['Title'])
            ->From($reportingData['UserID'])
            ->Because($reportingData['Reason'])
            ->Located(TRUE) // build URL automatically
            ->Commit();

         $sender->InformMessage('<span class="InformSprite Skull"></span>'.T('Your complaint has been registered. Thankyou!'), 'HasSprite Dismissable AutoDismiss');
      }

      $sender->SetData('Plugin.Reporting.Data', $reportingData);
      $sender->Render($this->GetView('report.php'));
   }

   /**
   * Handle awesome actions
   *
   * @param mixed $sender
   */
   public function Controller_Awesome($sender) {
      if (!($userID = Gdn::Session()->UserID))
         throw new Exception(T('Cannot report content while not logged in.'));

      $userName = Gdn::Session()->User->Name;

      $arguments = $sender->RequestArgs;
      if (sizeof($arguments) != 4)
         throw new Exception(sprintf(T("Incorrect arg-count. Doesn't look like a legit request. Got %s arguments, expected 4."),sizeof($arguments)));

      list($eventType, $context, $elementID, $encodedURL) = $arguments;
      $uRL = base64_decode(str_replace('-','=',$encodedURL));

      $reportElementModelName = ucfirst($context).'Model';
      if (!class_exists($reportElementModelName))
         throw new Exception(T('Cannot report on an entity with no model.'));

      // Ok we're good to go for sure now

      $reportElementModel = new $reportElementModelName();
      $reportElement = $reportElementModel->GetID($elementID);

      $elementTitle = Gdn_Format::Text(GetValue('Name', $reportElement, NULL), FALSE);
      $elementExcerpt = Gdn_Format::Text(GetValue('Body', $reportElement, NULL), FALSE);
      if (!is_null($elementExcerpt)) {
         $original = strlen($elementExcerpt);
         $elementExcerpt = substr($elementExcerpt, 0, 140);
         if ($original > strlen($elementExcerpt))
            $elementExcerpt .= "...";
      }

      if (is_null($elementTitle))
         $elementTitle = $elementExcerpt;

      $elementShortTitle = (strlen($elementTitle) <= 143) ? $elementTitle : substr($elementTitle, 0, 140).'...';

      $elementAuthorID = GetValue('InsertUserID', $reportElement);
      $elementAuthor = Gdn::UserModel()->GetID($elementAuthorID);
      $elementAuthorName = GetValue('Name', $elementAuthor);

      $reportingData = [
         'Context'         => $context,
         'ElementID'       => $elementID,
         'ElementTitle'    => $elementTitle,
         'ElementExcerpt'  => $elementExcerpt,
         'ElementAuthor'   => $elementAuthor,
         'URL'             => $uRL,
         'UserID'          => $userID,
         'UserName'        => $userName
      ];

      $regardingAction = C('Plugins.Reporting.AwesomeAction', FALSE);
      $regardingActionSupplement = C('Plugins.Reporting.AwesomeActionSupplement', FALSE);

      if ($sender->Form->AuthenticatedPostBack()) {
         $regardingTitle = sprintf(T("Awesome: '{RegardingTitle}' by %s"), $elementAuthorName);
         $regarding = Gdn::Regarding()
            ->That($context, $elementID, $reportElement)
            ->ItsAwesome()
            ->ForCollaboration($regardingAction, $regardingActionSupplement)
            ->Entitled($regardingTitle)
            ->From(Gdn::Session()->UserID)
            ->Because($sender->Form->GetValue('Plugin.Reporting.Reason'))
            ->Located(TRUE) // build URL automatically
            ->Commit();

         $sender->InformMessage('<span class="InformSprite Heart"></span>'.T('Your suggestion has been registered. Thankyou!'), 'HasSprite Dismissable AutoDismiss');
      }

      $sender->SetData('Plugin.Reporting.Data', $reportingData);
      $sender->Render($this->GetView('awesome.php'));
   }

   /*
    * UI injection
    */

   /**
    * Create 'Infraction' link for comments in a discussion.
    *
    * Clickable for those who can give infractions, otherwise just a UI marker
    * for regular users.
    */
   public function DiscussionController_AfterReactions_Handler($sender) {
      $context = $sender->EventArguments['Type'];
      $text = FALSE;
      $style = [];

      $context = strtolower($sender->EventArguments['Type']);

      if ($this->ReportEnabled)
         $this->OutputButton(self::BUTTON_TYPE_REPORT, $context, $sender);
      if ($this->AwesomeEnabled)
         $this->OutputButton(self::BUTTON_TYPE_AWESOME, $context, $sender);

      if ($this->ReportEnabled || $this->AwesomeEnabled)
         $sender->AddCssFile('reporting.css', 'plugins/Reporting');
   }

   protected function OutputButton($buttonType, $context, $sender) {
      // Signed in users only. No guest reporting!
      if (!Gdn::Session()->UserID) return;

      // Reporting permission checks

      if (!is_object($sender->EventArguments['Author'])) {
         $elementAuthorID = 0;
         $elementAuthor = 'Unknown';
      } else {
         $elementAuthorID = $sender->EventArguments['Author']->UserID;
         $elementAuthor = $sender->EventArguments['Author']->Name;
      }

      switch ($context) {
         case 'comment':
            $elementID = $sender->EventArguments['Comment']->CommentID;
            $uRL = "/discussion/comment/{$elementID}/#Comment_{$elementID}";
            break;

         case 'discussion':
            $elementID = $sender->EventArguments['Discussion']->DiscussionID;
            $uRL = "/discussion/{$elementID}/".Gdn_Format::Url($sender->EventArguments['Discussion']->Name);
            break;

         case 'conversation':
            break;

         default:
            return;
      }

      $buttonTitle = T(ucfirst($buttonType));
      $containerCSS = $buttonTitle.'Post';
      $encodedURL = str_replace('=','-',base64_encode($uRL));
      $eventUrl = "plugin/reporting/{$buttonType}/{$context}/{$elementID}/{$encodedURL}";

      //$Sender->EventArguments['CommentOptions'][$ButtonTitle] = array('Label' => $ButtonTitle, 'Url' => "plugin/reporting/{$ButtonType}/{$Context}/{$ElementID}/{$EncodedURL}", $ContainerCSS.' ReportContent Popup');

      $spriteType = "React".ucfirst($buttonType);
      $text = Anchor(Sprite($spriteType, 'ReactSprite').$buttonTitle, $eventUrl, "ReactButton React {$containerCSS} Popup");
      echo Bullet();
      echo $text;
   }

   /*
    * Regarding handlers
    */

   public function Gdn_Regarding_RegardingDisplay_Handler($sender) {
      $event = $sender->MatchEvent(['report', 'awesome'], '*');
      if ($event === FALSE)
         return;

      $entity = GetValue('Entity', $event);
      $regardingData = GetValue('RegardingData', $event);
      $regardingType = GetValue('Type', $regardingData);
      $reportInfo = [
         'ReportingUser'         => Gdn::UserModel()->GetID(GetValue('InsertUserID', $regardingData)),
         'EntityType'            => T(ucfirst(GetValue('ForeignType', $regardingData))),
         'ReportedUser'          => Gdn::UserModel()->GetID(GetValue('InsertUserID', $entity)),
         'ReportedTime'          => GetValue('DateInserted', $regardingData),
         'EntityURL'             => GetValue('ForeignURL', $regardingData, NULL)
      ];

      if (!is_null($reportedReason = GetValue('Comment', $regardingData, NULL)))
         $reportInfo['ReportedReason'] = $reportedReason;

      if (!is_null($reportedContent = GetValue('OriginalContent', $regardingData, NULL)))
         $reportInfo['OriginalContent'] = $reportedContent;

      Gdn::Controller()->SetData('RegardingSender', $sender);
      Gdn::Controller()->SetData('Entity', $entity);
      Gdn::Controller()->SetData('RegardingData', $regardingData);
      Gdn::Controller()->SetData('ReportInfo', $reportInfo);
      echo Gdn::Controller()->FetchView("{$regardingType}-regarding",'','plugins/Reporting');
   }

   public function Gdn_Regarding_RegardingActions_Handler($sender) {
      $event = $sender->MatchEvent('report', '*');
      if ($event === FALSE)
         return;

      // Add buttonz hurr?
   }

   /*
    * Regarding extensions
    */

   public function Gdn_RegardingEntity_ReportIt_Create($sender) {
      return $sender->ActionIt('Report');
   }

   public function Gdn_RegardingEntity_ItsAwesome_Create($sender) {
      return $sender->ActionIt('Awesome');
   }

   public function Setup() {

   }

}
