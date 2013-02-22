#!/opt/local/bin/php
<?php
error_reporting(E_ALL); //E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR);
ini_set('display_errors', 'on');
ini_set('track_errors', 1);

header('Content-Type: text/plain; charset=utf8');

mysql_connect('127.0.0.1', 'root');
mysql_select_db('_t1');
mysql_query('set names utf8');

$locales = array(
    'en-CA' => array('tx' => 'en_CA', 'name' => 'English'),
    
    'ar-AR' => array('tx' => 'ar', 'name' => 'Arabic'),
    'bg-BG' => array('tx' => 'bg_BG', 'name' => 'Bulgarian'),
//    'bs-BA' => array('tx' => 'bs', 'name' => 'Bosnian'),
    'da-DK' => array('tx' => 'da', 'name' => 'Danish'),
    'de-DE' => array('tx' => 'de_DE', 'name' => 'German'),
    'el-GR' => array('tx' => 'el_GR', 'name' => 'Greek'),
    'es-ES' => array('tx' => 'es_ES', 'name' => 'Spanish'),
    'fa-IR' => array('tx' => 'fa_IR', 'name' => 'Persian (Iran)'),
    'fi-FL' => array('tx' => 'fi', 'name' => 'Finnish'),
    'fr-FR' => array('tx' => 'fr_FR', 'name' => 'French'),
    'he_IL' => array('tx' => 'he_IL', 'name' => 'Hebrew'),
    'hu-HU' => array('tx' => 'hu', 'name' => 'Hungarian'),
    'it-IT' => array('tx' => 'it', 'name' => 'Italian'),
    'ja-JP' => array('tx' => 'ja', 'name' => 'Japanese'),
    'ko-KR' => array('tx' => 'ko_KR', 'name' => 'Korean'),
    'nl-NL' => array('tx' => 'nl', 'name' => 'Dutch'),
    'pt-BR' => array('tx' => 'pt_BR', 'name' => 'Portuguese (Brazil)'),
    'ro-RO' => array('tx' => 'ro_RO', 'name' => 'Romanian'),
    'ru-RU' => array('tx' => 'ru_RU', 'name' => 'Russian'),
    'sv-SE' => array('tx' => 'sv_SE', 'name' => 'Swedish'),
    'th_TH' => array('tx' => 'th_TH', 'name' => 'Thai'),
    'vi_VN' => array('tx' => 'vi_VN', 'name' => 'Vietnamese'),
    'zh-CN' => array('tx' => 'zh_CN', 'name' => 'Chinese (China)'),
    'zh-TW' => array('tx' => 'zh_TW', 'name' => 'Chinese (Taiwan)')
    );

function main($argv, $argc) {
   global $locales;
   
   $pull = in_array('nopull', $argv) ? false : true;
   $copy = in_array('nocopy', $argv) ? false : true;
   $save = in_array('nosave', $argv) ? false : true;
   
   if ($pull) {
      // Pull files from the transifex folder and copy the locales here.
      chdir('/www/tx/vanilla');
      $r = shell_exec("tx pull -f");
      echo $r;
   }
   
   if ($copy) {
      // Copy all of the locales.
      foreach ($locales as $code => $info) {
         echo "Copying $code...";
         copyLocaleToAddons($code);
         echo "done.\n";
      }
   }
   
   if ($save) {
      // Save the translations to the database.
      saveLocales();
   }
   
   // Re-generate the definitions from the database.
   generateFilesFromDb();
   
   // Copy the locales back to transifex.
   if (in_array('push', $argv)) {
      foreach ($locales as $code => $info) {
         echo "Copying $code back to transifex folder...";
         copyLocaleToTx($code);
         echo "done.\n";
      }
   }
      
   // Push the files the addons site.
}

function copyLocaleToAddons($code) {
   global $locales;
   
   $info = $locales[$code];
   
   $tx = $info['tx'];
   
   $slug = 'vf_'.str_replace('-', '_', $code);
   $folder = "/www/Addons/locales/$slug";
   
   // Make sure the folder for the locale exists.
   if (!file_exists($folder))
      mkdir($folder, 0777, TRUE);
   
   $version = gmdate('Y.m.d');
   
   // Create the info array.
   $infoArray = array(
       'Locale' => $code,
       'Name' => $info['name'].' Transifex',
       'Description' => "{$info['name']} language translations for Vanilla. Help contribute to this translation by going to its translation site <a href=\"https://www.transifex.com/projects/p/vanilla/language/$tx/\">here</a>.",
       'Version' => $version,
       'Author' => 'Vanilla Community',
       'AuthorUrl' => "https://www.transifex.com/projects/p/vanilla/language/$tx/"
   );
       
   $infoString = "<?php\n\n \$LocaleInfo['{$slug}'] = ".var_export($infoArray, TRUE).";\n";
   file_put_contents("$folder/definitions.php", $infoString);
   
   // Copy the transifex definitions.
   $resources = array('site_core', 'dash_core', 'archive_core');
   $txFolder = ($tx == 'en_CA' ? 'source' : 'translations');
   foreach ($resources as $resource) {
      $source = "/www/tx/vanilla/$txFolder/vanilla.$resource/$tx.php";
      $dest = "$folder/$resource.php";
      
      if (file_exists($source)) {
         formatDefs($source, $dest);
      }
   }
}

