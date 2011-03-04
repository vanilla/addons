<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Vanilla Forums Inc.
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
*/

// Define the plugin:
$PluginInfo['Quotes'] = array(
   'Name' => 'Quotes',
   'Description' => "This plugin allows users to quote each other's posts easily.",
   'Version' => '1.2',
   'MobileFriendly' => TRUE,
   'RequiredApplications' => FALSE,
   'RequiredTheme' => FALSE, 
   'RequiredPlugins' => FALSE,
   'HasLocale' => TRUE,
   'RegisterPermissions' => FALSE,
   'Author' => "Tim Gunter",
   'AuthorEmail' => 'tim@vanillaforums.com',
   'AuthorUrl' => 'http://www.vanillaforums.com'
);

class QuotesPlugin extends Gdn_Plugin {

   public function PluginController_Quotes_Create($Sender) {
		$this->Dispatch($Sender, $Sender->RequestArgs);
   }
   
   public function Controller_Getquote($Sender) {
      $Sender->DeliveryMethod(DELIVERY_METHOD_JSON);
      $Sender->DeliveryType(DELIVERY_TYPE_VIEW);

      $QuoteData = array(
         'status' => 'failed'
      );
      array_shift($Sender->RequestArgs);
      if (sizeof($Sender->RequestArgs)) {
         $QuoteData['selector'] = $Sender->RequestArgs[0];
         list($Type, $ID) = explode('_',$Sender->RequestArgs[0]);
         $this->FormatQuote($Type, $ID, $QuoteData);
      }
      $Sender->SetJson('Quote', $QuoteData);
      $Sender->Render($this->GetView('getquote.php'));
   }

   public function DiscussionController_Render_Before($Sender) {
      $this->PrepareController($Sender);
   }
   
   public function PostController_Render_Before($Sender) {
      $this->PrepareController($Sender);
   }
   
   protected function PrepareController($Sender) {
      $Sender->AddJsFile($this->GetResource('js/quotes.js', FALSE, FALSE));
      $Sender->AddCssFile($this->GetResource('css/quotes.css', FALSE, FALSE));
   }
   
   public function DiscussionController_CommentOptions_Handler($Sender) {
      $this->AddQuoteButton($Sender);
   }
   
   public function PostController_CommentOptions_Handler($Sender) {
      $this->AddQuoteButton($Sender);
   }
   
   protected function AddQuoteButton($Sender) {
      if (!Gdn::Session()->UserID) return;
      
      $Object = !isset($Sender->EventArguments['Comment']) ? $Sender->Data['Discussion'] : $Sender->EventArguments['Comment'];
      $ObjectID = !isset($Sender->EventArguments['Comment']) ? 'Discussion_'.$Sender->Data['Discussion']->DiscussionID : 'Comment_'.$Sender->EventArguments['Comment']->CommentID;
      $QuoteURL = Url("post/quote/{$Object->DiscussionID}/{$ObjectID}",TRUE);
      $QuoteText = T('Quote');
      echo <<<QUOTE
      <span class="CommentQuote"><a href="{$QuoteURL}">{$QuoteText}</a></span>
QUOTE;
   }
   
   public function DiscussionController_BeforeCommentDisplay_Handler($Sender) {
      $this->RenderQuotes($Sender);
   }
   
   public function PostController_BeforeCommentDisplay_Handler($Sender) {
      $this->RenderQuotes($Sender);
   }
   
   protected function RenderQuotes($Sender) {
      switch ($Sender->EventArguments['Object']->Format) {
         case 'Html':
            $Sender->EventArguments['Object']->Body = preg_replace_callback('/(<blockquote rel="([\d\w_ ]{3,30})">)/u', array($this, 'QuoteAuthorCallback'), $Sender->EventArguments['Object']->Body);
            $Sender->EventArguments['Object']->Body = str_replace('</blockquote>','</p></div></blockquote>',$Sender->EventArguments['Object']->Body);
            break;
            
         case 'BBCode':
			case 'Markdown':
            $Sender->EventArguments['Object']->Body = preg_replace_callback('/(\[quote="?([\d\w_ ]{3,30})"?\])/u', array($this, 'QuoteAuthorCallback'), $Sender->EventArguments['Object']->Body);
            $Sender->EventArguments['Object']->Body = str_replace('[/quote]','</p></div></blockquote>',$Sender->EventArguments['Object']->Body);
            break;
            
         case 'Display':
         case 'Text':
         default:
            break;
      
      }
   }
   
