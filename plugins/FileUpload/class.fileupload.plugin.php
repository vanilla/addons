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
$PluginInfo['FileUpload'] = array(
   'Description' => 'This plugin enables file uploads and attachments to discussions, comments and conversations.',
   'Version' => '1.4.4',
   'RequiredApplications' => array('Vanilla' => '2.0.9'),
   'RequiredTheme' => FALSE, 
   'RequiredPlugins' => FALSE,
   'HasLocale' => FALSE,
   'RegisterPermissions' => array('Plugins.Attachments.Upload.Allow','Plugins.Attachments.Download.Allow'),
   'SettingsUrl' => '/dashboard/plugin/fileupload',
   'SettingsPermission' => 'Garden.Settings.Manage',
   'Author' => "Tim Gunter",
   'AuthorEmail' => 'tim@vanillaforums.com',
   'AuthorUrl' => 'http://www.vanillaforums.com'
);

//Gdn_LibraryMap::SafeCache('library','class.mediamodel.php',dirname(__FILE__).DS.'models/class.mediamodel.php');

include dirname(__FILE__).'/class.mediamodel.php';

class FileUploadPlugin extends Gdn_Plugin {
   /// PROPERTIES ///
   protected $MediaCache;


   /// METHODS ///

   public function __construct() {
      $this->MediaCache = array();
      
      $this->CanUpload = Gdn::Session()->CheckPermission('Plugins.Attachments.Upload.Allow', FALSE);
      $this->CanDownload = Gdn::Session()->CheckPermission('Plugins.Attachments.Download.Allow', FALSE);
   }

   /**
    * @return MediaModel
    */
   public function MediaModel() {
      static $MediaModel = NULL;

      if ($MediaModel === NULL) {
         $MediaModel = new MediaModel();
      }
      return $MediaModel;
   }

   /// EVENT HANDLERS ///

   /**
    * Adds "Media" menu option to the Forum menu on the dashboard.
    */
   public function Base_GetAppSettingsMenuItems_Handler($Sender) {
      $Menu = &$Sender->EventArguments['SideMenu'];
      $Menu->AddItem('Forum', 'Forum');
      $Menu->AddLink('Forum', 'Media', 'plugin/fileupload', 'Garden.Settings.Manage');
   }
   
   public function PluginController_FileUpload_Create($Sender) {
      $Sender->Permission('Garden.Settings.Manage');
      $Sender->Title('FileUpload');
      $Sender->AddSideMenu('plugin/fileupload');
      $Sender->Form = new Gdn_Form();
      
      $this->EnableSlicing($Sender);
      $this->Dispatch($Sender, $Sender->RequestArgs);
   }
   
   public function Controller_Toggle($Sender) {
      $FileUploadStatus = Gdn::Config('Plugins.FileUpload.Enabled', FALSE);

      $Validation = new Gdn_Validation();
      $ConfigurationModel = new Gdn_ConfigurationModel($Validation);
      $ConfigurationModel->SetField(array('FileUploadStatus'));
      
      // Set the model on the form.
      $Sender->Form->SetModel($ConfigurationModel);
      
      if ($Sender->Form->AuthenticatedPostBack()) {
         $FileUploadStatus = ($Sender->Form->GetValue('FileUploadStatus') == 'ON') ? TRUE : FALSE;
         SaveToConfig('Plugins.FileUpload.Enabled', $FileUploadStatus);
      }
      
      $Sender->SetData('FileUploadStatus', $FileUploadStatus);
      $Sender->Form->SetData(array(
         'FileUploadStatus'  => $FileUploadStatus
      ));
      $Sender->Render($this->GetView('toggle.php'));
   }
   
   public function Controller_Index($Sender) {
      $Sender->AddCssFile($this->GetWebResource('css/fileupload.css'));
      $Sender->AddCssFile('admin.css');
      
      $Sender->Render($this->GetView('fileupload.php'));
   }
   
