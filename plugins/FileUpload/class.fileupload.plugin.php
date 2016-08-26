<?php
/**
 * FileUpload Plugin
 *
 * This plugin enables file uploads and attachments to discussions and comments.
 *
 * Changes:
 *  1.5      Add hooks for API uploading. Add docs. Fix constructor to call parent.
 *  1.5.6    Add hook for discussions/download.
 *  1.6      Fix the file upload plugin for external storage.
 *             Add file extensions to the non-image icons.
 *  1.7      Add support for discussions and comments placed in moderation queue (Lincoln, Nov 2012)
 *  1.7.1    Fix for file upload not working now that we have json rendered as application/json.
 *  1.8      Added the ability to restrict file uploads per category.
 *  1.8.1    Remove deprecated jQuery functions.
 *  1.8.3    Modified fileupload.js to handle dependency on jquery.popup better.
 *  1.9      Code reformatting. Remove isEnabled().
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @copyright 2009-2016 Vanilla Forums, Inc
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPL
 */

$PluginInfo['FileUpload'] = [
    'Description' => 'Images and files may be attached to discussions and comments.',
    'Version' => '1.9',
    'RequiredApplications' => ['Vanilla' => '2.1'],
    'MobileFriendly' => true,
    'RegisterPermissions' => [
        'Plugins.Attachments.Upload.Allow' => 'Garden.Profiles.Edit',
        'Plugins.Attachments.Download.Allow' => 'Garden.Profiles.Edit'
    ],
    'Author' => "Tim Gunter",
    'AuthorEmail' => 'tim@vanillaforums.com',
    'AuthorUrl' => 'http://www.vanillaforums.com'
];

/**
 * Class FileUploadPlugin
 */
class FileUploadPlugin extends Gdn_Plugin {

    /** @var array */
    protected $_MediaCache;

    /**
     * Permission checks & property prep.
     */
    public function __construct() {
        parent::__construct();

        if (!class_exists('MediaModel')) {
            require __DIR__.'/class.mediamodel.php';
        }

        $this->_MediaCache = null;
        $this->CanUpload = checkPermission('Plugins.Attachments.Upload.Allow');
        $this->CanDownload = checkPermission('Plugins.Attachments.Download.Allow');

        if ($this->CanUpload) {
            $PermissionCategory = CategoryModel::permissionCategory(Gdn::controller()->data('Category'));
            if (!val('AllowFileUploads', $PermissionCategory, true)) {
                $this->CanUpload = false;
            }
        }
    }

    /**
     * Add our CSS.
     *
     * @param AssetModel $sender
     */
    public function assetModel_styleCss_handler($sender) {
        $sender->addCssFile('fileupload.css', 'plugins/FileUpload');
    }

    /**
     * Get our cache.
     *
     * @return array|null
     */
    public function mediaCache() {
        if ($this->_MediaCache === null) {
            $this->cacheAttachedMedia(Gdn::controller());
        }
        return $this->_MediaCache;
    }

    /**
     * Get instance of MediaModel.
     *
     * @return MediaModel MediaModel
     */
    public function mediaModel() {
        static $MediaModel = null;

        if ($MediaModel === null) {
            $MediaModel = new MediaModel();
        }
        return $MediaModel;
    }

    /**
     *
     *
     * @param PluginController $Sender
     * @throws Exception
     */
    public function pluginController_fileUpload_create($Sender) {
        $Sender->title('FileUpload');
        $Sender->addSideMenu('plugin/fileupload');
        Gdn_Theme::section('Dashboard');
        $Sender->Form = new Gdn_Form();

        $this->enableSlicing($Sender);
        $this->dispatch($Sender, $Sender->RequestArgs);
    }

