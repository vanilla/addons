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
      $this->ReportEnabled = c('Plugins.Reporting.ReportEnabled', TRUE);
      $this->AwesomeEnabled = c('Plugins.Reporting.AwesomeEnabled', TRUE);
   }

   /*
    * Plugin control
    */
   public function pluginController_reporting_create($sender) {
      $sender->Form = new Gdn_Form();
      $this->dispatch($sender, $sender->RequestArgs);
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
   public function controller_Index($sender) {
      Gdn_Theme::section('Moderation');
      $sender->permission('Garden.Settings.Manage');
      $sender->title('Community Reporting');
      $sender->addCssFile('reporting.css', 'plugins/Reporting');

      // Check to see if the admin is toggling a feature
      $feature = getValue('1', $sender->RequestArgs);
      $command = getValue('2', $sender->RequestArgs);
      $transientKey = Gdn::request()->get('TransientKey');
      if (Gdn::session()->validateTransientKey($transientKey)) {
         if (in_array($feature, ['awesome', 'report'])) {
            saveToConfig(
               'Plugins.Reporting.'.ucfirst($feature).'Enabled',
               $command == 'disable' ? FALSE : TRUE
            );

            redirectTo('plugin/reporting');
         }
      }

      $categoryModel = new CategoryModel();
      $sender->setData('Plugins.Reporting.Data', [
         'ReportEnabled'   => $this->ReportEnabled,
         'AwesomeEnabled'  => $this->AwesomeEnabled
      ]);

      $sender->render($this->getView('settings.php'));
   }

   /**
   * Handle report actions
   *
   * @param mixed $sender
   */
   public function controller_Report($sender) {
      if (!($userID = Gdn::session()->UserID))
         throw new Exception(t('Cannot report content while not logged in.'));

      $userName = Gdn::session()->User->Name;

      $arguments = $sender->RequestArgs;
      if (sizeof($arguments) != 4)
         throw new Exception(sprintf(t("Incorrect arg-count. Doesn't look like a legit request. Got %s arguments, expected 4."),sizeof($arguments)));

      list($eventType, $context, $elementID, $encodedURL) = $arguments;
      $uRL = base64_decode(str_replace('-','=',$encodedURL));

      $reportElementModelName = ucfirst($context).'Model';
      if (!class_exists($reportElementModelName))
         throw new Exception(t('Cannot report on an entity with no model.'));

      // Ok we're good to go for sure now

      $reportElementModel = new $reportElementModelName();
      $reportElement = $reportElementModel->getID($elementID);

      $hasPermission = CategoryModel::checkPermission($reportElement->CategoryID, 'Vanilla.Discussion.View');

      if (!$hasPermission) {
          throw permissionException("Vanilla.Discussion.View");
      }

      $elementTitle = Gdn_Format::text(getValue('Name', $reportElement, NULL), FALSE);
      $elementExcerpt = Gdn_Format::text(getValue('Body', $reportElement, NULL), FALSE);
      if (!is_null($elementExcerpt)) {
         $original = strlen($elementExcerpt);
         $elementExcerpt = substr($elementExcerpt, 0, 140);
         if ($original > strlen($elementExcerpt))
            $elementExcerpt .= "...";
      }

      if (is_null($elementTitle))
         $elementTitle = $elementExcerpt;

      $elementShortTitle = (strlen($elementTitle) <= 143) ? $elementTitle : substr($elementTitle, 0, 140).'...';

      $elementAuthorID = getValue('InsertUserID', $reportElement);
      $elementAuthor = Gdn::userModel()->getID($elementAuthorID);
      $elementAuthorName = getValue('Name', $elementAuthor);

      $regardingAction = c('Plugins.Reporting.ReportAction', FALSE);
      $regardingActionSupplement = c('Plugins.Reporting.ReportActionSupplement', FALSE);

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

      if ($sender->Form->authenticatedPostBack()) {
         $regardingTitle = sprintf(t("Reported: '{RegardingTitle}' by %s"), $elementAuthorName);
         $reportingData['Title'] = $regardingTitle;
         $reportingData['Reason'] = $sender->Form->getValue('Plugin.Reporting.Reason');

         $this->EventArguments['Report'] = &$reportingData;
         $this->fireEvent('BeforeRegarding');

         $regarding = Gdn::regarding()
            ->that($reportingData['Context'], $reportingData['ElementID'], $reportingData['Element'])
            ->reportIt()
            ->forCollaboration($reportingData['Action'], $reportingData['Supplement'])
            ->entitled($reportingData['Title'])
            ->from($reportingData['UserID'])
            ->because($reportingData['Reason'])
            ->located(TRUE) // build URL automatically
            ->commit();

         $sender->informMessage('<span class="InformSprite Skull"></span>'.t('Your complaint has been registered. Thankyou!'), 'HasSprite Dismissable AutoDismiss');
      }

      $sender->setData('Plugin.Reporting.Data', $reportingData);
      $sender->render($this->getView('report.php'));
   }

   /**
   * Handle awesome actions
   *
   * @param mixed $sender
   */
   public function controller_Awesome($sender) {
      if (!($userID = Gdn::session()->UserID))
         throw new Exception(t('Cannot report content while not logged in.'));

      $userName = Gdn::session()->User->Name;

      $arguments = $sender->RequestArgs;
      if (sizeof($arguments) != 4)
         throw new Exception(sprintf(t("Incorrect arg-count. Doesn't look like a legit request. Got %s arguments, expected 4."),sizeof($arguments)));

      list($eventType, $context, $elementID, $encodedURL) = $arguments;
      $uRL = base64_decode(str_replace('-','=',$encodedURL));

      $reportElementModelName = ucfirst($context).'Model';
      if (!class_exists($reportElementModelName))
         throw new Exception(t('Cannot report on an entity with no model.'));

      // Ok we're good to go for sure now

      $reportElementModel = new $reportElementModelName();
      $reportElement = $reportElementModel->getID($elementID);

      $elementTitle = Gdn_Format::text(getValue('Name', $reportElement, NULL), FALSE);
      $elementExcerpt = Gdn_Format::text(getValue('Body', $reportElement, NULL), FALSE);
      if (!is_null($elementExcerpt)) {
         $original = strlen($elementExcerpt);
         $elementExcerpt = substr($elementExcerpt, 0, 140);
         if ($original > strlen($elementExcerpt))
            $elementExcerpt .= "...";
      }

      if (is_null($elementTitle))
         $elementTitle = $elementExcerpt;

      $elementShortTitle = (strlen($elementTitle) <= 143) ? $elementTitle : substr($elementTitle, 0, 140).'...';

      $elementAuthorID = getValue('InsertUserID', $reportElement);
      $elementAuthor = Gdn::userModel()->getID($elementAuthorID);
      $elementAuthorName = getValue('Name', $elementAuthor);

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

      $regardingAction = c('Plugins.Reporting.AwesomeAction', FALSE);
      $regardingActionSupplement = c('Plugins.Reporting.AwesomeActionSupplement', FALSE);

      if ($sender->Form->authenticatedPostBack()) {
         $regardingTitle = sprintf(t("Awesome: '{RegardingTitle}' by %s"), $elementAuthorName);
         $regarding = Gdn::regarding()
            ->that($context, $elementID, $reportElement)
            ->itsAwesome()
            ->forCollaboration($regardingAction, $regardingActionSupplement)
            ->entitled($regardingTitle)
            ->from(Gdn::session()->UserID)
            ->because($sender->Form->getValue('Plugin.Reporting.Reason'))
            ->located(TRUE) // build URL automatically
            ->commit();

         $sender->informMessage('<span class="InformSprite Heart"></span>'.t('Your suggestion has been registered. Thankyou!'), 'HasSprite Dismissable AutoDismiss');
      }

      $sender->setData('Plugin.Reporting.Data', $reportingData);
      $sender->render($this->getView('awesome.php'));
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
   public function discussionController_afterReactions_handler($sender) {
      $context = $sender->EventArguments['Type'];
      $text = FALSE;
      $style = [];

      $context = strtolower($sender->EventArguments['Type']);

      if ($this->ReportEnabled)
         $this->outputButton(self::BUTTON_TYPE_REPORT, $context, $sender);
      if ($this->AwesomeEnabled)
         $this->outputButton(self::BUTTON_TYPE_AWESOME, $context, $sender);

      if ($this->ReportEnabled || $this->AwesomeEnabled)
         $sender->addCssFile('reporting.css', 'plugins/Reporting');
   }

   protected function outputButton($buttonType, $context, $sender) {
      // Signed in users only. No guest reporting!
      if (!Gdn::session()->UserID) return;

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
            $uRL = "/discussion/{$elementID}/".Gdn_Format::url($sender->EventArguments['Discussion']->Name);
            break;

         case 'conversation':
            break;

         default:
            return;
      }

      $buttonTitle = t(ucfirst($buttonType));
      $containerCSS = $buttonTitle.'Post';
      $encodedURL = str_replace('=','-',base64_encode($uRL));
      $eventUrl = "plugin/reporting/{$buttonType}/{$context}/{$elementID}/{$encodedURL}";

      //$Sender->EventArguments['CommentOptions'][$ButtonTitle] = array('Label' => $ButtonTitle, 'Url' => "plugin/reporting/{$ButtonType}/{$Context}/{$ElementID}/{$EncodedURL}", $ContainerCSS.' ReportContent Popup');

      $spriteType = "React".ucfirst($buttonType);
      $text = anchor(sprite($spriteType, 'ReactSprite').$buttonTitle, $eventUrl, "ReactButton React {$containerCSS} Popup");
      echo bullet();
      echo $text;
   }

   /*
    * Regarding handlers
    */

   public function gdn_Regarding_RegardingDisplay_Handler($sender) {
      $event = $sender->matchEvent(['report', 'awesome'], '*');
      if ($event === FALSE)
         return;

      $entity = getValue('Entity', $event);
      $regardingData = getValue('RegardingData', $event);
      $regardingType = getValue('Type', $regardingData);
      $reportInfo = [
         'ReportingUser'         => Gdn::userModel()->getID(getValue('InsertUserID', $regardingData)),
         'EntityType'            => t(ucfirst(getValue('ForeignType', $regardingData))),
         'ReportedUser'          => Gdn::userModel()->getID(getValue('InsertUserID', $entity)),
         'ReportedTime'          => getValue('DateInserted', $regardingData),
         'EntityURL'             => getValue('ForeignURL', $regardingData, NULL)
      ];

      if (!is_null($reportedReason = getValue('Comment', $regardingData, NULL)))
         $reportInfo['ReportedReason'] = $reportedReason;

      if (!is_null($reportedContent = getValue('OriginalContent', $regardingData, NULL)))
         $reportInfo['OriginalContent'] = $reportedContent;

      Gdn::controller()->setData('RegardingSender', $sender);
      Gdn::controller()->setData('Entity', $entity);
      Gdn::controller()->setData('RegardingData', $regardingData);
      Gdn::controller()->setData('ReportInfo', $reportInfo);
      echo Gdn::controller()->fetchView("{$regardingType}-regarding",'','plugins/Reporting');
   }

   public function gdn_Regarding_RegardingActions_Handler($sender) {
      $event = $sender->matchEvent('report', '*');
      if ($event === FALSE)
         return;

      // Add buttonz hurr?
   }

   /*
    * Regarding extensions
    */

   public function gdn_RegardingEntity_ReportIt_Create($sender) {
      return $sender->actionIt('Report');
   }

   public function gdn_RegardingEntity_ItsAwesome_Create($sender) {
      return $sender->actionIt('Awesome');
   }

   public function setup() {

   }

}