   public function Controller_Delete($Sender) {
      list($Action, $MediaID) = $Sender->RequestArgs;
      $Sender->DeliveryMethod(DELIVERY_METHOD_JSON);
      $Sender->DeliveryType(DELIVERY_TYPE_VIEW);
      
      $Delete = array(
         'MediaID'   => $MediaID,
         'Status'    => 'failed'
      );
      
      $Media = $this->MediaModel()->GetID($MediaID);

      if ($Media) {
         $Delete['Media'] = $Media;
         $UserID = GetValue('UserID', Gdn::Session());
         if (GetValue('InsertUserID', $Media, NULL) == $UserID || Gdn::Session()->CheckPermission("Garden.Settings.Manage")) {
            $this->MediaModel()->Delete($Media, TRUE);
            $Delete['Status'] = 'success';
         }
      }
      
      $Sender->SetJSON('Delete', $Delete);
      $Sender->Render($this->GetView('blank.php'));
   }
   
   /**
    * DiscussionController_Render_Before HOOK
    * 
    * Calls FileUploadPlugin::PrepareController
    *
    * @access public
    * @param mixed $Sender The hooked controller
    * @see FileUploadPlugin::PrepareController
    * @return void
    */
   public function DiscussionController_Render_Before($Sender) {
      $this->PrepareController($Sender);
   }
   
   /**
    * PostController_Render_Before HOOK
    *
    * Calls FileUploadPlugin::PrepareController
    * 
    * @access public
    * @param mixed $Sender The hooked controller
    * @see FileUploadPlugin::PrepareController
    * @return void
    */
   public function PostController_Render_Before($Sender) {
      $this->PrepareController($Sender);
   }
   
   /**
    * PrepareController function.
    *
    * Adds CSS and JS includes to the header of the discussion or post.
    * 
    * @access protected
    * @param mixed $Controller The hooked controller
    * @return void
    */
   protected function PrepareController($Controller) {
      if (!$this->IsEnabled()) return;
      
      $Controller->AddCssFile($this->GetResource('css/fileupload.css', FALSE, FALSE));
      $Controller->AddJsFile($this->GetResource('js/fileupload.js', FALSE, FALSE));
      $Controller->AddDefinition('apcavailable',self::ApcAvailable());
      $Controller->AddDefinition('uploaderuniq',uniqid(''));
      
      $PostMaxSize = Gdn_Upload::UnformatFileSize(ini_get('post_max_size'));
      $FileMaxSize = Gdn_Upload::UnformatFileSize(ini_get('upload_max_filesize'));
      $ConfigMaxSize = Gdn_Upload::UnformatFileSize(C('Garden.Upload.MaxFileSize', '1MB'));
      $MaxSize = min($PostMaxSize, $FileMaxSize, $ConfigMaxSize);
      $Controller->AddDefinition('maxuploadsize',$MaxSize);
   }
   
   /**
    * PostController_BeforeFormButtons_Handler HOOK
    *
    * Calls FileUploadPlugin::DrawAttachFile
    * 
    * @access public
    * @param mixed &$Sender
    * @see FileUploadPlugin::DrawAttachFile
    * @return void
    */
   public function PostController_BeforeFormButtons_Handler($Sender) {
      if (!is_null($Discussion = GetValue('Discussion',$Sender, NULL))) {
         $this->CacheAttachedMedia($Sender);
         $Sender->EventArguments['Type'] = 'Discussion';
         $Sender->EventArguments['Discussion'] = $Discussion;
         $this->AttachUploadsToComment($Sender);
      }
      $this->DrawAttachFile($Sender);
   }
   
   public function DiscussionController_BeforeFormButtons_Handler($Sender) {
      $this->DrawAttachFile($Sender);
   }
   
   /**
    * DrawAttachFile function.
    * 
    * Helper method that allows the plugin to insert the file uploader UI into the 
    * Post Discussion and Post Comment forms.
    *
    * @access public
    * @param mixed $Sender
    * @return void
    */
   public function DrawAttachFile($Controller) {
      if (!$this->IsEnabled()) return;
      if (!$this->CanUpload) return;
      
      echo $Controller->FetchView($this->GetView('attach_file.php'));
   }
   
   /**
    * DiscussionController_BeforeDiscussionRender_Handler function.
    * 
    * @access public
    * @param mixed $Sender
    * @return void
    */
   public function DiscussionController_BeforeDiscussionRender_Handler($Sender) {
      // Cache the list of media. Don't want to do multiple queries!
      $this->CacheAttachedMedia($Sender);
   }
   