    /**
     *
     *
     * @param $Sender
     * @throws Exception
     */
    public function controller_delete($Sender) {
        list($Action, $MediaID) = $Sender->RequestArgs;
        $Sender->deliveryMethod(DELIVERY_METHOD_JSON);
        $Sender->deliveryType(DELIVERY_TYPE_VIEW);

        $Delete = array(
            'MediaID'    => $MediaID,
            'Status'     => 'failed'
        );

        $Media = $this->mediaModel()->getID($MediaID);
        $ForeignTable = val('ForeignTable', $Media);
        $Permission = false;

        // Get the category so we can figure out whether or not the user has permission to delete.
        if ($ForeignTable == 'discussion') {
            $PermissionCategoryID = Gdn::sql()
                ->select('c.PermissionCategoryID')
                ->from('Discussion d')
                ->join('Category c', 'd.CategoryID = c.CategoryID')
                ->where('d.DiscussionID', val('ForeignID', $Media))
                ->get()->value('PermissionCategoryID');
            $Permission = 'Vanilla.Discussions.Edit';
        } elseif ($ForeignTable == 'comment') {
            $PermissionCategoryID = Gdn::sql()
                ->select('c.PermissionCategoryID')
                ->from('Comment cm')
                ->join('Discussion d', 'd.DiscussionID = cm.DiscussionID')
                ->join('Category c', 'd.CategoryID = c.CategoryID')
                ->where('cm.CommentID', val('ForeignID', $Media))
                ->get()->value('PermissionCategoryID');
            $Permission = 'Vanilla.Comments.Edit';
        }

        if ($Media) {
            $Delete['Media'] = $Media;
            $UserID = val('UserID', Gdn::session());
            if (val('InsertUserID', $Media, null) == $UserID || Gdn::session()->checkPermission($Permission, true, 'Category', $PermissionCategoryID)) {
                $this->mediaModel()->delete($Media, true);
                $Delete['Status'] = 'success';
            } else {
                throw PermissionException();
            }
        } else {
            throw NotFoundException('Media');
        }

        $Sender->setJSON('Delete', $Delete);
        $Sender->render($this->getView('blank.php'));
    }

    /**
     * Calls FileUploadPlugin::PrepareController
     *
     * @param DiscussionController $Sender The hooked controller
     */
    public function discussionController_render_before($Sender) {
        $this->prepareController($Sender);
    }

    /**
     * Calls FileUploadPlugin::PrepareController
     *
     * @param PostController $Sender The hooked controller
     */
    public function postController_render_before($Sender) {
        $this->prepareController($Sender);
    }

    /**
     * Adds CSS and JS includes to the header of the discussion or post.
     *
     * @param mixed $Controller The hooked controller
     */
    protected function prepareController($Controller) {
        $Controller->addJsFile('fileupload.js', 'plugins/FileUpload');
        $Controller->addDefinition('apcavailable', self::apcAvailable());
        $Controller->addDefinition('uploaderuniq', uniqid());

        $PostMaxSize = Gdn_Upload::unformatFileSize(ini_get('post_max_size'));
        $FileMaxSize = Gdn_Upload::unformatFileSize(ini_get('upload_max_filesize'));
        $ConfigMaxSize = Gdn_Upload::unformatFileSize(c('Garden.Upload.MaxFileSize', '1MB'));

        $MaxSize = min($PostMaxSize, $FileMaxSize, $ConfigMaxSize);
        $Controller->addDefinition('maxuploadsize', $MaxSize);
    }

    /**
     * Calls FileUploadPlugin::DrawAttachFile
     *
     * @param PostController $Sender
     */
    public function postController_afterDiscussionFormOptions_handler($Sender) {
        if (!is_null($Discussion = val('Discussion', $Sender, null))) {
            $Sender->EventArguments['Type'] = 'Discussion';
            $Sender->EventArguments['Discussion'] = $Discussion;
            $this->attachUploadsToComment($Sender, 'discussion');
        }
        $this->drawAttachFile($Sender);
    }

    /**
     *
     *
     * @param DiscussionController $Sender
     */
    public function discussionController_beforeFormButtons_handler($Sender) {
        $this->drawAttachFile($Sender);
    }

    /**
     * DrawAttachFile function.
     *
     * Helper method that allows the plugin to insert the file uploader UI into the
     * Post Discussion and Post Comment forms.
     *
     * @access public
     * @param mixed $Sender
     */
    public function drawAttachFile($Sender) {
        if (!$this->CanUpload) {
            return;
        }
        echo $Sender->fetchView('attach_file', '', 'plugins/FileUpload');
    }

