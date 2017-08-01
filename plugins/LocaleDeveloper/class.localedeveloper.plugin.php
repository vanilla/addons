<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Vanilla Forums Inc.
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
*/

class LocaleDeveloperPlugin extends Gdn_Plugin {
    public $LocalePath;

    /**
     * @var Gdn_Form Form
     */
    protected $Form;

    public function __construct() {
        $this->LocalePath = PATH_UPLOADS.'/LocaleDeveloper';
        $this->Form = new Gdn_Form();
        parent::__construct();
    }

    /**
     * Save the captured definitions.
     *
     * @param Gdn_Controller $Sender
     * @param array $Args
     */
    public function base_render_after($Sender, $Args) {
        $Locale = Gdn::locale();
        if (!is_a($Locale, 'DeveloperLocale'))
            return;

        $Path = $this->LocalePath.'/tmp_'.randomString(10);
        if (!file_exists(dirname($Path)))
            mkdir(dirname($Path), 0777, TRUE);
        elseif (file_exists($Path)) {
            // Load the existing definitions.
            $Locale->load($Path);
        }

        // Load the core definitions.
        if (file_exists($this->LocalePath.'/captured_site_core.php')) {
            $Definition = [];
            include $this->LocalePath.'/captured_site_core.php';
            $Core = $Definition;
        } else {
            $Core = [];
        }

        // Load the admin definitions.
        if (file_exists($this->LocalePath.'/captured_dash_core.php')) {
            $Definition = [];
            include $this->LocalePath.'/captured_dash_core.php';
            $Admin = $Definition;
        } else {
            $Admin = [];
        }

        // Load the ignore file.
        $Definition = [];
        include dirname(__FILE__).'/ignore.php';
        $Ignore = $Definition;
        $Definition = [];

        $CapturedDefinitions = $Locale->capturedDefinitions();

//      decho ($CapturedDefinitions);
//      die();

        foreach ($CapturedDefinitions as $Prefix => $Definition) {
            $FinalPath = $this->LocalePath."/captured_$Prefix.php";

            // Load the definitions that have already been captured.
            if (file_exists($FinalPath)) {
                include $FinalPath;
            }
            $Definition = array_diff_key($Definition, $Ignore);

            // Strip core definitions from the file.
            if ($Prefix != 'site_core') {
                $Definition = array_diff_key($Definition, $Core);

                if ($Prefix != 'dash_core') {
                    $Definition = array_diff_key($Definition, $Admin);
                }
            }

            // Save the current definitions.
            $fp = fopen($Path, 'wb');
            if (!is_resource($fp)) {
                Logger::error("Could not open {path}.", ['path' => $Path]);
                continue;
            }
            fwrite($fp, $this->getFileHeader());
            LocaleModel::writeDefinitions($fp, $Definition);
            fclose($fp);

            // Copy the file over the existing one.
            $Result = rename($Path, $FinalPath);
        }
    }

    public function gdn_Dispatcher_BeforeDispatch_Handler($sender) {
        if (c('Plugins.LocaleDeveloper.CaptureDefinitions')) {
            // Install the developer locale.
            $_Locale = new DeveloperLocale(Gdn::locale()->current(), c('EnabledApplications'), c('EnabledPlugins'));

            $tmp = Gdn::factoryOverwrite(TRUE);
            Gdn::factoryInstall(Gdn::AliasLocale, 'Gdn_Locale', NULL, Gdn::FactorySingleton, $_Locale);
            Gdn::factoryOverwrite($tmp);
            unset($tmp);
        }
    }

    /**
     *
     * @param Gdn_Controller $sender
     * @param array $args
     */
    public function settingsController_render_before($sender, $args) {
        if (strcasecmp($sender->RequestMethod, 'locales') != 0)
            return;

        // Add a little pointer to the settings.
        $text = '<div class="Info">'.
            sprintf(t('Locale Developer Settings %s.'), anchor(t('here'), '/settings/localedeveloper')).
            '</div>';
        $sender->addAsset('Content', $text, 'LocaleDeveloperLink');
    }

