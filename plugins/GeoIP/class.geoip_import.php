<?php

class GeoipImport {


    //private static $csvDownloadURL = "http://geolite.maxmind.com/download/geoip/database/GeoLite2-City-CSV.zip";
    private static $csvDownloadURL = "http://deric.ca/geoip/GeoLite2-City-CSV.zip";

    private static $blockFileName    = 'GeoLite2-City-Blocks-IPv4.csv';
    private static $locationFileName = 'GeoLite2-City-Locations-%s.csv';
    private static $locationLocales  = ['de','en','es','fr','ja','pt-BR','ru','zh-CN'];

    const tuncateQuery  = "TRUNCATE `%s`";
    const describeQuery = "DESCRIBE `%s`";
    const dropTableQuery = "DROP TABLE %s IF EXISTS";

    private static $blockRowsPerQuery    = 2000;
    private static $locationRowsPerQuery = 500;

    private $blockImportTime      = 0;
    private $locationImportTime   = 0;

    private static $blockTableName       = 'geoip_block';
    private static $locationTableName    = 'geoip_location';

    public $downloadZip;
    public $locationFile;
    public $blockFile;

    public  $baseDir = '/tmp/geoip';
    //private $userDir; // Defined in __get().

    public function run() {

        // Clean previous working files:
        $this->trash();

        // Download Files:
        $downloaded = $this->importDownload();
        if ($downloaded==false) {
            error_log("Failed to download and extract GeoIP files!");
            return false;
        }

        // Import Data:
        $imported   = $this->importData();
        if ($imported==false) {
            error_log("Failed to Import data into MySQL!");
            return false;
        }

        // Clean up after ourselves:
        $this->trash();

        return true;
    }


    private function importDownload() {

        // Download Zip:
        $this->downloadZip  = $this->downloadGeoipZip(self::$csvDownloadURL);
        if (!is_file($this->downloadZip)) {
            error_log("Failed to download GeoIP CSV file in ".__METHOD__."()");
            return false;
        }
        error_log("Zip Downloaded: {$this->downloadZip}");

        // Extract downloaded payload file to get to the CSV files:
        $payloadFiles = $this->extractGeoipCSV($this->downloadZip);
        if (empty($payloadFiles) OR !is_array($payloadFiles)) {
            error_log("Failed to extract GeoIP CSV file in ".__METHOD__."()");
            return false;
        }
        $this->locationFile = !empty($payloadFiles['location_file']) ? $payloadFiles['location_file'] : false;
        $this->blockFile    = !empty($payloadFiles['block_file'])    ? $payloadFiles['block_file']    : false;
        //error_log("Zip Extracted");

        // Check File Paths:
        if (!is_file($this->locationFile)) {
            error_log("Failed to locate GeoIP CSV location file in ".__METHOD__."()");
            return false;
        }
        if (!is_file($this->blockFile)) {
            error_log("Failed to locate GeoIP CSV block file in ".__METHOD__."()");
            return false;
        }
        //error_log("Zip Content Confirmed ({$this->blockFile}, {$this->locationFile})");
        error_log("Zip Content Confirmed.");

        return true;
    }

    private function importData() {

        if (!is_file($this->locationFile)) {
            error_log("Failed to locate GeoIP CSV location file in ".__METHOD__."()");
            return false;
        }
        if (!is_file($this->blockFile)) {
            error_log("Failed to locate GeoIP CSV block file in ".__METHOD__."()");
            return false;
        }

        // Create Location Table:
        $locationCreated   = $this->createLocationTable();
        if (empty($locationCreated)) {
            error_log("Failed to create GeoIP location table in ".__METHOD__."()");
            return false;
        }
        error_log("Location Table Created");

        // Create Block Table:
        $blockCreated      = $this->createBlockTable();
        if (empty($blockCreated)) {
            error_log("Failed to create GeoIP block table in ".__METHOD__."()");
            return false;
        }
        error_log("Block Table Created");


        // Import Location CSV file into SQL:
        $locStart = microtime(true);
        $locationImported  = $this->importLocationCSV($this->locationFile);
        if (empty($locationImported)) {
            error_log("Failed to import GeoIP CSV location file into SQL table in ".__METHOD__."()");
            return false;
        }
        $this->locationImportTime = microtime(true) - $locStart;
        error_log("Location Table Imported in {$this->locationImportTime} seconds.");


        // Import Block CSV file into SQL:
        $blockStart = microtime(true);
        $blockImported  = $this->importBlockCSV($this->blockFile);
        if (empty($blockImported)) {
            error_log("Failed to import GeoIP CSV block file into SQL table in ".__METHOD__."()");
            return false;
        }
        $this->blockImportTime = microtime(true) - $blockStart;
        error_log("Block Table Imported in {$this->blockImportTime} seconds.");

        return true;
    }