    /**
     * CacheAttachedMedia function.
     *
     * @access protected
     * @param mixed $Sender
     * @return void
     */
    protected function cacheAttachedMedia($Sender) {
        $Comments = $Sender->data('Comments');
        $CommentIDList = array();

        if ($Comments instanceof Gdn_DataSet && $Comments->numRows()) {
            $Comments->dataSeek(-1);
            while ($Comment = $Comments->nextRow()) {
                $CommentIDList[] = $Comment->CommentID;
            }
        } elseif (isset($Sender->Discussion) && $Sender->Discussion) {
            $CommentIDList[] = $Sender->DiscussionID = $Sender->Discussion->DiscussionID;
        }

        if (isset($Sender->Comment) && isset($Sender->Comment->CommentID)) {
            $CommentIDList[] = $Sender->Comment->CommentID;
        }

        if (count($CommentIDList)) {
            $DiscussionID = $Sender->data('Discussion.DiscussionID');
            $MediaData = $this->mediaModel()->preloadDiscussionMedia($DiscussionID, $CommentIDList);
        } else {
            $MediaData = false;
        }

        $MediaArray = [];
        if ($MediaData !== false) {
            $MediaData->dataSeek(-1);
            while ($Media = $MediaData->nextRow()) {
                $MediaArray[$Media->ForeignTable.'/'.$Media->ForeignID][] = $Media;
            }
        }

        $this->_MediaCache = $MediaArray;
    }

    /**
     *
     *
     * @param DiscussionController $Sender
     * @return void
     */
    public function discussionController_afterCommentBody_handler($Sender, $Args) {
        if (isset($Args['Type'])) {
            $this->attachUploadsToComment($Sender, strtolower($Args['Type']));
        } else {
            $this->attachUploadsToComment($Sender);
        }
    }

    /**
     *
     *
     * @param DiscussionController $Sender
     */
    public function discussionController_afterDiscussionBody_handler($Sender) {
        $this->attachUploadsToComment($Sender, 'discussion');
    }

    /**
     *
     *
     * @param PostController $Sender
     * @return void
     */
    public function postController_afterCommentBody_handler($Sender) {
        $this->attachUploadsToComment($Sender);
    }

    /**
     *
     *
     * @param SettingsController $Sender
     */
    public function settingsController_addEditCategory_handler($Sender) {
        $Sender->Data['_PermissionFields']['AllowFileUploads'] = array('Control' => 'CheckBox');
    }

    /**
     *
     *
     * @param Gdn_Controller $Controller
     * @param string $Type
     * @return void
     */
    protected function attachUploadsToComment($Controller, $Type = 'comment') {
        $RawType = ucfirst($Type);

        if (StringEndsWith($Controller->RequestMethod, 'Comment', true) && $Type != 'comment') {
            $Type = 'comment';
            $RawType = 'Comment';
            if (!isset($Controller->Comment)) {
                return;
            }
            $Controller->EventArguments['Comment'] = $Controller->Comment;
        }

        $MediaList = $this->mediaCache();
        if (!is_array($MediaList)) {
            return;
        }

        $Param = (($Type == 'comment') ? 'CommentID' : 'DiscussionID');
        $MediaKey = $Type.'/'.GetValue($Param, GetValue($RawType, $Controller->EventArguments));
        if (array_key_exists($MediaKey, $MediaList)) {
            include_once $Controller->fetchViewLocation('fileupload_functions', '', 'plugins/FileUpload');

            $Controller->setData('CommentMediaList', $MediaList[$MediaKey]);
            $Controller->setData('GearImage', $this->getWebResource('images/gear.png'));
            $Controller->setData('Garbage', $this->getWebResource('images/trash.png'));
            $Controller->setData('CanDownload', $this->CanDownload);
            echo $Controller->fetchView($this->getView('link_files.php'));
        }
    }

    /**
     *
     *
     * @param DiscussionController $Sender
     */
    public function discussionController_download_create($Sender) {
        if (!$this->CanDownload) {
            throw PermissionException("File could not be streamed: Access is denied");
        }

        list($MediaID) = $Sender->RequestArgs;
        $Media = $this->mediaModel()->getID($MediaID);

        if (!$Media) {
            return;
        }

        $Filename = Gdn::request()->filename();
        if (!$Filename || $Filename == 'default') {
            $Filename = $Media->Name;
        }

        $DownloadPath = combinePaths(array(MediaModel::pathUploads(),val('Path', $Media)));

        if (in_array(strtolower(pathinfo($Filename, PATHINFO_EXTENSION)), array('bmp', 'gif', 'jpg', 'jpeg', 'png'))) {
            $ServeMode = 'inline';
        } else {
            $ServeMode = 'attachment';
        }

        $Served = false;
        $this->EventArguments['DownloadPath'] = $DownloadPath;
        $this->EventArguments['ServeMode'] = $ServeMode;
        $this->EventArguments['Media'] = $Media;
        $this->EventArguments['Served'] = &$Served;
        $this->fireEvent('BeforeDownload');

        if (!$Served) {
            return Gdn_FileSystem::serveFile($DownloadPath, $Filename, $Media->Type, $ServeMode);
            throw new Exception('File could not be streamed: missing file ('.$DownloadPath.').');
        }

        exit();
    }