   /**
    * PostController_BeforeCommentRender_Handler function.
    * 
    * @access public
    * @param mixed $Sender
    * @return void
    */
   public function PostController_BeforeCommentRender_Handler($Sender) {
      // Cache the list of media. Don't want to do multiple queries!
      $this->CacheAttachedMedia($Sender);
   }
   
   /**
    * CacheAttachedMedia function.
    * 
    * @access protected
    * @param mixed $Sender
    * @return void
    */
   protected function CacheAttachedMedia($Sender) {
      if (!$this->IsEnabled()) return;
      
      $Comments = $Sender->Data('CommentData');
      $CommentIDList = array();
      
      if ($Comments && $Comments instanceof Gdn_DataSet) {
         $Comments->DataSeek(-1);
         while ($Comment = $Comments->NextRow())
            $CommentIDList[] = $Comment->CommentID;
      } elseif ($Sender->Discussion) {
         $CommentIDList[] = $Sender->DiscussionID = $Sender->Discussion->DiscussionID;
      }
      if (isset($Sender->Comment) && isset($Sender->Comment->CommentID)) {
         $CommentIDList[] = $Sender->Comment->CommentID;
      }
      
      $MediaData = $this->MediaModel()->PreloadDiscussionMedia($Sender->DiscussionID, $CommentIDList);

      $MediaArray = array();
      if ($MediaData !== FALSE) {
         $MediaData->DataSeek(-1);
         while ($Media = $MediaData->NextRow()) {
            $MediaArray[$Media->ForeignTable.'/'.$Media->ForeignID][] = $Media;
         }
      }
            
      $this->MediaCache = $MediaArray;
   }
   
   /**
    * DiscussionController_AfterCommentBody_Handler function.
    * 
    * @access public
    * @param mixed $Sender
    * @return void
    */
   public function DiscussionController_AfterCommentBody_Handler($Sender) {
      $this->AttachUploadsToComment($Sender);
   }
   
   /**
    * PostController_AfterCommentBody_Handler function.
    * 
    * @access public
    * @param mixed $Sender
    * @return void
    */
   public function PostController_AfterCommentBody_Handler($Sender) {
      $this->AttachUploadsToComment($Sender);
   }
      
   /**
    * AttachUploadsToComment function.
    * 
    * @access protected
    * @param mixed $Sender
    * @return void
    */
   protected function AttachUploadsToComment($Controller) {
      if (!$this->IsEnabled()) return;
      
      $Type = strtolower($RawType = $Controller->EventArguments['Type']);

      if (StringEndsWith($Controller->RequestMethod, 'Comment', TRUE) && $Type != 'comment') {
         $Type = 'comment';
         $RawType = 'Comment';
         if (!isset($Controller->Comment))
            return;
         $Controller->EventArguments['Comment'] = $Controller->Comment;
      }

      $MediaList = $this->MediaCache;
      if (!is_array($MediaList)) return;
      
      $Param = (($Type == 'comment') ? 'CommentID' : 'DiscussionID');
      $MediaKey = $Type.'/'.$Controller->EventArguments[$RawType]->$Param;
      if (array_key_exists($MediaKey, $MediaList)) {
         $Controller->SetData('CommentMediaList', $MediaList[$MediaKey]);
         $Controller->SetData('GearImage', $this->GetWebResource('images/gear.png'));
         $Controller->SetData('Garbage', $this->GetWebResource('images/trash.png'));
         $Controller->SetData('CanDownload', $this->CanDownload);
         echo $Controller->FetchView($this->GetView('link_files.php'));
      }
   }
   