    /**
     * Downloads target GeoIP zip payload file that contains CSV files
     * that will be loaded into DB.
     *
     * @param null $url Optional URL parameter of target payload.
     * @return string
     */
    private function downloadGeoipZip($url=null) {

        // Remove time limit for php execution:
        set_time_limit(0);

        $url    = !empty($url) ? $url : self::$csvDownloadURL;
        $name   = substr($url, strrpos($url,'/')+1);

        // Create TMP User Dir:
        //$this->userDir = "{$this->baseDir}_".Gdn::session()->UserID;
        if (!is_dir($this->userDir)) {
            mkdir($this->userDir, 0775, true);
        }

        // Output destination file:
        $output = "{$this->userDir}/{$name}";

        $fp = fopen ($output, 'w+');//This is the file where we save the    information
        $ch = curl_init($url);//Here is the file we are downloading, replace spaces with %20
        //curl_setopt($ch, CURLOPT_TIMEOUT, 50);
        curl_setopt($ch, CURLOPT_FILE, $fp); // write curl response to file
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($ch); // get curl response
        curl_close($ch);

        return $output;
    }

    private function extractGeoipCSV($input, $locale='en') {
        if (!is_file($input)) {
            error_log("Invalid Zip file passed for extraction in ".__METHOD__."()");
            return false;
        }

        $baseDir = dirname($input);

        // UnZip Archive:
        $zip = new ZipArchive;

        if ($zip->open($input) === TRUE) {
            $zip->extractTo($baseDir);
            $zip->close();
        } else {
            return false;
        }

        // Get Extracted Directory Name:
        $geoipDir = self::locateSubDir($baseDir);
        error_log("GeoIP Directory: {$baseDir}/{$geoipDir}");

        $output = [];
        $output['block_file']    = "{$baseDir}/{$geoipDir}/".self::$blockFileName;
        $output['location_file'] = "{$baseDir}/{$geoipDir}/".sprintf(self::$locationFileName, $locale);

        // Check extracted payload files are properly linked:
        if (!is_file($output['block_file'])) {
            error_log("Could not locate extract GeoIP Block file in ".__METHOD__."()!");
        }
        if (!is_file($output['location_file'])) {
            error_log("Could not locate extract GeoIP Location file in ".__METHOD__."()!");
        }

        // Delete original Zip file.
        $deleted = unlink($input);
        if ($deleted==false) {
            error_log("ERROR: Failed to delete original downloaded ZIP file.");
        }

        return $output;
    }

    /**
     * Creates MySQL geoip location table.
     *
     * @return bool|Gdn_DataSet|object|string
     */
    private function createLocationTable() {

        error_log("Creating Location Table");
        if ($this->tableExists(self::$locationTableName)==false) {

            try {
                $sql = "CREATE TABLE ".self::$locationTableName." (\n";
                $sql .= "  `geoname_id` int(10) unsigned NOT NULL\n";
                $sql .= ", `locale_code` char(2) NOT NULL\n";
                $sql .= ", `continent_code` char(2) NOT NULL\n";
                $sql .= ", `continent_name` varchar(24) NOT NULL\n";
                $sql .= ", `country_iso_code` char(2) NOT NULL\n";
                $sql .= ", `country_name` varchar(36) NOT NULL\n";
                $sql .= ", `subdivision_1_iso_code` char(3) NOT NULL\n";
                $sql .= ", `subdivision_1_name` varchar(36) NOT NULL\n";
                $sql .= ", `subdivision_2_iso_code` char(3) NOT NULL\n";
                $sql .= ", `subdivision_2_name` varchar(36) NOT NULL\n";
                $sql .= ", `city_name` varchar(50) NOT NULL\n";
                $sql .= ", `metro_code` int(4) NOT NULL\n";
                $sql .= ", `time_zone` varchar(24) NOT NULL\n";
                $sql .= ", PRIMARY KEY (`geoname_id`)\n";
                $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8\n;";
                //error_log("Create Location Table:\n{$sql}");

                $output = GDN::SQL()->Query($sql);

            } catch(\Exception $e) {
                error_log("SQL Error: ".$e->getMessage());
                return false;
            }

        } else {
            error_log("-Location table exists.");
            $this->tableTruncate(self::$locationTableName);
            return true;
        }

        return $output;
    }

