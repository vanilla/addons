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
    public function fetID($MediaID, $DatasetType = false, $Options = []) {
        $this->fireEvent('BeforeGetID');
        $Data = $this->SQL
            ->select('m.*')
            ->from('Media m')
            ->where('m.MediaID', $MediaID)
            ->get()
            ->firstRow();
		
		return $Data;
    }
    
    /**
     * Retrieve all media for a foreign record.
     * 
     * @param int $ForeignID
     * @param string $ForeignTable Lowercase.
     * @return object SQL results.
     */
    public function reassign($ForeignID, $ForeignTable, $NewForeignID, $NewForeignTable) {
        $this->fireEvent('BeforeReassign');
        $Data = $this->SQL
            ->update('Media')
            ->set('ForeignID', $NewForeignID)
            ->set('ForeignTable', $NewForeignTable)
            ->where('ForeignID', $ForeignID)
            ->where('ForeignTable', $ForeignTable)
            ->put();
		
		return $Data;
    }
    
    /**
     * If passed path leads to an image, return size
     *
     * @param string $Path Path to file.
     * @return array [0] => Height, [1] => Width.
     */
    public static function getImageSize($Path) {
        // Static FireEvent for intercepting non-local files.
        $Sender = new stdClass();
        $Sender->Returns = array();
        $Sender->EventArguments = array();
        $Sender->EventArguments['Path'] =& $Path;
        $Sender->EventArguments['Parsed'] = Gdn_Upload::parse($Path);
        Gdn::pluginManager()->callEventHandlers($Sender, 'Gdn_Upload', 'CopyLocal');
    
        if (!in_array(strtolower(pathinfo($Path, PATHINFO_EXTENSION)), array('gif', 'jpg', 'jpeg', 'png'))) {
            return array(0, 0);
        }

        $ImageSize = @getimagesize($Path);
        if (is_array($ImageSize)) {
            if (!in_array($ImageSize[2], array(IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG))) {
                return array(0, 0);
            }
            
            return array($ImageSize[0], $ImageSize[1]);
        }
        
        return array(0, 0);
    }

    /**
     *
     *
     * @param $DiscussionID
     * @param $CommentIDList
     * @return Gdn_DataSet
     * @throws Exception
     */
    public function preloadDiscussionMedia($DiscussionID, $CommentIDList) {
        $this->fireEvent('BeforePreloadDiscussionMedia');
        
        $Data = $this->SQL
            ->select('m.*')
            ->from('Media m')
            ->beginWhereGroup()
                ->where('m.ForeignID', $DiscussionID)
                ->where('m.ForeignTable', 'discussion')
            ->endWhereGroup()
            ->orOp()
            ->beginWhereGroup()
                ->whereIn('m.ForeignID', $CommentIDList)
                ->where('m.ForeignTable', 'comment')
            ->endWhereGroup()
            ->get();

        // Assign image heights/widths where necessary.
        $Data2 = $Data->result();
        foreach ($Data2 as &$Row) {
            if ($Row->ImageHeight === null || $Row->ImageWidth === null) {
                list($Row->ImageWidth, $Row->ImageHeight) = self::getImageSize(MediaModel::pathUploads().'/'.ltrim($Row->Path, '/'));
                $this->SQL->put('Media', array('ImageWidth' => $Row->ImageWidth, 'ImageHeight' => $Row->ImageHeight), array('MediaID' => $Row->MediaID));
            }
        }

		return $Data;
    }

    /**
     * TODO: Refactor into different method to maintain compatibility with base class.
     *
     * @param array $Media The media row.
     * @param array|bool $Options Either a boolean that says whether or not to delete the file or an array with a
     * **Delete** key.
     */
    public function delete($Media = [], $Options = []) {
        if (is_bool($Options)) {
            $DeleteFile = $Options;
        } else {
            $DeleteFile = val('Delete', $Options, true);
        }

        $MediaID = false;
        if (is_a($Media, 'stdClass')) {
            $Media = (array)$Media;
        }
                
        if (is_numeric($Media)) {
            $MediaID = $Media;
        }
        elseif (array_key_exists('MediaID', $Media)) {
            $MediaID = $Media['MediaID'];
        }
        
        if ($MediaID) {
            $Media = $this->GetID($MediaID);
            $this->SQL->Delete($this->Name, array('MediaID' => $MediaID), false);
            
            if ($DeleteFile) {
                $DirectPath = MediaModel::pathUploads().DS.val('Path',$Media);
                if (file_exists($DirectPath)) {
                    @unlink($DirectPath);
                }
            }
        } else {
            $this->SQL->delete($this->Name, $Media, false);
        }
    }

    /**
     *
     *
     * @param $ParentTable
     * @param $ParentID
     */
    public function deleteParent($ParentTable, $ParentID) {
        $MediaItems = $this->SQL
            ->select('*')
            ->from($this->Name)
            ->where('ForeignTable', strtolower($ParentTable))
            ->where('ForeignID', $ParentID)
            ->get()->result(DATASET_TYPE_ARRAY);
            
        foreach ($MediaItems as $Media) {
            $this->delete(val('MediaID',$Media));
        }
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
     *
     *
     * @return bool|mixed
     */
    public static function thumbnailHeight() {
        static $Height = false;

        if ($Height === false) {
            $Height = c('Plugins.FileUpload.ThumbnailHeight', 128);
        }

        return $Height;
    }

    /**
     *
     *
     * @return bool|mixed
     */
    public static function thumbnailWidth() {
        static $Width = false;

        if ($Width === false) {
            $Width = c('Plugins.FileUpload.ThumbnailWidth', 256);
        }

        return $Width;
    }

    /**
     *
     *
     * @param $Media
     * @return mixed|string
     */
    public static function thumbnailUrl(&$Media) {
        if (val('ThumbPath', $Media))
            return Gdn_Upload::url(ltrim(val('ThumbPath', $Media), '/'));
        
        $Width = val('ImageWidth', $Media);
        $Height = val('ImageHeight', $Media);

        if (!$Width || !$Height) {
            if ($Height = self::thumbnailHeight())
                setValue('ThumbHeight', $Media, $Height);
            return '/plugins/FileUpload/images/file.png';
        }

        $RequiresThumbnail = false;
        if (self::thumbnailHeight() && $Height > self::thumbnailHeight())
            $RequiresThumbnail = true;
        elseif (self::thumbnailWidth() && $Width > self::thumbnailWidth())
            $RequiresThumbnail = true;

        $Path = ltrim(val('Path', $Media), '/');
        if ($RequiresThumbnail) {
            $Result = url('/utility/thumbnail/'.val('MediaID', $Media, 'x').'/'.$Path, true);
        } else {
            $Result = Gdn_Upload::url($Path);
        }

        return $Result;
    }

    /**
     *
     *
     * @param $Media
     * @return mixed|string
     */
    public static function url($Media) {
        static $UseDownloadUrl = null;

        if ($UseDownloadUrl === null) {
            $UseDownloadUrl = c('Plugins.FileUpload.UseDownloadUrl');
        }

        if (is_string($Media)) {
            $SubPath = $Media;
            if (method_exists('Gdn_Upload', 'Url')) {
                $Url = Gdn_Upload::url("$SubPath");
            } else {
                $Url = "/uploads/$SubPath";
            }
        } elseif ($UseDownloadUrl) {
            $Url = '/discussion/download/'.val('MediaID', $Media).'/'.rawurlencode(val('Name', $Media));
        } else {
            $SubPath = ltrim(val('Path', $Media), '/');
            if (method_exists('Gdn_Upload', 'Url')) {
                $Url = Gdn_Upload::url("$SubPath");
            } else {
                $Url = "/uploads/$SubPath";
            }
        }

        return $Url;
    }
}