   /**
    * DiscussionController_Download_Create function.
    * 
    * @access public
    * @param mixed $Sender
    * @return void
    */
   public function DiscussionController_Download_Create($Sender) {
      if (!$this->IsEnabled()) return;
      if (!$this->CanDownload) throw new PermissionException("File could not be streamed: Access is denied");
   
      list($MediaID) = $Sender->RequestArgs;
      $Media = $this->MediaModel()->GetID($MediaID);
      
      if (!$Media) return;
      
      $Filename = Gdn::Request()->Filename();
      if (!$Filename) $Filename = $Media->Name;
      
      $DownloadPath = CombinePaths(array(MediaModel::PathUploads(),GetValue('Path', $Media)));

      if (in_array(strtolower(pathinfo($Filename, PATHINFO_EXTENSION)), array('bmp', 'gif', 'jpg', 'jpeg', 'png')))
         $ServeMode = 'inline';
      else
         $ServeMode = 'attachment';

      $this->EventArguments['Media'] = $Media;
      $this->FireEvent('BeforeDownload');
      
      return Gdn_FileSystem::ServeFile($DownloadPath, $Filename, '', $ServeMode);
      throw new Exception('File could not be streamed: missing file ('.$DownloadPath.').');
      
      exit();
   }
   
   /**
    * PostController_AfterCommentSave_Handler function.
    * 
    * @access public
    * @param mixed $Sender
    * @return void
    */
   public function PostController_AfterCommentSave_Handler($Sender) {
      if (!$Sender->EventArguments['Comment']) return;
      
      $CommentID = $Sender->EventArguments['Comment']->CommentID;
      $AttachedFilesData = Gdn::Request()->GetValue('AttachedUploads');
      $AllFilesData = Gdn::Request()->GetValue('AllUploads');
      
      $this->AttachAllFiles($AttachedFilesData, $AllFilesData, $CommentID, 'comment');
   }
   
   /**
    * PostController_AfterDiscussionSave_Handler function.
    * 
    * @access public
    * @param mixed $Sender
    * @return void
    */
   public function PostController_AfterDiscussionSave_Handler($Sender) {
      if (!$Sender->EventArguments['Discussion']) return;
      
      $DiscussionID = $Sender->EventArguments['Discussion']->DiscussionID;
      $AttachedFilesData = Gdn::Request()->GetValue('AttachedUploads');
      $AllFilesData = Gdn::Request()->GetValue('AllUploads');
      
      $this->AttachAllFiles($AttachedFilesData, $AllFilesData, $DiscussionID, 'discussion');
   }
   
   /**
    * AttachAllFiles function.
    * 
    * @access protected
    * @param mixed $AttachedFilesData
    * @param mixed $AllFilesData
    * @param mixed $ForeignID
    * @param mixed $ForeignTable
    * @return void
    */
   protected function AttachAllFiles($AttachedFilesData, $AllFilesData, $ForeignID, $ForeignTable) {
      if (!$this->IsEnabled()) return;
      
      // No files attached
      if (!$AttachedFilesData) return;
      
      $SuccessFiles = array();
      foreach ($AttachedFilesData as $FileID) {
         $Attached = $this->AttachFile($FileID, $ForeignID, $ForeignTable);
         if ($Attached)
            $SuccessFiles[] = $FileID;
      }
         
      // clean up failed and unattached files
      $DeleteIDs = array_diff($AllFilesData, $SuccessFiles);
      foreach ($DeleteIDs as $DeleteID) {
         $this->TrashFile($DeleteID);
      }
   }

   public function UtilityController_Thumbnail_Create($Sender, $Args) {
      $SubPath = implode('/', $Args);
      $Path = MediaModel::PathUploads()."/$SubPath";
      if (!file_exists($Path))
         throw NotFoundException('File');

      // Figure out the dimensions of the upload.
      $ImageSize = getimagesize($Path);
      $SHeight = $ImageSize[1];
      $SWidth = $ImageSize[0];

      $Options = array();

      $ThumbHeight = MediaModel::ThumbnailHeight();
      $ThumbWidth = MediaModel::ThumbnailWidth();

      if (!$ThumbHeight || $SHeight < $ThumbHeight) {
         $Height = $SHeight;
         $Width = $SWidth;
      } else {
         $Height = $ThumbHeight;
         $Width = round($Height * $SWidth / $SHeight);
      }

      if ($ThumbWidth && $Width > $ThumbWidth) {
         $Width = $ThumbWidth;

         if (!$ThumbHeight) {
            $Height = round($Width * $SHeight / $SWidth);
         } else {
            $Options['Crop'] = TRUE;
         }
      }

      $TargetPath = MediaModel::PathUploads()."/thumbnails/$SubPath";
      if (!file_exists(dirname($TargetPath))) {
         mkdir(dirname($TargetPath), 0777, TRUE);
      }
      Gdn_UploadImage::SaveImageAs($Path, $TargetPath, $Height, $Width, $Options);

      $Url = MediaModel::Url("/thumbnails/$SubPath");
      Redirect($Url, 302);
//      Gdn_FileSystem::ServeFile($TargetPath, basename($Path), '', 'inline');
   }
   