    /**
     * Imports GeoIP location CSV file into MySQL database.
     *
     * @param $input Filename of CSV to import.
     * @return bool
     */
    private function importLocationCSV($input) {
        if (empty($input) OR !is_file($input)) {
            trigger_error("Invalid path to block CSV in ".__METHOD__."()!", E_USER_WARNING);
            return false;
        }

        $fh   = fopen($input,'r');
        $rem  = null;
        $i    = 0;

        while (!feof($fh)) {

            $sql  = "INSERT into ".self::$locationTableName."\n";
            $sql .= "(geoname_id, locale_code, continent_code, continent_name, country_iso_code, country_name, subdivision_1_iso_code, subdivision_1_name, subdivision_2_iso_code, subdivision_2_name, city_name, metro_code, time_zone)\n";
            $sql .= "VALUES\n";

            $j = 0;
            while ($j < self::$locationRowsPerQuery && !feof($fh)) {

                $cells = fgetcsv($fh, 1024, ",", '"');
                if (empty($cells[0])) {
                    continue;
                }

                $sql .= $j==0 ? "  " : ", ";
                // $sql .= "('{$cells[0]}','{$cells[1]}','{$cells[2]}','".str_replace("'","\\'",$cells[3])."','".str_replace("'","\\'",$cells[4])."','".str_replace("'","\\'",$cells[5])."','".str_replace("'","\\'",$cells[6])."','".str_replace("'","\\'",$cells[7])."','".str_replace("'","\\'",$cells[8])."','".str_replace("'","\\'",$cells[9])."','".str_replace("'","\\'",$cells[10])."','".str_replace("'","\\'",$cells[11])."','".str_replace("'","\\'",$cells[12])."')\n";
                $sql .= "('".str_replace("'","\\'",$cells[0])."'"
                    . ",'".str_replace("'","\\'",$cells[1])."'"
                    . ",'".str_replace("'","\\'",$cells[2])."'"
                    . ",'".str_replace("'","\\'",$cells[3])."'"
                    . ",'".str_replace("'","\\'",$cells[4])."'"
                    . ",'".str_replace("'","\\'",$cells[5])."'"
                    . ",'".str_replace("'","\\'",$cells[6])."'"
                    . ",'".str_replace("'","\\'",$cells[7])."'"
                    . ",'".str_replace("'","\\'",$cells[8])."'"
                    . ",'".str_replace("'","\\'",$cells[9])."'"
                    . ",'".str_replace("'","\\'",$cells[10])."'"
                    . ",'".str_replace("'","\\'",$cells[11])."'"
                    . ",'".str_replace("'","\\'",$cells[12])."'"
                    . ")\n";
                $j++;
            }
            $sql .= ";";
            // error_log("-{$i})\n{$sql}");

            try {
                $PDO = GDN::Database()->Connection();
                $PDO->query($sql);
            } catch(Exception $e) {
                error_log("Failed Query: ".$e->getMessage());
            }

            $i++;
        }

        fclose($fh);

        return true;
    }

    /**
     * Creates MySQL geoip block table.
     *
     * @return bool|Gdn_DataSet|object|string
     */
    private function createBlockTable() {

        error_log("Creating Block Table");
        if ($this->tableExists(self::$blockTableName)==false) {

            try{
                $sql  = "CREATE TABLE ".self::$blockTableName." (\n";
                $sql .= "  `network` varchar(20) NOT NULL\n";
                $sql .= ", `start` bigint(14) unsigned NOT NULL\n";
                $sql .= ", `end` bigint(14) unsigned NOT NULL\n";
                $sql .= ", `geoname_id` int(10) unsigned NOT NULL\n";
                $sql .= ", `registered_country_geoname_id` int(10) unsigned NOT NULL\n";
                $sql .= ", `represented_country_geoname_id` int(10) unsigned NOT NULL\n";
                $sql .= ", `is_anonymous_proxy` tinyint(1) unsigned NOT NULL\n";
                $sql .= ", `is_satellite_provider` tinyint(1) unsigned NOT NULL\n";
                $sql .= ", `postal_code` varchar(15) NOT NULL\n";
                $sql .= ", `latitude` decimal(7,4) NOT NULL\n";
                $sql .= ", `longitude` decimal(7,4) NOT NULL\n";
                $sql .= ", PRIMARY KEY (`network`)\n";
                $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8\n;";
                //error_log("Create Block Table:\n{$sql}");

                $output = GDN::SQL()->Query($sql);

            } catch(\Exception $e) {
                error_log("SQL Error: ".$e->getMessage());
                return false;
            }

        } else {
            error_log("-Block table exists.");
            //$this->tableDrop(self::$blockTableName);
            //$this->createBlockTable();
            $this->tableTruncate(self::$blockTableName);
            return true;
        }

        return $output;
    }