    /**
     * Attach files to a comment during save.
     *
     * @param PostController $Sender
     * @param array $Args
     */
    public function postController_afterCommentSave_handler($Sender, $Args) {
        if (!$Args['Comment']) {
            return;
        }

        $CommentID = $Args['Comment']->CommentID;
        if (!$CommentID) {
            return;
        }

        $AttachedFilesData = Gdn::request()->getValue('AttachedUploads');
        $AllFilesData = Gdn::request()->getValue('AllUploads');

        $this->attachAllFiles($AttachedFilesData, $AllFilesData, $CommentID, 'comment');
    }

    /**
     * Attach files to a discussion during save.
     *
     * @param PostController $Sender
     * @param array $Args
     */
    public function postController_afterDiscussionSave_handler($Sender, $Args) {
        if (!$Args['Discussion']) {
            return;
        }

        $DiscussionID = $Args['Discussion']->DiscussionID;
        if (!$DiscussionID) {
            return;
        }

        $AttachedFilesData = Gdn::request()->getValue('AttachedUploads');
        $AllFilesData = Gdn::request()->getValue('AllUploads');
        $this->EventArguments['AllFilesData'] = $AllFilesData;
        $this->EventArguments['CategoryID'] = $Args['Discussion']->CategoryID;
        $this->fireEvent("InsertDiscussionMedia");

        $this->attachAllFiles($AttachedFilesData, $AllFilesData, $DiscussionID, 'discussion');
    }

    /**
     * Attach files to a log entry; used when new content is sent to moderation queue.
     *
     * @access public
     * @param object $Sender
     * @param array $Args
     */
    public function logModel_afterInsert_handler($Sender, $Args) {
        // Only trigger if logging unapproved discussion or comment
        $Log = val('Log', $Args);
        $Type = strtolower(val('RecordType', $Log));
        $Operation = val('Operation', $Log);
        if (!in_array($Type, array('discussion', 'comment')) || $Operation != 'Pending') {
            return;
        }

        // Attach file to the log entry
        $LogID = val('LogID', $Args);
        $AttachedFilesData = Gdn::request()->getValue('AttachedUploads');
        $AllFilesData = Gdn::request()->getValue('AllUploads');

        $this->attachAllFiles($AttachedFilesData, $AllFilesData, $LogID, 'log');
    }

    /**
     * Attach files to record created by restoring a log entry.
     *
     * This happens when a discussion or comment is approved.
     *
     * @param LogModel $Sender
     * @param array $Args
     */
    public function logModel_afterRestore_handler($Sender, $Args) {
        $Log = val('Log', $Args);

        // Only trigger if restoring discussion or comment
        $Type = strtolower(val('RecordType', $Log));
        if (!in_array($Type, array('discussion', 'comment'))) {
            return;
        }

        // Reassign media records from log entry to newly inserted content
        $this->mediaModel()->reassign(val('LogID', $Log), 'log', val('InsertID', $Args), $Type);
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
    protected function attachAllFiles($AttachedFilesData, $AllFilesData, $ForeignID, $ForeignTable) {
        // No files attached
        if (!$AttachedFilesData) {
            return;
        }

        $SuccessFiles = [];
        foreach ($AttachedFilesData as $FileID) {
            $Attached = $this->attachFile($FileID, $ForeignID, $ForeignTable);
            if ($Attached) {
                $SuccessFiles[] = $FileID;
            }
        }

        // clean up failed and unattached files
        $DeleteIDs = array_diff($AllFilesData, $SuccessFiles);
        foreach ($DeleteIDs as $DeleteID) {
            $this->trashFile($DeleteID);
        }
    }

    /**
     * Create and display a thumbnail of an uploaded file.
     *
     * @param UtilityController $Sender
     * @param array $Args
     */
    public function utilityController_thumbnail_create($Sender, $Args = array()) {
        $MediaID = array_shift($Args);
        if (!is_numeric($MediaID)) {
            array_unshift($Args, $MediaID);
        }
        $SubPath = implode('/', $Args);
        // Fix mauling of protocol:// URLs.
        $SubPath = preg_replace('/:\/{1}/', '://', $SubPath);
        $Name = $SubPath;
        $Parsed = Gdn_Upload::parse($Name);

        // Get actual path to the file.
        $upload = new Gdn_UploadImage();
        $Path = $upload->copyLocal($SubPath);
        if (!file_exists($Path)) {
            throw NotFoundException('File');
        }

        // Figure out the dimensions of the upload.
        $ImageSize = getimagesize($Path);
        $SHeight = $ImageSize[1];
        $SWidth = $ImageSize[0];

        if (!in_array($ImageSize[2], array(IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG))) {
            if (is_numeric($MediaID)) {
                // Fix the thumbnail information so this isn't requested again and again.
                $Model = new MediaModel();
                $Media = array('MediaID' => $MediaID, 'ImageWidth' => 0, 'ImageHeight' => 0, 'ThumbPath' => null);
                $Model->save($Media);
            }

            $Url = Asset('/plugins/FileUpload/images/file.png');
            Redirect($Url, 301);
        }

        $Options = array();

        $ThumbHeight = MediaModel::thumbnailHeight();
        $ThumbWidth = MediaModel::thumbnailWidth();

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
                $Options['Crop'] = true;
            }
        }