   protected function QuoteAuthorCallback($Matches) {
      $Attribution = T('%s said:');
      $Attribution = sprintf($Attribution, "<a href=\"/profile/{$Matches[2]}\" rel=\"nofollow\">{$Matches[2]}</a>");
      return <<<BLOCKQUOTE
      <blockquote class="UserQuote"><div class="QuoteAuthor">{$Attribution}</div><div class="QuoteText"><p>
BLOCKQUOTE;
   }
   
   public function PostController_Quote_Create($Sender) {
      if (sizeof($Sender->RequestArgs) < 2) return;
      $Selector = $Sender->RequestArgs[1];
      $Sender->SetData('Plugin.Quotes.QuoteSource', $Selector);
      $Sender->View = 'comment';
      return $Sender->Comment();
   }
   
   public function PostController_BeforeCommentRender_Handler($Sender) {
      if (isset($Sender->Data['Plugin.Quotes.QuoteSource'])) {
         if (sizeof($Sender->RequestArgs) < 2) return;
         $Selector = $Sender->RequestArgs[1];
         list($Type, $ID) = explode('_', $Selector);
         $QuoteData = array(
            'status' => 'failed'
         );
         $this->FormatQuote($Type, $ID, $QuoteData);
         
         if ($QuoteData['status'] == 'success') {
            switch ($QuoteData['format']) {
               case 'Html':
                  $Sender->Form->SetValue('Body', '<blockquote rel="'.$QuoteData['authorname'].'">'.$QuoteData['body']."</blockquote>\n");
                  break;
               case 'BBCode':
                  $Sender->Form->SetValue('Body', '[quote="'.$QuoteData['authorname'].'"]'.$QuoteData['body']."[/quote]\n");
                  break;
               case 'Display':
               case 'Text':
               default:
                  $Sender->Form->SetValue('Body', '> '.$QuoteData['authorname']."\n> {$QuoteData['body']}\n");
            }
         }
      }
   }
   
   protected function FormatQuote($Type, $ID, &$QuoteData) {
      $Model = FALSE;
      switch (strtolower($Type)) {
         case 'comment':
            $Model = new CommentModel();
            break;
         
         case 'discussion':
            $Model = new DiscussionModel();
            break;
            
         default:
            break;
      }
      
      //$QuoteData = array();
      if ($Model !== FALSE) {
         $Data = $Model->GetID($ID);
         $NewFormat = C('Garden.InputFormatter');
         $QuoteFormat = $Data->Format;
         $QuoteData = array_merge($QuoteData, array(
            'status'       => 'success',
            'body'         => $Data->Body,
            'format'       => C('Garden.InputFormatter'),
            'authorid'     => $Data->InsertUserID,
            'authorname'   => $Data->InsertName
         ));
         
         // Perform transcoding if possible
         $NewBody = $Data->Body;
         if ($QuoteFormat != $NewFormat) {
            if ($QuoteFormat == 'BBCode' && $NewFormat == 'Html')
               $NewBody = Gdn_Format::BBCode($NewBody);
            elseif ($QuoteFormat == 'Text' && $NewFormat == 'Html')
               $NewBody = Gdn_Format::Text($NewBody);
            elseif ($QuoteFormat == 'Html' && $NewFormat == 'BBCode')
               $NewBody = Gdn_Format::Text($NewBody);
            elseif ($QuoteFormat == 'Text' && $NewFormat == 'BBCode')
               $NewBody = Gdn_Format::Text($NewBody);
         }
         $Data->Body = $NewBody;
      }
   }
   
   public function Setup() {
      SaveToConfig('Garden.Html.SafeStyles',FALSE);
   }
   
   public function OnDisable() {
      RemoveFromConfig('Garden.Html.SafeStyles');
   }
   
   public function Structure() {
      // Nothing to do here!
   }
         
}