<?php
error_reporting(E_ALL); //E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR);
ini_set('display_errors', 'on');
ini_set('track_errors', 1);

header('Content-Type: text/plain; charset=utf8');

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
    'fi-FL' => array('tx' => 'fi_FL', 'name' => 'Finnish'),
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
   
   
   if ($pull) {
      // Pull files from the transifex folder and copy the locales here.
      chdir('/www/tx/vanilla');
      $r = shell_exec("tx pull -f --mode=translator");
      echo $r;
   }
   
   // Copy all of the locales.
   foreach ($locales as $code => $info) {
      echo "Copying $code...";
      copyLocale($code);
      echo "done.\n";
   }
}

function copyLocale($code) {
   global $locales;
   
   $info = $locales[$code];
   
   $tx = $info['tx'];
   
   $slug = 'vf_'.str_replace('-', '_', $code);
   $folder = "/www/Addons/locales/$slug";
   
   // Make sure the folder for the locale exists.
   if (!file_exists($folder))
      mkdir($folder, 0777, TRUE);
   
   // Create the info array.
   $infoArray = array(
       'Locale' => $code,
       'Name' => $info['name'].' Transifex',
       'Description' => "{$info['name']} language translations for Vanilla. Help contribute to this translation by going to its translation site <a href=\"https://www.transifex.com/projects/p/vanilla/language/$tx/\">here</a>.",
       'Version' => '1.0',
       'Author' => 'Vanilla Community',
       'AuthorUrl' => "https://www.transifex.com/projects/p/vanilla/language/$tx/"
   );
       
   $infoString = "<?php\n\n \$LocaleInfo['{$slug}'] = ".var_export($infoArray, TRUE).";\n";
   file_put_contents("$folder/definitions.php", $infoString);
   
   // Copy the transifex definitions.
   $resources = array('site_core', 'dash_core');
   $txFolder = ($tx == 'en_CA' ? 'source' : 'translations');
   foreach ($resources as $resource) {
      $source = "/www/tx/vanilla/$txFolder/vanilla.$resource/$tx.php";
      $dest = "$folder/$resource.php";
      
      if (file_exists($source)) {
         formatDefs($source, $dest);
      }
   }
}

function formatDefs($source, $dest) {
   $Definition = array();
   require $source;
   
   // Clear out all of the blank definitions.
   $Definition = array_filter($Definition, 'notEmpty');
   
   $fp = fopen($dest, 'wb');
   
   fwrite($fp, "<?php\n");
   
   $last = '';
   
   foreach ($Definition as $Key => $Value) {
      $curr = substr($Key, 0, 1);
      
      if ($curr !== $last)
         fwrite($fp, "\n");
      
      fwrite($fp, '$Definition['.var_export($Key, TRUE).'] = '.var_export($Value, TRUE).";\n");
      
      $last = $curr;
   }
   fclose($fp);
}

function getValue($key, $array, $default = null) {
   return isset($array[$key]) ? $array[$key] : $default;
}

function notEmpty($str) {
   return !empty($str);
}

main($argv, $argc);