function copyLocaleToTx($local) {
   global $locales;
   
   $info = $locales[$local];
   $tx = $info['tx'];
   $slug = str_replace('-', '_', $local);
   
   $resources = array('site_core', 'dash_core', 'archive_core');
   $txFolder = ($tx == 'en_CA' ? 'source' : 'translations');
   
   foreach ($resources as $resource) {
      $source = dirname(__FILE__)."/vf_{$slug}/$resource.php";
      $dest = "/www/tx/vanilla/$txFolder/vanilla.$resource/{$tx}.php";
      
//      echo "source: $source\ndest: $dest\n\n";
      
      if (file_exists($source)) {
         if (!file_exists(dirname($dest)))
            mkdir(dirname($dest), 0777, true);
         
         copy($source, $dest);
      }
   }
}

function formatDefs($source, $dest) {
   $Definition = array();
   require $source;
   
   // Clear out all of the blank definitions.
   $Definition = removeBadTranslations($Definition);
   
   saveDefs($Definition, $dest);
}

function removeBadTranslations($arr) {
   $result = array();
   
   foreach ($arr as $k => $v) {
      if (!$v)
         continue;
      if ($k == $v)
         continue;
      if (strpos($v, '???') !== false)
         continue;
      $result[$k] = $v;
   }
   return $result;
}

function saveDefs(&$defs, $path) {
   uksort($defs, 'strcasecmp');
   
   $fp = fopen($path, 'wb');
   
   fwrite($fp, "<?php\n");
   
   $last = '';
   
   foreach ($defs as $Key => $Value) {
      $curr = substr($Key, 0, 1);
      
      if ($curr !== $last)
         fwrite($fp, "\n");
      
      fwrite($fp, '$Definition['.var_export($Key, TRUE).'] = '.var_export($Value, TRUE).";\n");
      
      $last = $curr;
   }
   fclose($fp);
}

function generateFilesFromDb() {
   global $locales;
   
   $files = array('site', 'dash', 'archive');
   
   foreach ($locales as $code => $info) {
      $slug = str_replace('-', '_', $code);
      $badDefs = array();
      
      echo "Generating $code from db...";
      
      foreach ($files as $file) {
         echo $file.' ';
         $path = dirname(__FILE__)."/vf_{$slug}/{$file}_core.php";
         
         generateFileFromDb($code, $path, $file, $badDefs);
      }
      
      // Save the bad defs to a file too.
      if (!empty($badDefs)) {
         $path = dirname(__FILE__)."/vf_{$slug}/bad_defs.php";
         saveDefs($badDefs, $path);
      }
      
      echo "done.\n";
   }
}

function loadTranslationsFromDb($locale, $type, &$badDefs = null) {
   if (!is_array($badDefs))
      $badDefs = array();
   
   // Keep a copy of the en-CA translation for comparison.
   static $enDefs = array();
   if (!isset($enDefs[$type]) && $locale != 'en-CA') {
      $enDefs[$type] = loadTranslationsFromDb('en-CA', $type);
   }
   
   switch ($type) {
      case 'dash':
         $Dashboard = 1;
         $Active = 1;
         break;
      case 'site':
         $Dashboard = 0;
         $Active = 1;
         break;
      case 'archive':
         $Dashboard = null;
         $Active = 2;
         break;
   }
   
   // Load the stuff from the db.
   $sql = "select
      c.Name,
      t.Translation
   from GDN_LocaleCode c
   join GDN_LocaleTranslation t
      on c.CodeID = t.CodeID and t.Locale = :Locale
   where c.Active = :Active";
   
   if ($Dashboard !== null)
      $sql .= ' and c.Dashboard = :Dashboard';
   
   $r = query($sql, array('Locale' => $locale, 'Dashboard' => $Dashboard, 'Active' => $Active));
   $defs = array();
   while ($row = mysql_fetch_assoc($r)) {
      if (!$row['Name'])
         continue;
      
      $name = $row['Name'];
      
      $translation = $row['Translation'];
      if ($locale == 'en-CA') {
         if (!$translation)
            $translation = $name;
      } else {
         if (!$translation)
            continue;
         if ($translation == $name)
            continue;
         
         if (isset($enDefs[$type][$name]) && $translation == $enDefs[$type][$name]) {
            $badDefs[$name] = $translation;
         }
         
         if (strpos($translation, '???') !== false)
            continue;
      }
      
      $defs[$name] = $row['Translation'];
   }
   
   if ($locale == 'en-CA' && !isset($enDefs[$type])) {
      $enDefs[$type] = $defs;
   }
   
   return $defs;
}