    /**
     *
     * @var SettingsController $sender
     */
    public function settingsController_localeDeveloper_create($sender, $args = []) {
        $sender->permission('Garden.Settings.Manage');

        $sender->addSideMenu();
        $sender->setData('Title', t('Locale Developer'));

        switch (strtolower(getValue(0, $args, ''))) {
            case '':
                $this->_Settings($sender, $args);
                break;
            case 'download':
                $this->_Download($sender, $args);
                break;
            case 'googletranslate':
                $this->_GoogleTranslate($sender, $args);
                break;
        }
    }

    public function _Download($sender, $args) {
        try {
            // Create the zip file.
            $path = $this->createZip();

            // Serve the zip file.
            Gdn_FileSystem::serveFile($path, basename($path), 'application/zip');
        } catch (Exception $ex) {
            $this->Form->addError($ex);
            $this->_Settings($sender, $args);
        }
    }

    public function ensureDefinitionFile() {
        $path = $this->LocalePath.'/definitions.php';
        if (file_exists($path))
            unlink($path);
        $contents = $this->getFileHeader().self::formatInfoArray('$LocaleInfo', $this->getInfoArray());
        Gdn_FileSystem::saveFile($path, $contents);
    }

    public static function formatInfoArray($variableName, $array) {
        $variableName = '$'.trim($variableName, '$');

        $result = '';
        foreach ($array as $key => $value) {
            $result .= $variableName."['".addcslashes($key, "'")."'] = ";
            $result .= var_export($value, TRUE);
            $result .= ";\n\n";
        }

        return $result;
    }

    public static function formatValue($value, $singleLine = TRUE) {
        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        } elseif (is_numeric($value)) {
            return (string)$value;
        } elseif (is_string($value)) {
            if ($singleLine)
                return var_export($value, TRUE);
            else
                return "'".addcslashes($value, "'")."'";
        } elseif (is_array($value)) {
            $result = '';
            $arraySep = $singleLine ? ', ' : ",\n   ";

            foreach ($value as $key => $arrayValue) {
                if (strlen($result) > 0)
                    $result .= $arraySep;

                if ($singleLine == 'TRUEFALSE')
                    $singleLine = FALSE;

                $result .= "'".addcslashes($key, "'")."' => ".self::formatValue($arrayValue, $singleLine);
            }

            $result = 'array('.$result.')';
            return $result;
        } else {
            $error = print_r($value);
            $error = str_replace('*/', '', $error);

            return "/* Could not format the following value:\n{$error}\n*/";
        }
    }

    public function getFileHeader() {
        $now = Gdn_Format::toDateTime();

        $result = "<?php if (!defined('APPLICATION')) exit();
/** This file was generated by the Locale Developer plugin on $now **/\n\n";

        return $result;
    }

    public function getInfoArray() {
        $info = c('Plugins.LocaleDeveloper');
        foreach ($info as $key => $value) {
            if (!$value)
                unset($info[$key]);
        }

        $infoArray = [getValue('Key', $info, 'LocaleDeveloper') => [
            'Locale' => getValue('Locale', $info, Gdn::locale()->current()),
            'Name' => getValue('Name', $info, 'Locale Developer'),
            'Description' => 'Automatically gernerated by the Locale Developer plugin.',
            'Version' => '0.1a',
            'Author' => "Your Name",
            'AuthorEmail' => 'Your Email',
            'AuthorUrl' => 'http://your.domain.com',
            'License' => 'Your choice of license'
        ]];

        return $infoArray;
    }

    public function _GoogleTranslate($sender, $args) {
        $sender->Form = $this->Form;

        if ($this->Form->isPostBack()) {
            exit('Foo');

        } else {
            // Load all of the definitions.
            //$Definitions = $this->loadDefinitions();
            //$Sender->setData('Definitions', $Definitions);
        }

        $sender->render('googletranslate', '', 'plugins/LocaleDeveloper');
    }

