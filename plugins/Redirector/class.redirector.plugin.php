<?php if (!defined('APPLICATION')) exit();

/**
 * Adds 301 redirects for Vanilla from common forum platforms.
 * 
 * Changes:
 *  1.0        Initial Release
 * 
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright Copyright 2008, 2009 Vanilla Forums Inc.
 * @license Proprietary
 */

// Define the plugin:
$PluginInfo['Redirector'] = array(
   'Name' => 'Forum Redirector',
   'Description' => "Adds 301 redirects for Vanilla from common forum platforms.",
   'Version' => '1.0b',
   'RequiredApplications' => array('Vanilla' => '2.1a'),
   'Author' => 'Todd Burry',
   'AuthorEmail' => 'todd@vanillaforums.com',
   'AuthorUrl' => 'http://vanillaforums.com'
);

class RedirectorPlugin extends Gdn_Plugin {
   public static $Files = array(
      'forum' => array('RedirectorPlugin', 'forum_Filter'),
      'forumdisplay.php' => array( // vBulletin category
         'f' => 'CategoryID',
         'page' => 'Page',
         '_arg0' => array('CategoryID', 'Filter' => array('RedirectorPlugin', 'RemoveID')),
         '_arg1' => array('Page', 'Filter' => array('RedirectorPlugin', 'GetNumber'))
         ),
      'member.php' => array( // vBulletin user
         'u' => 'UserID',
         '_arg0' => array('UserID', 'Filter' => array('RedirectorPlugin', 'RemoveID'))
         ),
      'memberlist.php' => array( // phpBB user
         'u' => 'UserID'
         ),
      'post' => array( // punbb comment
         '_arg0' => 'CommentID'
         ),
      'showpost.php' => array( // vBulletin comment
         'p' => 'CommentID'
         ),
      'showthread.php' => array( // vBulletin discussion
         't' => 'DiscussionID',
         'p' => 'CommentID',
         'page' => 'Page',
         '_arg0' => array('DiscussionID', 'Filter' => array('RedirectorPlugin', 'RemoveID')),
         '_arg1' => array('Page', 'Filter' => array('RedirectorPlugin', 'GetNumber'))
         ),
      'topic' => array('RedirectorPlugin', 'topic_Filter'),
//      'user' => array( // ipb user
//         '_arg0' => array('UserID', 'Filter' => array('RedirectorPlugin', 'RemoveID'))
//         ),
      'viewforum.php' => array( // phpBB category
         'f' => 'CategoryID',
         'start' => 'Offset'
         ),
      'viewtopic.php' => array( // phpBB discussion/comment
         't' => 'DiscussionID',
         'p' => 'CommentID',
         'start' => 'Offset'
         )
      );
   
   /**
    * @param Gdn_Dispatcher $Sender
    */
   public function Gdn_Dispatcher_NotFound_Handler($Dispatcher, $Args) {
      $Path = Gdn::Request()->Path();
      $Get = Gdn::Request()->Get();
      
      Trace(array('Path' => $Path, 'Get' => $Get), 'Input');
      
      // Figure out the filename.
      $Parts = explode('/', $Path);
      $After = array();
      $Filename = '';
      while(count($Parts) > 0) {
         $V = array_pop($Parts);
         if (preg_match('`.*\..*`', $V)) {
            $Filename = $V;
            break;
         }
         
         array_unshift($After, $V);
      }
      
      if (!$Filename) {
         // There was no filename, so we can try the first folder as the filename.
         $Filename = array_shift($After);
      }
      
      // Add the after parts to the array.
      $i = 0;
      foreach ($After as $Arg) {
         $Get["_arg$i"] = $Arg;
         $i++;
      }
      
      $Url = $this->FilenameRedirect($Filename, $Get);
      if ($Url) {
         if (Debug())
            Trace($Url, "Redirect found");
         else
            Redirect($Url, 301);
      }
   }
   