    /**
     * Imports GeoIP block CSV file into MySQL database.
     *
     * @param $input Filename of CSV to import.
     * @return bool
     */
    private function importBlockCSV($input) {
        if (empty($input) OR !is_file($input)) {
            trigger_error("Invalid path to block CSV in ".__METHOD__."()!", E_USER_WARNING);
            return false;
        }

        $fh   = fopen($input,'r');
        $rem  = null;
        $i    = 0;

        while (!feof($fh)) {

            $sql  = "INSERT into ".self::$blockTableName."\n";
            $sql .= "(network,start,end,geoname_id,registered_country_geoname_id,represented_country_geoname_id,is_anonymous_proxy,is_satellite_provider,postal_code,latitude,longitude)\n";
            //$sql .= "(network,geoname_id,registered_country_geoname_id,represented_country_geoname_id,is_anonymous_proxy,is_satellite_provider,postal_code,latitude,longitude)\n";
            $sql .= "VALUES\n";

            $j = 0;
            while ($j < self::$blockRowsPerQuery && !feof($fh)) {

                $cells = fgetcsv($fh, 1024, ",", '"');
                if (empty($cells[0])) {
                    continue;
                }

                $sql .= $j==0 ? "  " : ", ";
                $sql .= "('".str_replace("'","\\'",$cells[0])."'"
                    . ", inet_aton(SUBSTRING(network, 1, LOCATE('/',network) -1))"
                    . ", inet_aton(SUBSTRING(network, 1, LOCATE('/',network) -1)) + pow(2, (32 - CONVERT( SUBSTRING(network, LOCATE('/',network) +1), UNSIGNED) )) -1"
                    . ",'".str_replace("'","\\'",$cells[1])."'"
                    . ",'".str_replace("'","\\'",$cells[2])."'"
                    . ",'".str_replace("'","\\'",$cells[3])."'"
                    . ",'".str_replace("'","\\'",$cells[4])."'"
                    . ",'".str_replace("'","\\'",$cells[5])."'"
                    . ",'".str_replace("'","\\'",$cells[6])."'"
                    . ",'".str_replace("'","\\'",$cells[7])."'"
                    . ",'".str_replace("'","\\'",$cells[8])."'"
                    . ")\n";
                $j++;
            }
            $sql .= ";";
            // error_log("-{$i})\n{$sql}");

            try {
                $PDO = GDN::Database()->Connection();
                $PDO->query($sql);
            } catch(Exception $e) {
                error_log("Failed Query: ".$e->getMessage());
            }

            $i++;
        }

        fclose($fh);

        return true;
    }



    private function locateSubDir($input) {

        $cmd = "ls -1 {$input}";
        exec($cmd, $dirList);
        // error_log("Scanning dir {$input} for CSV folder.");

        foreach ($dirList AS $item) {
            // error_log("-- {$item}");
            if (is_dir("{$input}/{$item}")) {
                $output = $item;
                break;
            }
        }

        return $output;
    }

    private function tableExists($input, $maxNameLength=20) {
        if (empty($input) || !is_string($input) || !strlen($input) > $maxNameLength) {
            error_log("Invalid INPUT {$input} passed to ".__METHOD__."()");
            return false;
        }
        //error_log("Checking Table {$input} Exists!");

        //$result = GDN::SQL()->query(sprintf(self::describeQuery, $input));
        //$result = $this->runQuery();
        $PDO = GDN::Database()->Connection();
        $result = $PDO->query(sprintf(self::describeQuery, $input));

        $output = empty($result) ? false : true;
        return $output;
    }

    private function tableTruncate($input, $maxNameLength=20) {
        if (empty($input) || !is_string($input) || !strlen($input) > $maxNameLength) {
            error_log("Invalid INPUT {$input} passed to ".__METHOD__."()");
            return false;
        }
        error_log("TRUNCATing table {$input}");
        $result = GDN::SQL()->query(sprintf(self::tuncateQuery, $input));
        $output = empty($result) ? false : true;

        return $output;
    }

    private function tableDrop($input, $maxNameLength=20) {
        if (empty($input) || !is_string($input) || !strlen($input) > $maxNameLength) {
            error_log("Invalid INPUT {$input} passed to ".__METHOD__."()");
            return false;
        }

        $result = GDN::SQL()->query(sprintf(self::dropTableQuery, $input));
        $output = empty($result) ? false : true;
        error_log("Dropped table {$input}");
        return $output;
    }

    /**
     * Cleans all temporary files used during import process.
     *
     * @return bool
     */
    public  function trash() {
        //unlink($this->blockFile);
        //unlink($this->locationFile);
        //rmdir(dirname($this->blockFile));
        //rmdir(dirname($this->downloadZip));

        if (is_dir($this->userDir)) {
            $cmd = "rm -Rf {$this->userDir}";
            system($cmd);
            return true;
        } else {
            return false;
        }
    }

    function __get($varName) {
        switch ($varName) {
            case 'userDir': return $this->baseDir.'_'.GDN::session()->UserID;
            default: null;
        }
    }

}