   /**
    * AttachFile function.
    * 
    * @access protected
    * @param mixed $FileID
    * @param mixed $ForeignID
    * @param mixed $ForeignType
    * @return void
    */
   protected function AttachFile($FileID, $ForeignID, $ForeignType) {
      $Media = $this->MediaModel()->GetID($FileID);
      if ($Media) {
         $Media->ForeignID = $ForeignID;
         $Media->ForeignTable = $ForeignType;
         try {
//            $PlacementStatus = $this->PlaceMedia($Media, Gdn::Session()->UserID);
            $this->MediaModel()->Save($Media);
         } catch (Exception $e) {
            die($e->getMessage());
            return FALSE;
         }
         return TRUE;
      }
      return FALSE;
   }
   
   /**
    * PlaceMedia function.
    * 
    * @access protected
    * @param mixed &$Media
    * @param mixed $UserID
    * @return void
    */
   protected function PlaceMedia(&$Media, $UserID) {
      $NewFolder = FileUploadPlugin::FindLocalMediaFolder($Media->MediaID, $UserID, TRUE, FALSE);
      $CurrentPath = array();
      foreach ($NewFolder as $FolderPart) {
         array_push($CurrentPath, $FolderPart);
         $TestFolder = CombinePaths($CurrentPath);
         
         if (!is_dir($TestFolder) && !@mkdir($TestFolder, 0777, TRUE))
            throw new Exception("Failed creating folder '{$TestFolder}' during PlaceMedia verification loop");
      }
      
      $FileParts = pathinfo($Media->Name);
      $SourceFilePath = CombinePaths(array($this->PathUploads(),$Media->Path));
      $NewFilePath = CombinePaths(array($TestFolder,$Media->MediaID.'.'.$FileParts['extension']));
      $Success = rename($SourceFilePath, $NewFilePath);
      if (!$Success)
         throw new Exception("Failed renaming '{$SourceFilePath}' -> '{$NewFilePath}'");
      
      $NewFilePath = FileUploadPlugin::FindLocalMedia($Media, FALSE, TRUE);
      $Media->Path = $NewFilePath;
      
      return TRUE;
   }
   
   /**
    * FindLocalMediaFolder function.
    * 
    * @access public
    * @static
    * @param mixed $MediaID
    * @param mixed $UserID
    * @param mixed $Absolute. (default: FALSE)
    * @param mixed $ReturnString. (default: FALSE)
    * @return void
    */
   public static function FindLocalMediaFolder($MediaID, $UserID, $Absolute = FALSE, $ReturnString = FALSE) {
      $DispersionFactor = C('Plugin.FileUpload.DispersionFactor',20);
      $FolderID = $MediaID % $DispersionFactor;
      $ReturnArray = array('FileUpload',$FolderID);
      
      if ($Absolute)
         array_unshift($ReturnArray, MediaModel::PathUploads());
      
      return ($ReturnString) ? implode(DS,$ReturnArray) : $ReturnArray;
   }
   
   /**
    * FindLocalMedia function.
    * 
    * @access public
    * @static
    * @param mixed $Media
    * @param mixed $Absolute. (default: FALSE)
    * @param mixed $ReturnString. (default: FALSE)
    * @return void
    */
   public static function FindLocalMedia($Media, $Absolute = FALSE, $ReturnString = FALSE) {
      $ArrayPath = FileUploadPlugin::FindLocalMediaFolder($Media->MediaID, $Media->InsertUserID, $Absolute, FALSE);
      
      $FileParts = pathinfo($Media->Name);
      $RealFileName = $Media->MediaID.'.'.$FileParts['extension'];
      array_push($ArrayPath, $RealFileName);
      
      return ($ReturnString) ? implode(DS, $ArrayPath) : $ArrayPath;
   }
   