   public function FilenameRedirect($Filename, $Get) {
      Trace(array('Filename' => $Filename, 'Get' => $Get), 'Testing');
      $Filename = strtolower($Filename);
      array_change_key_case($Get);
      
      if (!isset(self::$Files[$Filename]))
         return FALSE;
      
      $Row = self::$Files[$Filename];
      
      if (is_callable($Row)) {
         // Use a callback to determine the translation.
         $Row = call_user_func($Row, $Get);
      }
      
      // Translate all of the get parameters into new parameters.
      $Vars = array();
      foreach ($Get as $Key => $Value) {
         if (!isset($Row[$Key]))
            continue;
         
         $Opts = (array)$Row[$Key];
         
         if (isset($Opts['Filter'])) {
            // Call the filter function to change the value.
            $R = call_user_func($Opts['Filter'], $Value, $Opts[0]);
            if (is_array($R)) {
               // The filter can change the column name too.
               $Opts[0] = $R[0];
               $Value = $R[1];
            } else {
               $Value = $R;
            }
         }
         
         $Vars[$Opts[0]] = $Value;
      }
      
      Trace($Vars, 'Translated Arguments');
      
      // Now let's see what kind of record we have.
      // We'll check the various primary keys in order of importance.
      $Result = FALSE;
      if (isset($Vars['CommentID'])) {
         Trace("Looking up comment {$Vars['CommentID']}.");
         $CommentModel = new CommentModel();
         $Comment = $CommentModel->GetID($Vars['CommentID']);
         if ($Comment)
            $Result = CommentUrl($Comment, '//');
      } elseif (isset($Vars['DiscussionID'])) {
         Trace("Looking up discussion {$Vars['DiscussionID']}.");
         $DiscussionModel = new DiscussionModel();
         $Discussion = $DiscussionModel->GetID($Vars['DiscussionID']);
         if ($Discussion)
            $Result = DiscussionUrl($Discussion, self::PageNumber($Vars, 'Vanilla.Comments.PerPage'), '//');
      } elseif (isset($Vars['UserID'])) {
         Trace("Looking up user {$Vars['UserID']}.");
         $User = Gdn::UserModel()->GetID($Vars['UserID']);
         if ($User)
            $Result = Url(UserUrl($User), '//');
      } elseif (isset($Vars['CategoryID'])) {
         Trace("Looking up category {$Vars['CategoryID']}.");
         $Category = CategoryModel::Categories($Vars['CategoryID']);
         if ($Category)
            $Result = CategoryUrl($Category, self::PageNumber($Vars, 'Vanilla.Discussions.PerPage'), '//');
      }
      
      return $Result;
   }
   
   public static function forum_Filter($Get) {
      if (GetValue('_arg2', $Get) == 'page') {
         // This is a punbb style forum.
         return array(
            '_arg0' => 'CategoryID',
            '_arg3' => 'Page'
            );
      } else {
         // This is an ipb style topic.
         return array(
            '_arg0' => array('CategoryID', 'Filter' => array('RedirectorPlugin', 'RemoveID')),
            '_arg1' => array('Page', 'Filter' => array('RedirectorPlugin', 'IPBPageNumber'))
            );
      }
   }
   
   public static function GetNumber($Value) {
      if (preg_match('`(\d+)`', $Value, $Matches))
         return $Matches[1];
      return NULL;
   }
   
   public static function IPBPageNumber($Value) {
      if (preg_match('`page__st__(\d+)`i', $Value, $Matches))
         return array('Offset', $Matches[1]);
      return self::GetNumber($Value);
   }
   
   /**
    * Return the page number from the given variables that may have an offset or a page.
    * 
    * @param array $Vars The variables that should contain an Offset or Page key.
    * @param int|string $PageSize The pagesize or the config key of the pagesize.
    * @return int
    */
   public static function PageNumber($Vars, $PageSize) {
      if (isset($Vars['Page']))
         return $Vars['Page'];
      if (isset($Vars['Offset'])) {
         if (is_numeric($PageSize))
            return PageNumber($Vars['Offset'], $PageSize, FALSE, Gdn::Session()->IsValid());
         else
            return PageNumber($Vars['Offset'], C($PageSize, 30), FALSE, Gdn::Session()->IsValid());
      }
      return 1;
   }
   
   public static function RemoveID($Value) {
      if (preg_match('`^(\d+)`', $Value, $Matches))
         return $Matches[1];
      return NULL;
   }
   
   public static function topic_Filter($Get) {
      if (GetValue('_arg2', $Get) == 'page') {
         // This is a punbb style topic.
         return array(
            '_arg0' => 'DiscussionID',
            '_arg3' => 'Page'
            );
      } else {
         // This is an ipb style topic.
         return array(
            'p' => 'CommentID',
            '_arg0' => array('DiscussionID', 'Filter' => array('RedirectorPlugin', 'RemoveID')),
            '_arg1' => array('Page', 'Filter' => array('RedirectorPlugin', 'IPBPageNumber'))
            );
      }
   }
}