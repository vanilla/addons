<?php
/**
 * @copyright 2009-2016 Vanilla Forums, Inc
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPL
 */

/**
 * Class MediaModel
 */
class MediaModel extends Gdn_Model {

    /**
     * MediaModel constructor.
     */
    public function __construct() {
        parent::__construct('Media');
    }

    /**
     * Get a media row by ID.
     *
     * @param int $MediaID The ID of the media entry.
     * @param bool $DatasetType Not used.
     * @param array $Options Not used.
     * @return array|false Returns the media row or **false** if it isn't found.
     */
    public function GetID($MediaID, $DatasetType = false, $Options = []) {
        $this->FireEvent('BeforeGetID');
        $Data = $this->SQL
            ->Select('m.*')
            //->Select('iu.*')
            ->From('Media m')
            //->Join('User iu', 'm.InsertUserID = iu.UserID', 'left') // Insert user
            ->Where('m.MediaID', $MediaID)
            ->Get()
            ->FirstRow();
		
		return $Data;
    }
    
    /**
     * Retrieve all media for a foreign record.
     * 
     * @param int $ForeignID
     * @param string $ForeignTable Lowercase.
     * @return object SQL results.
     */
    public function Reassign($ForeignID, $ForeignTable, $NewForeignID, $NewForeignTable) {
        $this->FireEvent('BeforeReassign');
        $Data = $this->SQL
            ->Update('Media')
            ->Set('ForeignID', $NewForeignID)
            ->Set('ForeignTable', $NewForeignTable)
            ->Where('ForeignID', $ForeignID)
            ->Where('ForeignTable', $ForeignTable)
            ->Put();
		
		return $Data;
    }
    
    /**
     * If passed path leads to an image, return size
     *
     * @param string $Path Path to file.
     * @return array [0] => Height, [1] => Width.
     */
    public static function GetImageSize($Path) {
        // Static FireEvent for intercepting non-local files.
        $Sender = new stdClass();
        $Sender->Returns = array();
        $Sender->EventArguments = array();
        $Sender->EventArguments['Path'] =& $Path;
        $Sender->EventArguments['Parsed'] = Gdn_Upload::Parse($Path);
        Gdn::PluginManager()->CallEventHandlers($Sender, 'Gdn_Upload', 'CopyLocal');
    
        if (!in_array(strtolower(pathinfo($Path, PATHINFO_EXTENSION)), array('gif', 'jpg', 'jpeg', 'png')))
            return array(0, 0);

        $ImageSize = @getimagesize($Path);
        if (is_array($ImageSize)) {
            if (!in_array($ImageSize[2], array(IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG)))
                return array(0, 0);
            
            return array($ImageSize[0], $ImageSize[1]);
        }
        
        return array(0, 0);
    }
    
    public function PreloadDiscussionMedia($DiscussionID, $CommentIDList) {
        $this->FireEvent('BeforePreloadDiscussionMedia');
        
        $StartT = microtime(true);
        $Data = $this->SQL
            ->Select('m.*')
            ->From('Media m')
            ->BeginWhereGroup()
                ->Where('m.ForeignID', $DiscussionID)
                ->Where('m.ForeignTable', 'discussion')
            ->EndWhereGroup()
            ->OrOp()
            ->BeginWhereGroup()
                ->WhereIn('m.ForeignID', $CommentIDList)
                ->Where('m.ForeignTable', 'comment')
            ->EndWhereGroup()
            ->Get();

        // Assign image heights/widths where necessary.
        $Data2 = $Data->Result();
        foreach ($Data2 as &$Row) {
            if ($Row->ImageHeight === NULL || $Row->ImageWidth === NULL) {
                list($Row->ImageWidth, $Row->ImageHeight) = self::GetImageSize(MediaModel::PathUploads().'/'.ltrim($Row->Path, '/'));
                $this->SQL->Put('Media', array('ImageWidth' => $Row->ImageWidth, 'ImageHeight' => $Row->ImageHeight), array('MediaID' => $Row->MediaID));
            }
        }
/*
        $DiscussionData = $this->SQL
            ->Select('m.*')
            ->From('Media m')
            ->Where('m.ForeignID', $DiscussionID)
            ->Where('m.ForeignTable', 'discussion')
            ->Get()->Result(DATASET_TYPE_ARRAY);

        $CommentData = $this->SQL
            ->Select('m.*')
            ->From('Media m')
            ->WhereIn('m.ForeignID', $CommentIDList)
            ->Where('m.ForeignTable', 'comment')
            ->Get()->Result(DATASET_TYPE_ARRAY);
        
        $Data = array_merge($DiscussionData, $CommentData);
*/

		return $Data;
    }