   /**
    * PostController_Upload_Create function.
    * 
    * Controller method that allows plugin to handle ajax file uploads
    *
    * @access public
    * @param mixed &$Sender
    * @return void
    */
   public function PostController_Upload_Create($Sender) {
      if (!$this->IsEnabled()) return;
      
      list($FieldName) = $Sender->RequestArgs;
      
      $Sender->DeliveryMethod(DELIVERY_METHOD_JSON);
      $Sender->DeliveryType(DELIVERY_TYPE_VIEW);
      
      $Sender->FieldName = $FieldName;
      $Sender->ApcKey = Gdn::Request()->GetValueFrom(Gdn_Request::INPUT_POST,'APC_UPLOAD_PROGRESS');
      
      // this will hold the IDs and filenames of the items we were sent. booyahkashaa.
      $MediaResponse = array();
      
      $FileData = Gdn::Request()->GetValueFrom(Gdn_Request::INPUT_FILES, $FieldName, FALSE);
      try {
         if (!$this->CanUpload) 
            throw new FileUploadPluginUploadErrorException("You do not have permission to upload files",11,'???');
      
         if (!$Sender->Form->IsPostBack()) {
            $PostMaxSize = ini_get('post_max_size');
            throw new FileUploadPluginUploadErrorException("The post data was too big (max {$PostMaxSize})",10,'???');
         }
      
         if (!$FileData) {
            //$PostMaxSize = ini_get('post_max_size');
            $MaxUploadSize = ini_get('upload_max_filesize');
            //throw new FileUploadPluginUploadErrorException("The uploaded file was too big (max {$MaxUploadSize})",10,'???');
            throw new FileUploadPluginUploadErrorException("No file data could be found in your post",10,'???');
         }

         // Validate the file upload now.
         $FileErr  = $FileData['error'];
         $FileType = $FileData['type'];
         $FileName = $FileData['name'];
         $FileTemp = $FileData['tmp_name'];
         $FileSize = $FileData['size'];
         $FileKey  = ($Sender->ApcKey ? $Sender->ApcKey : '');

         if ($FileErr != UPLOAD_ERR_OK) {
            $ErrorString = '';
            switch ($FileErr) {
               case UPLOAD_ERR_INI_SIZE:
                  $MaxUploadSize = ini_get('upload_max_filesize');
                  $ErrorString = sprintf(T('The uploaded file was too big (max %s).'), $MaxUploadSize);
                  break;
               case UPLOAD_ERR_FORM_SIZE:
                  $ErrorString = 'The uploaded file was too big';
                  break;
               case UPLOAD_ERR_PARTIAL:
                  $ErrorString = 'The uploaded file was only partially uploaded';
                  break;
               case UPLOAD_ERR_NO_FILE:
                  $ErrorString = 'No file was uploaded';
                  break;
               case UPLOAD_ERR_NO_TMP_DIR:
                  $ErrorString = 'Missing a temporary folder';
                  break;
               case UPLOAD_ERR_CANT_WRITE:
                  $ErrorString = 'Failed to write file to disk';
                  break;
               case UPLOAD_ERR_EXTENSION:
                  $ErrorString = 'A PHP extension stopped the file upload';
                  break;
            }
            
            throw new FileUploadPluginUploadErrorException($ErrorString, $FileErr, $FileName, $FileKey);
         }
         
         $FileNameParts = pathinfo($FileName);
         $Extension = strtolower($FileNameParts['extension']);
         $AllowedExtensions = C('Garden.Upload.AllowedFileExtensions', array("*"));
         if (!in_array($Extension, $AllowedExtensions) && !in_array('*',$AllowedExtensions))
            throw new FileUploadPluginUploadErrorException("Uploaded file type is not allowed.", 11, $FileName, $FileKey);

         $MaxUploadSize = Gdn_Upload::UnformatFileSize(C('Garden.Upload.MaxFileSize', '1G'));
         if ($FileSize > $MaxUploadSize) {
            $Message = sprintf(T('The uploaded file was too big (max %s).'), Gdn_Upload::FormatFileSize($MaxUploadSize));
            throw new FileUploadPluginUploadErrorException($Message, 11, $FileName, $FileKey);
         }



         $SaveFilename = md5(microtime()).'.'.strtolower($Extension);
         $SaveFilename = '/FileUpload/'.substr($SaveFilename, 0, 2).'/'.substr($SaveFilename, 2);
         $SavePath = MediaModel::PathUploads().$SaveFilename;
         if (!is_dir(dirname($SavePath)))
            @mkdir(dirname($SavePath), 0777, TRUE);

         if (!is_dir(dirname($SavePath)))
            throw new FileUploadPluginUploadErrorException("Internal error, could not save the file.", 9, $FileName);
         
         $MoveSuccess = @move_uploaded_file($FileTemp, $SavePath);
         
         if (!$MoveSuccess)
            throw new FileUploadPluginUploadErrorException("Internal error, could not move the file.", 9, $FileName);

         // Get the image dimensions (if this is an image).
         list($ImageWidth, $ImageHeight) = MediaModel::GetImageSize($SavePath);

         $Media = array(
            'Name'            => $FileName,
            'Type'            => $FileType,
            'Size'            => $FileSize,
            'ImageWidth'      => $ImageWidth,
            'ImageHeight'     => $ImageHeight,
            'InsertUserID'    => Gdn::Session()->UserID,
            'DateInserted'    => date('Y-m-d H:i:s'),
            'StorageMethod'   => 'local',
            'Path'            => $SaveFilename
         );
         $MediaID = $this->MediaModel()->Save($Media);
         
         $FinalImageLocation = '';
         $PreviewImageLocation = MediaModel::ThumbnailUrl($Media);
//         $PreviewImageLocation = Asset('plugins/FileUpload/images/file.png');
//         if (getimagesize($ScratchFileName)) {
//            $FinalImageLocation = Asset(
//               'uploads/'
//               .FileUploadPlugin::FindLocalMediaFolder($MediaID, Gdn::Session()->UserID, FALSE, TRUE)
//               .'/'
//               .$MediaID
//               .'.'
//               .GetValue('extension', pathinfo($FileName), '')
//            );
//            $PreviewImageLocation = Asset('uploads/FileUpload/scratch/'.$TempFileName);
//         }
         $MediaResponse = array(
            'Status'             => 'success',
            'MediaID'            => $MediaID,
            'Filename'           => $FileName,
            'Filesize'           => $FileSize,
            'FormatFilesize'     => Gdn_Format::Bytes($FileSize,1),
            'ProgressKey'        => $Sender->ApcKey ? $Sender->ApcKey : '',
            'PreviewImageLocation' => Url($PreviewImageLocation),
            'FinalImageLocation' => Url(MediaModel::Url($SaveFilename))
         );

      } catch (FileUploadPluginUploadErrorException $e) {
      
         $MediaResponse = array(
            'Status'          => 'failed',
            'ErrorCode'       => $e->getCode(),
            'Filename'        => $e->getFilename(),
            'StrError'        => $e->getMessage()
         );
         if (!is_null($e->getApcKey()))
            $MediaResponse['ProgressKey'] = $e->getApcKey();
         
         if ($e->getFilename() != '???')
            $MediaResponse['StrError'] = '('.$e->getFilename().') '.$MediaResponse['StrError'];
      }
      
      $Sender->SetJSON('MediaResponse', $MediaResponse);
      $Sender->Render($this->GetView('blank.php'));
   }
   