function generateFileFromDb($locale, $path, $type, &$badDefs) {
   $defs = loadTranslationsFromDb($locale, $type, $badDefs);
   
   // Save the definitions to the file.
   saveDefs($defs, $path);
}

function getValue($key, $array, $default = null) {
   return isset($array[$key]) ? $array[$key] : $default;
}

function notEmpty($str) {
   return !empty($str);
}

function query($sql, $params = array()) {
   foreach ($params as $key => $value) {
      $sql = str_replace(':'.$key, "'".mysql_real_escape_string($value)."'", $sql);
   }
   
//   echo "$sql\n";
   
   $r = mysql_query($sql);
   if (!$r) {
      trigger_error("Error in:\n$sql\n\n".mysql_error(), E_USER_ERROR);
      return $r;
   }
   
   if (preg_match('`^\s*insert\s`', $sql))
      $r = mysql_insert_id();
   
   return $r;
}

function dbDate($timestamp = null) {
   if (!$timestamp)
      $timestamp = time();
   
   return gmdate('Y-m-d G:i:s', $timestamp);
}

function saveLocales() {
   global $locales;
   
   // Copy all of the locales.
   foreach ($locales as $code => $info) {
      if ($code == 'en-CA')
         continue;
      
      echo "Saving $code...";
      
      $result = saveLocaleToDb($code);
      
      echo "inserted: {$result['inserted']}, updated: {$result['updated']}, equal: {$result['equal']}, skipped: {$result['skipped']}.\n";
   }
}

function saveLocaleToDb($locale) {
   $result = array('inserted' => 0, 'updated' => 0, 'equal' => 0, 'skipped' => 0);
   $filenames = array('dash_core.php', 'site_core.php');
   
   // First load all of the strings from the db.
   $sql = "select
      c.CodeID, 
      c.Name,
      t.TranslationID,
      t.Locale,
      t.Translation
   from GDN_LocaleCode c
   left join GDN_LocaleTranslation t
      on c.CodeID = t.CodeID and t.Locale = :Locale";
   
   $strings = array();
   $r = query($sql, array('Locale' => $locale));
   while ($row = mysql_fetch_assoc($r)) {
      $strings[$row['Name']] = $row;
   }
   
   // Load the strings from the files.
   $slug = str_replace('-', '_', $locale);
   foreach ($filenames as $filename) {
      $path = dirname(__FILE__)."/vf_{$slug}/$filename";
      if (!file_exists($path)) {
         echo "Path $path does not exist.\n";
         continue;
      }
      
      
      $Definition = array();
      include $path;
      
      foreach ($Definition as $code => $string) {
         // Make sure the code is even in the db.
         if (!isset($strings[$code])) {
            $result['skipped']++;
            continue;
         }
         
         $row =& $strings[$code];
         
         // Check to see if the string has changed.
         if ($row['Translation'] == $string) {
            $result['equal']++;
            continue;
         }
         
         $now = dbDate();
         
         if ($row['TranslationID']) {
            $r = query("update GDN_LocaleTranslation
               set Translation = :Translation,
                  DateUpdated = :DateUpdated
               where TranslationID = :TranslationID",
               array('TranslationID' => $row['TranslationID'], 'Translation' => $string, 'DateUpdated' => $now));
            if ($r)
               $result['updated']++;
         } else {
            $translationID = query('insert GDN_LocaleTranslation (
                  CodeID,
                  Locale,
                  Translation,
                  DateInserted,
                  InsertUserID)
               values (
                  :CodeID,
                  :Locale,
                  :Translation,
                  :DateInserted,
                  :InsertUserID)',
               array (
                   'CodeID' => $row['CodeID'],
                   'Locale' => $locale,
                   'Translation' => $string,
                   'DateInserted' => $now,
                   'InsertUserID' => 1));
            if ($r) {
               $result['inserted']++;
               $row['TranslationID'] = $r;
               $row['Translation'] = $string;
            }
         }
         
         $row['Translation'] = $string;
      }
   }
   
   return $result;
}

main($argv, $argc);