        $TargetPath = "thumbnails/{$Parsed['Name']}";
        $ThumbParsed = Gdn_UploadImage::saveImageAs($Path, $TargetPath, $Height, $Width, $Options);

        // Cleanup if we're using a scratch copy
        if ($ThumbParsed['Type'] != '' || $Path != MediaModel::pathUploads().'/'.$SubPath) {
            @unlink($Path);
        }

        if (is_numeric($MediaID)) {
            // Save the thumbnail information.
            $Model = new MediaModel();
            $Media = array('MediaID' => $MediaID, 'ThumbWidth' => $ThumbParsed['Width'], 'ThumbHeight' => $ThumbParsed['Height'], 'ThumbPath' => $ThumbParsed['SaveName']);
            $Model->save($Media);
        }

        $Url = $ThumbParsed['Url'];
        redirect($Url, 301);
    }

    /**
     * Attach a file to a foreign table and ID.
     *
     * @access protected
     * @param int $FileID
     * @param int $ForeignID
     * @param string $ForeignType Lowercase.
     * @return bool Whether attach was successful.
     */
    protected function attachFile($FileID, $ForeignID, $ForeignType) {
        $Media = $this->mediaModel()->getID($FileID);
        if ($Media) {
            $Media->ForeignID = $ForeignID;
            $Media->ForeignTable = $ForeignType;
            try {
//                $PlacementStatus = $this->PlaceMedia($Media, Gdn::Session()->UserID);
                $this->mediaModel()->save($Media);
            } catch (Exception $e) {
                die($e->getMessage());
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     *
     *
     * @param mixed &$Media
     * @param mixed $UserID
     * @return bool
     */
    protected function placeMedia(&$Media, $UserID) {
        $NewFolder = FileUploadPlugin::findLocalMediaFolder($Media->MediaID, $UserID, true, false);
        $CurrentPath = array();
        foreach ($NewFolder as $FolderPart) {
            array_push($CurrentPath, $FolderPart);
            $TestFolder = CombinePaths($CurrentPath);

            if (!is_dir($TestFolder) && !@mkdir($TestFolder, 0777, true)) {
                throw new Exception("Failed creating folder '{$TestFolder}' during PlaceMedia verification loop");
            }
        }

        $FileParts = pathinfo($Media->Name);
        $SourceFilePath = combinePaths(array($this->pathUploads(),$Media->Path));
        $NewFilePath = combinePaths(array($TestFolder,$Media->MediaID.'.'.$FileParts['extension']));
        $Success = rename($SourceFilePath, $NewFilePath);
        if (!$Success) {
            throw new Exception("Failed renaming '{$SourceFilePath}' -> '{$NewFilePath}'");
        }

        $NewFilePath = FileUploadPlugin::findLocalMedia($Media, false, true);
        $Media->Path = $NewFilePath;

        return true;
    }

    /**
     *
     *
     * @param mixed $MediaID
     * @param mixed $UserID
     * @param mixed $Absolute. (default: false)
     * @param mixed $ReturnString. (default: false)
     * @return array
     */
    public static function findLocalMediaFolder($MediaID, $UserID, $Absolute = false, $ReturnString = false) {
        $DispersionFactor = c('Plugin.FileUpload.DispersionFactor', 20);
        $FolderID = $MediaID % $DispersionFactor;
        $ReturnArray = array('FileUpload',$FolderID);

        if ($Absolute) {
            array_unshift($ReturnArray, MediaModel::pathUploads());
        }

        return ($ReturnString) ? implode(DS,$ReturnArray) : $ReturnArray;
    }

    /**
     *
     *
     * @param mixed $Media
     * @param mixed $Absolute. (default: false)
     * @param mixed $ReturnString. (default: false)
     * @return array
     */
    public static function findLocalMedia($Media, $Absolute = false, $ReturnString = false) {
        $ArrayPath = FileUploadPlugin::findLocalMediaFolder($Media->MediaID, $Media->InsertUserID, $Absolute, false);

        $FileParts = pathinfo($Media->Name);
        $RealFileName = $Media->MediaID.'.'.$FileParts['extension'];
        array_push($ArrayPath, $RealFileName);

        return ($ReturnString) ? implode(DS, $ArrayPath) : $ArrayPath;
    }

    /**
     * Allows plugin to handle ajax file uploads.
     *
     * @access public
     * @param object $Sender
     */
    public function postController_upload_create($Sender) {
        list($FieldName) = $Sender->RequestArgs;

        $Sender->deliveryMethod(DELIVERY_METHOD_JSON);
        $Sender->deliveryType(DELIVERY_TYPE_VIEW);

        include_once $Sender->fetchViewLocation('fileupload_functions', '', 'plugins/FileUpload');

        $Sender->FieldName = $FieldName;
        $Sender->ApcKey = Gdn::request()->getValueFrom(Gdn_Request::INPUT_POST,'APC_UPLOAD_PROGRESS');

        $FileData = Gdn::request()->getValueFrom(Gdn_Request::INPUT_FILES, $FieldName, false);
        try {
            if (!$this->CanUpload) {
                throw new FileUploadPluginUploadErrorException("You do not have permission to upload files", 11, '???');
            }
            if (!$Sender->Form->isPostBack()) {
                $PostMaxSize = ini_get('post_max_size');
                throw new FileUploadPluginUploadErrorException("The post data was too big (max {$PostMaxSize})", 10, '???');
            }

            if (!$FileData) {
                throw new FileUploadPluginUploadErrorException("No file data could be found in your post", 10, '???');
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
                        $ErrorString = sprintf(t('The uploaded file was too big (max %s).'), $MaxUploadSize);
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

            // Analyze file extension
            $FileNameParts = pathinfo($FileName);
            $Extension = strtolower($FileNameParts['extension']);
            $AllowedExtensions = C('Garden.Upload.AllowedFileExtensions', array("*"));
            if (!in_array($Extension, $AllowedExtensions) && !in_array('*',$AllowedExtensions)) {
                throw new FileUploadPluginUploadErrorException("Uploaded file type is not allowed.", 11, $FileName, $FileKey);
            }

            // Check upload size
            $MaxUploadSize = Gdn_Upload::unformatFileSize(c('Garden.Upload.MaxFileSize', '1G'));
            if ($FileSize > $MaxUploadSize) {
                $Message = sprintf(t('The uploaded file was too big (max %s).'), Gdn_Upload::formatFileSize($MaxUploadSize));
                throw new FileUploadPluginUploadErrorException($Message, 11, $FileName, $FileKey);
            }

            // Build filename
            $SaveFilename = md5(microtime()).'.'.strtolower($Extension);
            $SaveFilename = '/FileUpload/'.substr($SaveFilename, 0, 2).'/'.substr($SaveFilename, 2);

            // Get the image size before doing anything.
            list($ImageWidth, $ImageHeight, $ImageType) = Gdn_UploadImage::imageSize($FileTemp, $FileName);

            // Fire event for hooking save location
            $this->EventArguments['Path'] = $FileTemp;
            $Parsed = Gdn_Upload::parse($SaveFilename);
            $this->EventArguments['Parsed'] =& $Parsed;
            $this->EventArguments['OriginalFilename'] = $FileName;
            $Handled = false;
            $this->EventArguments['Handled'] =& $Handled;
            $this->EventArguments['ImageType'] = $ImageType;
            $this->fireAs('Gdn_Upload')->fireEvent('SaveAs');

            if (!$Handled) {
                // Build save location
                $SavePath = MediaModel::pathUploads().$SaveFilename;
                if (!is_dir(dirname($SavePath))) {
                    @mkdir(dirname($SavePath), 0777, true);
                }
                if (!is_dir(dirname($SavePath))) {
                    throw new FileUploadPluginUploadErrorException("Internal error, could not save the file.", 9, $FileName);
                }

                // Move to permanent location
                // Use SaveImageAs so that image is rotated if necessary
                if ($ImageType !== false) {
                    try {
                        $ImgParsed = Gdn_UploadImage::saveImageAs($FileTemp, $SavePath);
                        $MoveSuccess = true;
                        // In case image got rotated
                        $ImageWidth = $ImgParsed['Width'];
                        $ImageHeight = $ImgParsed['Height'];
                    } catch(Exception $Ex) {
                        // In case it was an image, but not a supported type - still upload
                        $MoveSuccess = @move_uploaded_file($FileTemp, $SavePath);
                    }
                } else {
                    // If not an image, just upload it
                    $MoveSuccess = @move_uploaded_file($FileTemp, $SavePath);
                }
                if (!$MoveSuccess) {
                    throw new FileUploadPluginUploadErrorException("Internal error, could not move the file.", 9, $FileName);
                }
            } else {
                $SaveFilename = $Parsed['SaveName'];
            }

            // Save Media data
            $Media = array(
                'Name' => $FileName,
                'Type' => $FileType,
                'Size' => $FileSize,
                'ImageWidth'  => $ImageWidth,
                'ImageHeight' => $ImageHeight,
                'InsertUserID' => Gdn::session()->UserID,
                'DateInserted' => date('Y-m-d H:i:s'),
                'StorageMethod' => 'local',
                'Path' => $SaveFilename
            );
            $MediaID = $this->mediaModel()->save($Media);
            $Media['MediaID'] = $MediaID;

            $MediaResponse = array(
                'Status' => 'success',
                'MediaID' => $MediaID,
                'Filename' => $FileName,
                'Filesize' => $FileSize,
                'FormatFilesize' => Gdn_Format::bytes($FileSize,1),
                'ProgressKey' => $Sender->ApcKey ? $Sender->ApcKey : '',
                'Thumbnail' => base64_encode(MediaThumbnail($Media)),
                'FinalImageLocation' => Url(MediaModel::url($Media)),
                'Parsed' => $Parsed
            );
        } catch (FileUploadPluginUploadErrorException $e) {
            $MediaResponse = array(
                'Status' => 'failed',
                'ErrorCode' => $e->getCode(),
                'Filename' => $e->getFilename(),
                'StrError' => $e->getMessage()
            );

            if (!is_null($e->getApcKey())) {
                $MediaResponse['ProgressKey'] = $e->getApcKey();
            }

            if ($e->getFilename() != '???') {
                $MediaResponse['StrError'] = '('.$e->getFilename().') '.$MediaResponse['StrError'];
            }
        } catch (Exception $Ex) {
            $MediaResponse = array(
                'Status' => 'failed',
                'ErrorCode' => $Ex->getCode(),
                'StrError' => $Ex->getMessage()
            );
        }

        $Sender->setJSON('MediaResponse', $MediaResponse);

        // Kludge: This needs to have a content type of text/* because it's in an iframe.
        ob_clean();
        header('Content-Type: text/html');
        echo json_encode($Sender->getJson());
        die();
    }

    /**
     * Controller method that allows an AJAX call to check the progress of a file
     * upload that is currently in progress.
     *
     * @access public
     * @param object $Sender
     */
    public function postController_checkUpload_create($Sender) {
        list($ApcKey) = $Sender->RequestArgs;

        $Sender->deliveryMethod(DELIVERY_METHOD_JSON);
        $Sender->deliveryType(DELIVERY_TYPE_VIEW);

        $KeyData = explode('_',$ApcKey);
        array_shift($KeyData);
        $UploaderID = implode('_',$KeyData);

        $ApcAvailable = self::apcAvailable();

        $Progress = array(
            'key' => $ApcKey,
            'uploader' => $UploaderID,
            'apc' => ($ApcAvailable) ? 'yes' : 'no'
        );

        if ($ApcAvailable) {
            $Success = false;
            $UploadStatus = apc_fetch('upload_'.$ApcKey, $Success);

            if (!$Success) {
                $UploadStatus = ['current' => 0, 'total' => -1];
            }

            $Progress['progress'] = ($UploadStatus['current'] / $UploadStatus['total']) * 100;
            $Progress['total'] = $UploadStatus['total'];
            $Progress['format_total'] = Gdn_Format::bytes($Progress['total'],1);
            $Progress['cache'] = $UploadStatus;
        }

        $Sender->setJSON('Progress', $Progress);
        $Sender->render($this->getView('blank.php'));
    }

    /**
     * Update Gdn_Media when a discussion is transformed into a comment due to a merge.
     *
     * @param object $sender Sending controller instance
     * @param array $args Event's arguments
     */
    public function base_transformDiscussionToComment_handler($sender, $args) {
        $this->mediaModel()->reassign(
             val('DiscussionID', $args['SourceDiscussion']),
             'discussion',
             val('CommentID', $args['TargetComment']),
             'comment'
        );
    }

    /**
     *
     *
     * @return bool
     */
    public static function apcAvailable() {
        $ApcAvailable = true;

        if ($ApcAvailable && !ini_get('apc.enabled')) {
            $ApcAvailable = false;
        }

        if ($ApcAvailable && !ini_get('apc.rfc1867')) {
            $ApcAvailable = false;
        }

        return $ApcAvailable;
    }

    /**
     * Delete an uploaded file & its media record.
     *
     * @param int $MediaID Unique ID on Media table.
     */
    protected function trashFile($MediaID) {
        $Media = $this->mediaModel()->getID($MediaID);

        if ($Media) {
            $this->mediaModel()->delete($Media);
            $Deleted = false;

            // Allow interception
            $this->EventArguments['Parsed'] = Gdn_Upload::parse($Media->Path);
            $this->EventArguments['Handled'] =& $Deleted; // Allow skipping steps below
            $this->fireEvent('TrashFile');

            if (!$Deleted) {
                $DirectPath = MediaModel::pathUploads().DS.$Media->Path;
                if (file_exists($DirectPath)) {
                    $Deleted = @unlink($DirectPath);
                }
            }

            if (!$Deleted) {
                $CalcPath = FileUploadPlugin::findLocalMedia($Media, true, true);
                if (file_exists($CalcPath)) {
                    @unlink($CalcPath);
                }
            }

        }
    }

    /**
     *
     *
     * @param DiscussionModel $Sender
     */
    public function discussionModel_deleteDiscussion_handler($Sender) {
        $DiscussionID = $Sender->EventArguments['DiscussionID'];
        $this->mediaModel()->deleteParent('Discussion', $DiscussionID);
    }

    /**
     *
     *
     * @param CommentModel $Sender
     */
    public function commentModel_deleteComment_handler($Sender) {
        $CommentID = $Sender->EventArguments['CommentID'];
        $this->mediaModel()->deleteParent('Comment', $CommentID);
    }

    /**
     * Run once on enable.
     */
    public function setup() {
        $this->structure();
    }

    /**
     * Database update.
     *
     * @throws Exception
     */
    public function structure() {
        Gdn::structure()->table('Media')
            ->primaryKey('MediaID')
            ->column('Name', 'varchar(255)')
            ->column('Type', 'varchar(128)')
            ->column('Size', 'int(11)')
            ->column('ImageWidth', 'usmallint', null)
            ->column('ImageHeight', 'usmallint', null)
            ->column('StorageMethod', 'varchar(24)', 'local')
            ->column('Path', 'varchar(255)')

            ->column('ThumbWidth', 'usmallint', null)
            ->column('ThumbHeight', 'usmallint', null)
            ->column('ThumbPath', 'varchar(255)', null)

            ->column('InsertUserID', 'int(11)')
            ->column('DateInserted', 'datetime')
            ->column('ForeignID', 'int(11)', true)
            ->column('ForeignTable', 'varchar(24)', true)
            ->set();

        Gdn::structure()->table('Category')
            ->column('AllowFileUploads', 'tinyint(1)', '1')
            ->set();
    }
}

/**
 * Class FileUploadPluginUploadErrorException
 */
class FileUploadPluginUploadErrorException extends Exception {

    /** @var Exception  */
    protected $Filename;

    /** @var null  */
    protected $ApcKey;

    /**
     * FileUploadPluginUploadErrorException constructor.
     *
     * @param string $Message
     * @param int $Code
     * @param Exception $Filename
     * @param null $ApcKey
     */
    public function __construct($Message, $Code, $Filename, $ApcKey = null) {
        parent::__construct($Message, $Code);
        $this->Filename = $Filename;
        $this->ApcKey = $ApcKey;
    }

    /**
     * @return Exception
     */
    public function getFilename() {
        return $this->Filename;
    }

    /**
     * @return null
     */
    public function getApcKey() {
        return $this->ApcKey;
    }
}