   /**
    * PostController_Checkupload_Create function.
    *
    * Controller method that allows an AJAX call to check the progress of a file
    * upload that is currently in progress.
    * 
    * @access public
    * @param mixed &$Sender
    * @return void
    */
   public function PostController_Checkupload_Create($Sender) {
      list($ApcKey) = $Sender->RequestArgs;
      
      $Sender->DeliveryMethod(DELIVERY_METHOD_JSON);
      $Sender->DeliveryType(DELIVERY_TYPE_VIEW);
      
      $KeyData = explode('_',$ApcKey);
      array_shift($KeyData);
      $UploaderID = implode('_',$KeyData);
   
      $ApcAvailable = self::ApcAvailable();
      
      $Progress = array(
         'key'          => $ApcKey,
         'uploader'     => $UploaderID,
         'apc'          => ($ApcAvailable) ? 'yes' : 'no'
      );
      
      if ($ApcAvailable) {
         
         $UploadStatus = apc_fetch('upload_'.$ApcKey, $Success);
         
         if (!$Success)
            $UploadStatus = array(
               'current'   => 0,
               'total'     => -1
            );
            
         $Progress['progress'] = ($UploadStatus['current'] / $UploadStatus['total']) * 100;
         $Progress['total'] = $UploadStatus['total'];
            
         
         $Progress['format_total'] = Gdn_Format::Bytes($Progress['total'],1);
         $Progress['cache'] = $UploadStatus;
         
      }
         
      $Sender->SetJSON('Progress', $Progress);
      $Sender->Render($this->GetView('blank.php'));
   }
   