//   public function loadDefinitions($Path = NULL) {
//      if ($Path === NULL)
//         $Path = $this->LocalePath;
//
//      $Paths = safeGlob($Path.'/*.php');
//      $Definition = array();
//      foreach ($Paths as $Path) {
//         // Skip the locale developer's changes file.
//         if ($Path == $this->LocalePath && basename($Path) == 'changes.php')
//            continue;
//         include $Path;
//      }
//      return $Definition;
//   }

    public function _Settings($sender, $args) {
        $sender->Form = $this->Form;

        // Grab the existing locale packs.
        $localeModel = new LocaleModel();
        $localePacks = $localeModel->availableLocalePacks();
        $localArray = [];
        foreach ($localePacks as $key => $info) {
            $localeArray[$key] = getValue('Name', $info, $key);
        }
        $sender->setData('LocalePacks', $localeArray);

        if ($this->Form->isPostBack()) {
            if ($this->Form->getFormValue('Save')) {
                $values = arrayTranslate($this->Form->formValues(), ['Key', 'Name', 'Locale', 'CaptureDefinitions']);
                $saveValues = [];
                foreach ($values as $key => $value) {
                    $saveValues['Plugins.LocaleDeveloper.'.$key] = $value;
                }

                // Save the settings.
                saveToConfig($saveValues, '', ['RemoveEmpty' => TRUE]);

                $sender->StatusMessage = t('Your changes have been saved.');
            } elseif ($this->Form->getFormValue('GenerateChanges')) {
                $key = $this->Form->getFormValue('LocalePackForChanges');
                if (!$key)
                    $this->Form->addError('ValidateRequired', 'Locale Pack');
                $path = PATH_ROOT.'/locales/'.$key;
                if (!file_exists($path))
                    $this->Form->addError('Could not find the selected locale pack.');

                if ($this->Form->errorCount() == 0) {
                    try {
                        $localeModel->generateChanges($path, $this->LocalePath);
                        $sender->StatusMessage = t('Your changes have been saved.');
                    } catch (Exception $ex) {
                        $this->Form->addError($ex);
                    }
                }
            } elseif ($this->Form->getFormValue('Copy')) {
                $key = $this->Form->getFormValue('LocalePackForCopy');
                if (!$key)
                    $this->Form->addError('ValidateRequired', 'Locale Pack');
                $path = PATH_ROOT.'/locales/'.$key;
                if (!file_exists($path))
                    $this->Form->addError('Could not find the selected locale pack.');

                if ($this->Form->errorCount() == 0) {
                    try {
                        $localeModel->copyDefinitions($path, $this->LocalePath.'/copied.php');
                        $sender->StatusMessage = t('Your changes have been saved.');
                    } catch (Exception $ex) {
                        $this->Form->addError($ex);
                    }
                }
            } elseif ($this->Form->getFormValue('Remove')) {
                $files = safeGlob($this->LocalePath.'/*');
                foreach ($files as $file) {
                    $result = unlink($file);
                    if (!$result) {
                        $this->Form->addError('@'.sprintf(t('Could not remove %s.'), $file));
                    }
                }
                if ($this->Form->errorCount() == 0)
                    $sender->StatusMessage = t('Your changes have been saved.');
            }
        } else {
            $values = c('Plugins.LocaleDeveloper');
            foreach ($values as $key => $value) {
                $this->Form->setFormValue($key, $value);
            }
        }

        $sender->setData('LocalePath', $this->LocalePath);

        $sender->render('', '', 'plugins/LocaleDeveloper');
    }

    public function writeInfoArray($fp) {
        $info = c('Plugins.LocaleDeveloper');

        // Write the info array.
        $infoArray = $this->getInfoArray();

        $infoString = self::formatInfoArray('$LocaleInfo', $infoArray);
        fwrite($fp, $infoString);
    }

    public function createZip() {
        if (!class_exists('ZipArchive')) {
            throw new Exception('Your server does not support zipping files.', 400);
        }

        $info = $this->getInfoArray();
        $this->ensureDefinitionFile();

        // Get the basename of the locale.
        $key = key($info);

        $zipPath = PATH_UPLOADS."/$key.zip";
        $tmpPath = PATH_UPLOADS."/tmp_".randomString(10);

        $zip = new ZipArchive();
        $zip->open($tmpPath, ZIPARCHIVE::CREATE);

        // Add all of the files in the locale to the zip.
        $files = safeGlob(rtrim($this->LocalePath, '/').'/*.*', ['php', 'txt']);
        foreach ($files as $file) {
            $localPath = $key.'/'.basename($file);
            $zip->addFile($file, $localPath);
        }

        $zip->close();

        rename($tmpPath, $zipPath);

        return $zipPath;
    }
}
