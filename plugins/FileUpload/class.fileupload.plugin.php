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
 *  1.9      Code reformatting. Remove isEnabled(). Restore attach option on edit comment.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @copyright 2009-2016 Vanilla Forums, Inc
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPL
 */

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

        $this->_MediaCache = null;
        $this->CanUpload = checkPermission('Plugins.Attachments.Upload.Allow');
        $this->CanDownload = checkPermission('Plugins.Attachments.Download.Allow');

        if ($this->CanUpload) {
            $permissionCategory = CategoryModel::permissionCategory(Gdn::controller()->data('Category'));
            if (!val('AllowFileUploads', $permissionCategory, true)) {
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
        static $mediaModel = null;

        if ($mediaModel === null) {
            $mediaModel = new MediaModel();
        }
        return $mediaModel;
    }

    /**
     *
     *
     * @param PluginController $sender
     * @throws Exception
     */
    public function pluginController_fileUpload_create($sender) {
        $sender->title('FileUpload');
        Gdn_Theme::section('Dashboard');
        $sender->Form = new Gdn_Form();
        $this->dispatch($sender, $sender->RequestArgs);
    }

    /**
     *
     *
     * @param $sender
     * @throws Exception
     */
    public function controller_delete($sender) {
        list($action, $mediaID) = $sender->RequestArgs;
        $sender->deliveryMethod(DELIVERY_METHOD_JSON);
        $sender->deliveryType(DELIVERY_TYPE_VIEW);

        $delete = [
            'MediaID'    => $mediaID,
            'Status'     => 'failed'
        ];

        $media = $this->mediaModel()->getID($mediaID);
        $foreignTable = val('ForeignTable', $media);
        $permission = false;

        // Get the category so we can figure out whether or not the user has permission to delete.
        if ($foreignTable == 'discussion') {
            $permissionCategoryID = Gdn::sql()
                ->select('c.PermissionCategoryID')
                ->from('Discussion d')
                ->join('Category c', 'd.CategoryID = c.CategoryID')
                ->where('d.DiscussionID', val('ForeignID', $media))
                ->get()->value('PermissionCategoryID');
            $permission = 'Vanilla.Discussions.Edit';
        } elseif ($foreignTable == 'comment') {
            $permissionCategoryID = Gdn::sql()
                ->select('c.PermissionCategoryID')
                ->from('Comment cm')
                ->join('Discussion d', 'd.DiscussionID = cm.DiscussionID')
                ->join('Category c', 'd.CategoryID = c.CategoryID')
                ->where('cm.CommentID', val('ForeignID', $media))
                ->get()->value('PermissionCategoryID');
            $permission = 'Vanilla.Comments.Edit';
        }

        if ($media) {
            $delete['Media'] = $media;
            $userID = val('UserID', Gdn::session());
            if (val('InsertUserID', $media, null) == $userID || Gdn::session()->checkPermission($permission, true, 'Category', $permissionCategoryID)) {
                $this->mediaModel()->delete($media, true);
                $delete['Status'] = 'success';
            } else {
                throw PermissionException();
            }
        } else {
            throw NotFoundException('Media');
        }

        $sender->setJSON('Delete', $delete);
        $sender->render($sender->fetchViewLocation('blank', '', 'plugins/FileUpload'));
    }

    /**
     * Calls FileUploadPlugin::PrepareController
     *
     * @param DiscussionController $sender The hooked controller
     */
    public function discussionController_render_before($sender) {
        $this->prepareController($sender);
    }

    /**
     * Calls FileUploadPlugin::PrepareController
     *
     * @param PostController $sender The hooked controller
     */
    public function postController_render_before($sender) {
        $this->prepareController($sender);
    }

    /**
     * Adds CSS and JS includes to the header of the discussion or post.
     *
     * @param mixed $controller The hooked controller
     */
    protected function prepareController($controller) {
        $controller->addJsFile('fileupload.js', 'plugins/FileUpload');
        $controller->addDefinition('apcavailable', self::apcAvailable());
        $controller->addDefinition('uploaderuniq', uniqid());

        $postMaxSize = Gdn_Upload::unformatFileSize(ini_get('post_max_size'));
        $fileMaxSize = Gdn_Upload::unformatFileSize(ini_get('upload_max_filesize'));
        $configMaxSize = Gdn_Upload::unformatFileSize(c('Garden.Upload.MaxFileSize', '1MB'));

        $maxSize = min($postMaxSize, $fileMaxSize, $configMaxSize);
        $controller->addDefinition('maxuploadsize', $maxSize);
    }

    /**
     * Calls FileUploadPlugin::DrawAttachFile
     *
     * @param PostController $sender
     */
    public function postController_afterDiscussionFormOptions_handler($sender) {
        if (!is_null($discussion = val('Discussion', $sender, null))) {
            $sender->EventArguments['Type'] = 'Discussion';
            $sender->EventArguments['Discussion'] = $discussion;
            $this->attachUploadsToComment($sender, 'discussion');
        }
        $this->drawAttachFile($sender);
    }

    /**
     * Add "Attach a file" to edit comment form.
     *
     * @param $sender
     */
    public function postController_afterBodyField_handler($sender) {
        $this->drawAttachFile($sender);
    }

    /**
     *
     *
     * @param DiscussionController $sender
     */
    public function discussionController_beforeFormButtons_handler($sender) {
        $this->drawAttachFile($sender);
    }

    /**
     * DrawAttachFile function.
     *
     * Helper method that allows the plugin to insert the file uploader UI into the
     * Post Discussion and Post Comment forms.
     *
     * @access public
     * @param mixed $sender
     */
    public function drawAttachFile($sender) {
        if (!$this->CanUpload) {
            return;
        }
        echo $sender->fetchView('attach_file', '', 'plugins/FileUpload');
    }

    /**
     * CacheAttachedMedia function.
     *
     * @access protected
     * @param mixed $sender
     * @return void
     */
    protected function cacheAttachedMedia($sender) {
        $comments = $sender->data('Comments');
        $commentIDList = [];

        if ($comments instanceof Gdn_DataSet && $comments->numRows()) {
            $comments->dataSeek(-1);
            while ($comment = $comments->nextRow()) {
                $commentIDList[] = $comment->CommentID;
            }
        } elseif (isset($sender->Discussion) && $sender->Discussion) {
            $commentIDList[] = $sender->DiscussionID = $sender->Discussion->DiscussionID;
        }

        if (isset($sender->Comment) && isset($sender->Comment->CommentID)) {
            $commentIDList[] = $sender->Comment->CommentID;
        }

        if (count($commentIDList)) {
            $discussionID = $sender->data('Discussion.DiscussionID');
            $mediaData = $this->preloadDiscussionMedia($discussionID, $commentIDList);
        } else {
            $mediaData = false;
        }

        $mediaArray = [];
        if ($mediaData !== false) {
            $mediaData->dataSeek(-1);
            while ($media = $mediaData->nextRow()) {
                $mediaArray[$media->ForeignTable.'/'.$media->ForeignID][] = $media;
            }
        }

        $this->_MediaCache = $mediaArray;
    }

    /**
     *
     *
     * @param DiscussionController $sender
     * @return void
     */
    public function discussionController_afterCommentBody_handler($sender, $args) {
        if (isset($args['Type'])) {
            $this->attachUploadsToComment($sender, strtolower($args['Type']));
        } else {
            $this->attachUploadsToComment($sender);
        }
    }

    /**
     *
     *
     * @param DiscussionController $sender
     */
    public function discussionController_afterDiscussionBody_handler($sender) {
        $this->attachUploadsToComment($sender, 'discussion');
    }

    /**
     *
     *
     * @param PostController $sender
     * @return void
     */
    public function postController_afterCommentBody_handler($sender) {
        $this->attachUploadsToComment($sender);
    }

    /**
     *
     *
     * @param SettingsController $sender
     */
    public function settingsController_addEditCategory_handler($sender) {
        $sender->Data['_PermissionFields']['AllowFileUploads'] = ['Control' => 'CheckBox'];
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
        $MediaKey = $Type.'/'.val($Param, val($RawType, $Controller->EventArguments));
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
     * @param DiscussionController $sender
     */
    public function discussionController_download_create($sender) {
        if (!$this->CanDownload) {
            throw PermissionException("File could not be streamed: Access is denied");
        }

        list($mediaID) = $sender->RequestArgs;
        $media = $this->mediaModel()->getID($mediaID);

        if (!$media) {
            return;
        }

        $filename = Gdn::request()->filename();
        if (!$filename || $filename == 'default') {
            $filename = $media->Name;
        }

        $downloadPath = combinePaths([self::pathUploads(),val('Path', $media)]);

        if (in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), ['bmp', 'gif', 'jpg', 'jpeg', 'png'])) {
            $serveMode = 'inline';
        } else {
            $serveMode = 'attachment';
        }

        $served = false;
        $this->EventArguments['DownloadPath'] = $downloadPath;
        $this->EventArguments['ServeMode'] = $serveMode;
        $this->EventArguments['Media'] = $media;
        $this->EventArguments['Served'] = &$served;
        $this->fireEvent('BeforeDownload');

        if (!$served) {
            return Gdn_FileSystem::serveFile($downloadPath, $filename, $media->Type, $serveMode);
            throw new Exception('File could not be streamed: missing file ('.$downloadPath.').');
        }

        exit();
    }

    /**
     * Attach files to a comment during save.
     *
     * @param PostController $sender
     * @param array $args
     */
    public function postController_afterCommentSave_handler($sender, $args) {
        if (!$args['Comment']) {
            return;
        }

        $commentID = $args['Comment']->CommentID;
        if (!$commentID) {
            return;
        }

        $attachedFilesData = Gdn::request()->getValue('AttachedUploads');
        $allFilesData = Gdn::request()->getValue('AllUploads');

        $this->attachAllFiles($attachedFilesData, $allFilesData, $commentID, 'comment');
    }

    /**
     * Attach files to a discussion during save.
     *
     * @param PostController $sender
     * @param array $args
     */
    public function postController_afterDiscussionSave_handler($sender, $args) {
        if (!$args['Discussion']) {
            return;
        }

        $discussionID = $args['Discussion']->DiscussionID;
        if (!$discussionID) {
            return;
        }

        $attachedFilesData = Gdn::request()->getValue('AttachedUploads');
        $allFilesData = Gdn::request()->getValue('AllUploads');
        $this->EventArguments['AllFilesData'] = $allFilesData;
        $this->EventArguments['CategoryID'] = $args['Discussion']->CategoryID;
        $this->fireEvent("InsertDiscussionMedia");

        $this->attachAllFiles($attachedFilesData, $allFilesData, $discussionID, 'discussion');
    }

    /**
     * Attach files to a log entry; used when new content is sent to moderation queue.
     *
     * @access public
     * @param object $sender
     * @param array $args
     */
    public function logModel_afterInsert_handler($sender, $args) {
        // Only trigger if logging unapproved discussion or comment
        $log = val('Log', $args);
        $type = strtolower(val('RecordType', $log));
        $operation = val('Operation', $log);
        if (!in_array($type, ['discussion', 'comment']) || $operation != 'Pending') {
            return;
        }

        // Attach file to the log entry
        $logID = val('LogID', $args);
        $attachedFilesData = Gdn::request()->getValue('AttachedUploads');
        $allFilesData = Gdn::request()->getValue('AllUploads');

        $this->attachAllFiles($attachedFilesData, $allFilesData, $logID, 'log');
    }

    /**
     * Attach files to record created by restoring a log entry.
     *
     * This happens when a discussion or comment is approved.
     *
     * @param LogModel $sender
     * @param array $args
     */
    public function logModel_afterRestore_handler($sender, $args) {
        $log = val('Log', $args);

        // Only trigger if restoring discussion or comment
        $type = strtolower(val('RecordType', $log));
        if (!in_array($type, ['discussion', 'comment'])) {
            return;
        }

        // Reassign media records from log entry to newly inserted content
        $this->mediaModel()->reassign(val('LogID', $log), 'log', val('InsertID', $args), $type);
    }

    /**
     * AttachAllFiles function.
     *
     * @access protected
     * @param mixed $attachedFilesData
     * @param mixed $allFilesData
     * @param mixed $foreignID
     * @param mixed $foreignTable
     * @return void
     */
    protected function attachAllFiles($attachedFilesData, $allFilesData, $foreignID, $foreignTable) {
        // No files attached
        if (!$attachedFilesData) {
            return;
        }

        $successFiles = [];
        foreach ($attachedFilesData as $fileID) {
            $attached = $this->attachFile($fileID, $foreignID, $foreignTable);
            if ($attached) {
                $successFiles[] = $fileID;
            }
        }

        // clean up failed and unattached files
        $deleteIDs = array_diff($allFilesData, $successFiles);
        foreach ($deleteIDs as $deleteID) {
            $this->trashFile($deleteID);
        }
    }

    /**
     * Create and display a thumbnail of an uploaded file.
     *
     * @param UtilityController $sender
     * @param array $args
     */
    public function utilityController_thumbnail_create($sender, $args = []) {
        $mediaID = array_shift($args);
        if (!is_numeric($mediaID)) {
            array_unshift($args, $mediaID);
        }
        $subPath = implode('/', $args);
        // Fix mauling of protocol:// URLs.
        $subPath = preg_replace('/:\/{1}/', '://', $subPath);
        $name = $subPath;
        $parsed = Gdn_Upload::parse($name);

        // Get actual path to the file.
        $upload = new Gdn_UploadImage();
        $path = $upload->copyLocal($subPath);
        if (!file_exists($path)) {
            throw NotFoundException('File');
        }

        // Figure out the dimensions of the upload.
        $imageSize = getimagesize($path);
        $sHeight = $imageSize[1];
        $sWidth = $imageSize[0];

        if (!in_array($imageSize[2], [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG])) {
            if (is_numeric($mediaID)) {
                // Fix the thumbnail information so this isn't requested again and again.
                $model = new MediaModel();
                $media = ['MediaID' => $mediaID, 'ImageWidth' => 0, 'ImageHeight' => 0, 'ThumbPath' => null];
                $model->save($media);
            }

            $url = Asset('/plugins/FileUpload/images/file.png');
            redirectTo($url, 301);
        }

        $options = [];

        $thumbHeight = self::thumbnailHeight();
        $thumbWidth = self::thumbnailWidth();

        if (!$thumbHeight || $sHeight < $thumbHeight) {
            $height = $sHeight;
            $width = $sWidth;
        } else {
            $height = $thumbHeight;
            $width = round($height * $sWidth / $sHeight);
        }

        if ($thumbWidth && $width > $thumbWidth) {
            $width = $thumbWidth;

            if (!$thumbHeight) {
                $height = round($width * $sHeight / $sWidth);
            } else {
                $options['Crop'] = true;
            }
        }

        $targetPath = "thumbnails/{$parsed['Name']}";
        $thumbParsed = Gdn_UploadImage::saveImageAs($path, $targetPath, $height, $width, $options);

        // Cleanup if we're using a scratch copy
        if ($thumbParsed['Type'] != '' || $path != self::pathUploads().'/'.$subPath) {
            @unlink($path);
        }

        if (is_numeric($mediaID)) {
            // Save the thumbnail information.
            $model = new MediaModel();
            $media = ['MediaID' => $mediaID, 'ThumbWidth' => $thumbParsed['Width'], 'ThumbHeight' => $thumbParsed['Height'], 'ThumbPath' => $thumbParsed['SaveName']];
            $model->save($media);
        }

        $url = $thumbParsed['Url'];
        redirectTo($url, 301, false);
    }

    /**
     * Attach a file to a foreign table and ID.
     *
     * @access protected
     * @param int $fileID
     * @param int $foreignID
     * @param string $foreignType Lowercase.
     * @return bool Whether attach was successful.
     */
    protected function attachFile($fileID, $foreignID, $foreignType) {
        $media = $this->mediaModel()->getID($fileID);
        if ($media) {
            $media->ForeignID = $foreignID;
            $media->ForeignTable = $foreignType;
            try {
//                $PlacementStatus = $this->PlaceMedia($Media, Gdn::Session()->UserID);
                $this->mediaModel()->save($media);
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
     * @param mixed &$media
     * @param mixed $userID
     * @return bool
     */
    protected function placeMedia(&$media, $userID) {
        $newFolder = FileUploadPlugin::findLocalMediaFolder($media->MediaID, $userID, true, false);
        $currentPath = [];
        foreach ($newFolder as $folderPart) {
            array_push($currentPath, $folderPart);
            $testFolder = CombinePaths($currentPath);

            if (!is_dir($testFolder) && !@mkdir($testFolder, 0777, true)) {
                throw new Exception("Failed creating folder '{$testFolder}' during PlaceMedia verification loop");
            }
        }

        $fileParts = pathinfo($media->Name);
        $sourceFilePath = combinePaths([self::pathUploads(), $media->Path]);
        $newFilePath = combinePaths([$testFolder,$media->MediaID.'.'.$fileParts['extension']]);
        $success = rename($sourceFilePath, $newFilePath);
        if (!$success) {
            throw new Exception("Failed renaming '{$sourceFilePath}' -> '{$newFilePath}'");
        }

        $newFilePath = FileUploadPlugin::findLocalMedia($media, false, true);
        $media->Path = $newFilePath;

        return true;
    }

    /**
     *
     *
     * @param mixed $mediaID
     * @param mixed $userID
     * @param mixed $absolute. (default: false)
     * @param mixed $returnString. (default: false)
     * @return array
     */
    public static function findLocalMediaFolder($mediaID, $userID, $absolute = false, $returnString = false) {
        $dispersionFactor = c('Plugin.FileUpload.DispersionFactor', 20);
        $folderID = $mediaID % $dispersionFactor;
        $returnArray = ['FileUpload',$folderID];

        if ($absolute) {
            array_unshift($returnArray, self::pathUploads());
        }

        return ($returnString) ? implode(DS,$returnArray) : $returnArray;
    }

    /**
     *
     *
     * @param mixed $media
     * @param mixed $absolute. (default: false)
     * @param mixed $returnString. (default: false)
     * @return array
     */
    public static function findLocalMedia($media, $absolute = false, $returnString = false) {
        $arrayPath = FileUploadPlugin::findLocalMediaFolder($media->MediaID, $media->InsertUserID, $absolute, false);

        $fileParts = pathinfo($media->Name);
        $realFileName = $media->MediaID.'.'.$fileParts['extension'];
        array_push($arrayPath, $realFileName);

        return ($returnString) ? implode(DS, $arrayPath) : $arrayPath;
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
            $AllowedExtensions = C('Garden.Upload.AllowedFileExtensions', ["*"]);
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
                $SavePath = self::pathUploads().$SaveFilename;
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
            $Media = [
                'Name' => $FileName,
                'Type' => $FileType,
                'Size' => $FileSize,
                'ImageWidth'  => $ImageWidth,
                'ImageHeight' => $ImageHeight,
                'InsertUserID' => Gdn::session()->UserID,
                'DateInserted' => date('Y-m-d H:i:s'),
                'Path' => $SaveFilename
            ];
            $MediaID = $this->mediaModel()->save($Media);
            $Media['MediaID'] = $MediaID;

            $MediaResponse = [
                'Status' => 'success',
                'MediaID' => $MediaID,
                'Filename' => $FileName,
                'Filesize' => $FileSize,
                'FormatFilesize' => Gdn_Format::bytes($FileSize,1),
                'ProgressKey' => $Sender->ApcKey ? $Sender->ApcKey : '',
                'Thumbnail' => base64_encode(MediaThumbnail($Media)),
                'FinalImageLocation' => Url(self::url($Media)),
                'Parsed' => $Parsed
            ];
        } catch (FileUploadPluginUploadErrorException $e) {
            $MediaResponse = [
                'Status' => 'failed',
                'ErrorCode' => $e->getCode(),
                'Filename' => $e->getFilename(),
                'StrError' => $e->getMessage()
            ];

            if (!is_null($e->getApcKey())) {
                $MediaResponse['ProgressKey'] = $e->getApcKey();
            }

            if ($e->getFilename() != '???') {
                $MediaResponse['StrError'] = '('.$e->getFilename().') '.$MediaResponse['StrError'];
            }
        } catch (Exception $Ex) {
            $MediaResponse = [
                'Status' => 'failed',
                'ErrorCode' => $Ex->getCode(),
                'StrError' => $Ex->getMessage()
            ];
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
     * @param object $sender
     */
    public function postController_checkUpload_create($sender) {
        list($apcKey) = $sender->RequestArgs;

        $sender->deliveryMethod(DELIVERY_METHOD_JSON);
        $sender->deliveryType(DELIVERY_TYPE_VIEW);

        $keyData = explode('_',$apcKey);
        array_shift($keyData);
        $uploaderID = implode('_',$keyData);
        $apcAvailable = self::apcAvailable();


        $progress = [
            'key' => $apcKey,
            'uploader' => $uploaderID,
            'apc' => ($apcAvailable) ? 'yes' : 'no'
        ];

        if ($apcAvailable) {
            $success = false;
            $uploadStatus = apc_fetch('upload_'.$apcKey, $success);

            if (!$success) {
                $uploadStatus = ['current' => 0, 'total' => -1];
            }

            $progress['progress'] = ($uploadStatus['current'] / $uploadStatus['total']) * 100;
            $progress['total'] = $uploadStatus['total'];
            $progress['format_total'] = Gdn_Format::bytes($progress['total'],1);
            $progress['cache'] = $uploadStatus;
        }

        $sender->setJSON('Progress', $progress);
        $sender->render($this->getView('blank.php'));
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
        $apcAvailable = true;

        if ($apcAvailable && !ini_get('apc.enabled')) {
            $apcAvailable = false;
        }

        if ($apcAvailable && !ini_get('apc.rfc1867')) {
            $apcAvailable = false;
        }

        return $apcAvailable;
    }

    /**
     * Delete an uploaded file & its media record.
     *
     * @param int $mediaID Unique ID on Media table.
     */
    protected function trashFile($mediaID) {
        $media = $this->mediaModel()->getID($mediaID);

        if ($media) {
            $this->mediaModel()->delete($media);
            $deleted = false;

            // Allow interception
            $this->EventArguments['Parsed'] = Gdn_Upload::parse($media->Path);
            $this->EventArguments['Handled'] =& $deleted; // Allow skipping steps below
            $this->fireEvent('TrashFile');

            if (!$deleted) {
                $directPath = self::pathUploads().DS.$media->Path;
                if (file_exists($directPath)) {
                    $deleted = @unlink($directPath);
                }
            }

            if (!$deleted) {
                $calcPath = FileUploadPlugin::findLocalMedia($media, true, true);
                if (file_exists($calcPath)) {
                    @unlink($calcPath);
                }
            }

        }
    }

    /**
     *
     *
     * @param DiscussionModel $sender
     */
    public function discussionModel_deleteDiscussion_handler($sender) {
        $discussionID = $sender->EventArguments['DiscussionID'];
        $this->mediaModel()->deleteParent('Discussion', $discussionID);
    }

    /**
     *
     *
     * @param CommentModel $sender
     */
    public function commentModel_deleteComment_handler($sender) {
        $commentID = $sender->EventArguments['CommentID'];
        $this->mediaModel()->deleteParent('Comment', $commentID);
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
        Gdn::structure()->table('Category')
            ->column('AllowFileUploads', 'tinyint(1)', '1')
            ->set();
    }

    /**
     *
     *
     * @param $discussionID
     * @param $commentIDList
     * @return Gdn_DataSet
     * @throws Exception
     */
    public function preloadDiscussionMedia($discussionID, $commentIDList) {
        $this->fireEvent('BeforePreloadDiscussionMedia');

        $data = Gdn::sql()
            ->select('m.*')
            ->from('Media m')
            ->beginWhereGroup()
                ->where('m.ForeignID', $discussionID)
                ->where('m.ForeignTable', 'discussion')
            ->endWhereGroup()
            ->orOp()
            ->beginWhereGroup()
                ->whereIn('m.ForeignID', $commentIDList)
                ->where('m.ForeignTable', 'comment')
            ->endWhereGroup()
            ->get();

        // Assign image heights/widths where necessary.
        $data2 = $data->result();
        foreach ($data2 as &$row) {
            if ($row->ImageHeight === null || $row->ImageWidth === null) {
                list($row->ImageWidth, $row->ImageHeight) = self::getImageSize(self::pathUploads().'/'.ltrim($row->Path, '/'));
                $this->mediaModel()->update(
                    [
                        'ImageWidth' => $row->ImageWidth,
                        'ImageHeight' => $row->ImageHeight,
                    ],
                    ['MediaID' => $row->MediaID]
                );
            }
        }

		return $data;
    }

    /**
     * If passed path leads to an image, return size
     *
     * @param string $path Path to file.
     * @return array [0] => Height, [1] => Width.
     */
    public static function getImageSize($path) {
        // Static FireEvent for intercepting non-local files.
        Gdn::pluginManager()
            ->fireAs('Gdn_Upload')
            ->fireEvent('CopyLocal',[
                'Path' => &$path,
                'Parsed' => Gdn_Upload::parse($path),
            ]);

        if (!in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['gif', 'jpg', 'jpeg', 'png'])) {
            return [0, 0];
        }

        $imageSize = @getimagesize($path);
        if (is_array($imageSize)) {
            if (!in_array($imageSize[2], [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG])) {
                return [0, 0];
            }

            return [$imageSize[0], $imageSize[1]];
        }

        return [0, 0];
    }

    /**
     * Return path to upload folder.
     *
     * @return string Path to upload folder.
     */
    public static function pathUploads() {
        if (defined('PATH_LOCAL_UPLOADS')) {
            return PATH_LOCAL_UPLOADS;
        }

        return PATH_UPLOADS;
    }

    /**
     * Get thumbnail height.
     *
     * @return int
     */
    public static function thumbnailHeight() {
        static $height = false;

        if ($height === false) {
            $height = c('Plugins.FileUpload.ThumbnailHeight', 128);
        }

        return $height;
    }

    /**
     * Get thumbnail width.
     *
     * @return int
     */
    public static function thumbnailWidth() {
        static $width = false;

        if ($width === false) {
            $width = c('Plugins.FileUpload.ThumbnailWidth', 256);
        }

        return $width;
    }

    /**
     *
     *
     * @param $media
     * @return mixed|string
     */
    public static function thumbnailUrl(&$media) {
        $thumbPath = val('ThumbPath', $media);
        if ($thumbPath) {
            return Gdn_Upload::url(ltrim($thumbPath, '/'));
        }

        $width = val('ImageWidth', $media);
        $height = val('ImageHeight', $media);

        if (!$width || !$height) {
            if ($height = self::thumbnailHeight()) {
                setValue('ThumbHeight', $media, $height);
            }
            return '/plugins/FileUpload/images/file.png';
        }

        $requiresThumbnail = false;
        if (self::thumbnailHeight() && $height > self::thumbnailHeight()) {
            $requiresThumbnail = true;
        } elseif (self::thumbnailWidth() && $width > self::thumbnailWidth()) {
            $requiresThumbnail = true;
        }

        $path = ltrim(val('Path', $media), '/');
        if ($requiresThumbnail) {
            $result = url('/utility/thumbnail/'.val('MediaID', $media, 'x').'/'.$path, true);
        } else {
            $result = Gdn_Upload::url($path);
        }

        return $result;
    }

    /**
     *
     *
     * @param $media
     * @return mixed|string
     */
    public static function url($media) {
        static $useDownloadUrl = null;

        if ($useDownloadUrl === null) {
            $useDownloadUrl = c('Plugins.FileUpload.UseDownloadUrl');
        }

        if (is_string($media)) {
            $subPath = $media;
            if (method_exists('Gdn_Upload', 'Url')) {
                $url = Gdn_Upload::url("$subPath");
            } else {
                $url = "/uploads/$subPath";
            }
        } elseif ($useDownloadUrl) {
            $url = '/discussion/download/'.val('MediaID', $media).'/'.rawurlencode(val('Name', $media));
        } else {
            $subPath = ltrim(val('Path', $media), '/');
            if (method_exists('Gdn_Upload', 'Url')) {
                $url = Gdn_Upload::url("$subPath");
            } else {
                $url = "/uploads/$subPath";
            }
        }

        return $url;
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
     * @param string $message
     * @param int $code
     * @param Exception $filename
     * @param null $apcKey
     */
    public function __construct($message, $code, $filename, $apcKey = null) {
        parent::__construct($message, $code);
        $this->Filename = $filename;
        $this->ApcKey = $apcKey;
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