   public static function ApcAvailable() {
      $ApcAvailable = TRUE;
      if ($ApcAvailable && !ini_get('apc.enabled')) $ApcAvailable = FALSE;
      if ($ApcAvailable && !ini_get('apc.rfc1867')) $ApcAvailable = FALSE;
      
      return $ApcAvailable;
   }
   
   /**
    * TrashFile function.
    * 
    * @access protected
    * @param mixed $FileID
    * @return void
    */
   protected function TrashFile($FileID) {
      $Media = $this->MediaModel()->GetID($FileID);
      
      if ($Media) {
         $this->MediaModel()->Delete($Media);
         $Deleted = FALSE;
         
         if (!$Deleted) {
            $DirectPath = MediaModel::PathUploads().DS.$Media->Path;
            if (file_exists($DirectPath))
               $Deleted = @unlink($DirectPath);
         }
         
         if (!$Deleted) {
            $CalcPath = FileUploadPlugin::FindLocalMedia($Media, TRUE, TRUE);
            if (file_exists($CalcPath))
               $Deleted = @unlink($CalcPath);
         }
         
      }
   }
   
   public function DiscussionModel_DeleteDiscussion_Handler($Sender) {
      $DiscussionID = $Sender->EventArguments['DiscussionID'];
      $this->MediaModel()->DeleteParent('Discussion', $DiscussionID);
   }
   
   public function CommentModel_DeleteComment_Handler($Sender) {
      $CommentID = $Sender->EventArguments['CommentID'];
      $this->MediaModel()->DeleteParent('Comment', $CommentID);
   }
   
   public function Setup() {
      $this->Structure();
      SaveToConfig('Plugins.FileUpload.Enabled', TRUE);
   }

   public function Structure() {
      $Structure = Gdn::Structure();
      $Structure
         ->Table('Media')
         ->PrimaryKey('MediaID')
         ->Column('Name', 'varchar(255)')
         ->Column('Type', 'varchar(128)')
         ->Column('Size', 'int(11)')
         ->Column('ImageWidth', 'usmallint', NULL)
         ->Column('ImageHeight', 'usmallint', NULL)
         ->Column('StorageMethod', 'varchar(24)')
         ->Column('Path', 'varchar(255)')
         ->Column('InsertUserID', 'int(11)')
         ->Column('DateInserted', 'datetime')
         ->Column('ForeignID', 'int(11)', TRUE)
         ->Column('ForeignTable', 'varchar(24)', TRUE)
         ->Set(FALSE, FALSE);
   }

   public function OnDisable() {
      RemoveFromConfig('Plugins.FileUpload.Enabled');
   }
}

class FileUploadPluginUploadErrorException extends Exception {

   protected $Filename;
   protected $ApcKey;
   
   public function __construct($Message, $Code, $Filename, $ApcKey = NULL) {
      parent::__construct($Message, $Code);
      $this->Filename = $Filename;
      $this->ApcKey = $ApcKey;
   }
   
   public function getFilename() {
      return $this->Filename;
   }

   public function getApcKey() {
      return $this->ApcKey;
   }

}