    /**
     * TODO: Refactor into different method to maintain compatibility with base class.
     *
     * @param array $Media The media row.
     * @param array|bool $Options Either a boolean that says whether or not to delete the file or an array with a
     * **Delete** key.
     */
    public function Delete($Media = [], $Options = []) {
        if (is_bool($Options)) {
            $DeleteFile = $Options;
        } else {
            $DeleteFile = val('Delete', $Options, true);
        }

        $MediaID = FALSE;
        if (is_a($Media, 'stdClass'))
            $Media = (array)$Media;
                
        if (is_numeric($Media)) 
            $MediaID = $Media;
        elseif (array_key_exists('MediaID', $Media))
            $MediaID = $Media['MediaID'];
        
        if ($MediaID) {
            $Media = $this->GetID($MediaID);
            $this->SQL->Delete($this->Name, array('MediaID' => $MediaID), FALSE);
            
            if ($DeleteFile) {
                $DirectPath = MediaModel::PathUploads().DS.GetValue('Path',$Media);
                if (file_exists($DirectPath))
                    @unlink($DirectPath);
            }
        } else {
            $this->SQL->Delete($this->Name, $Media, FALSE);
        }
    }
    
    public function DeleteParent($ParentTable, $ParentID) {
        $MediaItems = $this->SQL->Select('*')
            ->From($this->Name)
            ->Where('ForeignTable', strtolower($ParentTable))
            ->Where('ForeignID', $ParentID)
            ->Get()->Result(DATASET_TYPE_ARRAY);
            
        foreach ($MediaItems as $Media) {
            $this->Delete(GetValue('MediaID',$Media));
        }
    }
    
    /**
     * Return path to upload folder.
     *
     * @return string Path to upload folder.
     */
    public static function PathUploads() {
        if (defined('PATH_LOCAL_UPLOADS'))
            return PATH_LOCAL_UPLOADS;
        else
            return PATH_UPLOADS;
    }

    public static function ThumbnailHeight() {
        static $Height = FALSE;

        if ($Height === FALSE)
            $Height = C('Plugins.FileUpload.ThumbnailHeight', 128);
        return $Height;
    }

    public static function ThumbnailWidth() {
        static $Width = FALSE;

        if ($Width === FALSE)
            $Width = C('Plugins.FileUpload.ThumbnailWidth', 256);
        return $Width;
    }

    public static function ThumbnailUrl(&$Media) {
        if (GetValue('ThumbPath', $Media))
            return Gdn_Upload::Url(ltrim(GetValue('ThumbPath', $Media), '/'));
        
        $Width = GetValue('ImageWidth', $Media);
        $Height = GetValue('ImageHeight', $Media);

        if (!$Width || !$Height) {
            if ($Height = self::ThumbnailHeight())
                SetValue('ThumbHeight', $Media, $Height);
            return '/plugins/FileUpload/images/file.png';
        }

        $RequiresThumbnail = FALSE;
        if (self::ThumbnailHeight() && $Height > self::ThumbnailHeight())
            $RequiresThumbnail = TRUE;
        elseif (self::ThumbnailWidth() && $Width > self::ThumbnailWidth())
            $RequiresThumbnail = TRUE;

        $Path = ltrim(GetValue('Path', $Media), '/');
        if ($RequiresThumbnail) {
            $Result = Url('/utility/thumbnail/'.GetValue('MediaID', $Media, 'x').'/'.$Path, TRUE);
        } else {
            $Result = Gdn_Upload::Url($Path);
        }
        return $Result;
    }

    public static function Url($Media) {
        static $UseDownloadUrl = NULL;
        if ($UseDownloadUrl === NULL)
            $UseDownloadUrl = C('Plugins.FileUpload.UseDownloadUrl');

//        decho($Media);
        
        if (is_string($Media)) {
            $SubPath = $Media;
            if (method_exists('Gdn_Upload', 'Url'))
                $Url = Gdn_Upload::Url("$SubPath");
            else
                $Url = "/uploads/$SubPath";
        } elseif ($UseDownloadUrl) {
            $Url = '/discussion/download/'.GetValue('MediaID', $Media).'/'.rawurlencode(GetValue('Name', $Media));
        } else {
            $SubPath = ltrim(GetValue('Path', $Media), '/');
            if (method_exists('Gdn_Upload', 'Url'))
                $Url = Gdn_Upload::Url("$SubPath");
            else
                $Url = "/uploads/$SubPath";
        }

        return $Url;
    }
    
}