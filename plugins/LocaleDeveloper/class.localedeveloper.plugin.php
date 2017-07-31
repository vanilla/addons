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
    public function Base_Render_After($Sender, $Args) {
        $Locale = Gdn::Locale();
        if (!is_a($Locale, 'DeveloperLocale'))
            return;

        $Path = $this->LocalePath.'/tmp_'.RandomString(10);
        if (!file_exists(dirname($Path)))
            mkdir(dirname($Path), 0777, TRUE);
        elseif (file_exists($Path)) {
            // Load the existing definitions.
            $Locale->Load($Path);
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

        $CapturedDefinitions = $Locale->CapturedDefinitions();

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
            fwrite($fp, $this->GetFileHeader());
            LocaleModel::WriteDefinitions($fp, $Definition);
            fclose($fp);

            // Copy the file over the existing one.
            $Result = rename($Path, $FinalPath);
        }
    }

    public function Gdn_Dispatcher_BeforeDispatch_Handler($sender) {
        if (C('Plugins.LocaleDeveloper.CaptureDefinitions')) {
            // Install the developer locale.
            $_Locale = new DeveloperLocale(Gdn::Locale()->Current(), C('EnabledApplications'), C('EnabledPlugins'));

            $tmp = Gdn::FactoryOverwrite(TRUE);
            Gdn::FactoryInstall(Gdn::AliasLocale, 'Gdn_Locale', NULL, Gdn::FactorySingleton, $_Locale);
            Gdn::FactoryOverwrite($tmp);
            unset($tmp);
        }
    }

    /**
     *
     * @param Gdn_Controller $sender
     * @param array $args
     */
    public function SettingsController_Render_Before($sender, $args) {
        if (strcasecmp($sender->RequestMethod, 'locales') != 0)
            return;

        // Add a little pointer to the settings.
        $text = '<div class="Info">'.
            sprintf(T('Locale Developer Settings %s.'), Anchor(T('here'), '/settings/localedeveloper')).
            '</div>';
        $sender->AddAsset('Content', $text, 'LocaleDeveloperLink');
    }

    /**
     *
     * @var SettingsController $sender
     */
    public function SettingsController_LocaleDeveloper_Create($sender, $args = []) {
        $sender->Permission('Garden.Settings.Manage');

        $sender->AddSideMenu();
        $sender->SetData('Title', T('Locale Developer'));

        switch (strtolower(GetValue(0, $args, ''))) {
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
            $path = $this->CreateZip();

            // Serve the zip file.
            Gdn_FileSystem::ServeFile($path, basename($path), 'application/zip');
        } catch (Exception $ex) {
            $this->Form->AddError($ex);
            $this->_Settings($sender, $args);
        }
    }

    public function EnsureDefinitionFile() {
        $path = $this->LocalePath.'/definitions.php';
        if (file_exists($path))
            unlink($path);
        $contents = $this->GetFileHeader().self::FormatInfoArray('$LocaleInfo', $this->GetInfoArray());
        Gdn_FileSystem::SaveFile($path, $contents);
    }

    public static function FormatInfoArray($variableName, $array) {
        $variableName = '$'.trim($variableName, '$');

        $result = '';
        foreach ($array as $key => $value) {
            $result .= $variableName."['".addcslashes($key, "'")."'] = ";
            $result .= var_export($value, TRUE);
            $result .= ";\n\n";
        }

        return $result;
    }

    public static function FormatValue($value, $singleLine = TRUE) {
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

                $result .= "'".addcslashes($key, "'")."' => ".self::FormatValue($arrayValue, $singleLine);
            }

            $result = 'array('.$result.')';
            return $result;
        } else {
            $error = print_r($value);
            $error = str_replace('*/', '', $error);

            return "/* Could not format the following value:\n{$error}\n*/";
        }
    }

    public function GetFileHeader() {
        $now = Gdn_Format::ToDateTime();

        $result = "<?php if (!defined('APPLICATION')) exit();
/** This file was generated by the Locale Developer plugin on $now **/\n\n";

        return $result;
    }

    public function GetInfoArray() {
        $info = C('Plugins.LocaleDeveloper');
        foreach ($info as $key => $value) {
            if (!$value)
                unset($info[$key]);
        }

        $infoArray = [GetValue('Key', $info, 'LocaleDeveloper') => [
            'Locale' => GetValue('Locale', $info, Gdn::Locale()->Current()),
            'Name' => GetValue('Name', $info, 'Locale Developer'),
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

        if ($this->Form->IsPostBack()) {
            exit('Foo');

        } else {
            // Load all of the definitions.
            //$Definitions = $this->LoadDefinitions();
            //$Sender->SetData('Definitions', $Definitions);
        }

        $sender->Render('googletranslate', '', 'plugins/LocaleDeveloper');
    }

//   public function LoadDefinitions($Path = NULL) {
//      if ($Path === NULL)
//         $Path = $this->LocalePath;
//
//      $Paths = SafeGlob($Path.'/*.php');
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
        $localePacks = $localeModel->AvailableLocalePacks();
        $localArray = [];
        foreach ($localePacks as $key => $info) {
            $localeArray[$key] = GetValue('Name', $info, $key);
        }
        $sender->SetData('LocalePacks', $localeArray);

        if ($this->Form->IsPostBack()) {
            if ($this->Form->GetFormValue('Save')) {
                $values = ArrayTranslate($this->Form->FormValues(), ['Key', 'Name', 'Locale', 'CaptureDefinitions']);
                $saveValues = [];
                foreach ($values as $key => $value) {
                    $saveValues['Plugins.LocaleDeveloper.'.$key] = $value;
                }

                // Save the settings.
                SaveToConfig($saveValues, '', ['RemoveEmpty' => TRUE]);

                $sender->StatusMessage = T('Your changes have been saved.');
            } elseif ($this->Form->GetFormValue('GenerateChanges')) {
                $key = $this->Form->GetFormValue('LocalePackForChanges');
                if (!$key)
                    $this->Form->AddError('ValidateRequired', 'Locale Pack');
                $path = PATH_ROOT.'/locales/'.$key;
                if (!file_exists($path))
                    $this->Form->AddError('Could not find the selected locale pack.');

                if ($this->Form->ErrorCount() == 0) {
                    try {
                        $localeModel->GenerateChanges($path, $this->LocalePath);
                        $sender->StatusMessage = T('Your changes have been saved.');
                    } catch (Exception $ex) {
                        $this->Form->AddError($ex);
                    }
                }
            } elseif ($this->Form->GetFormValue('Copy')) {
                $key = $this->Form->GetFormValue('LocalePackForCopy');
                if (!$key)
                    $this->Form->AddError('ValidateRequired', 'Locale Pack');
                $path = PATH_ROOT.'/locales/'.$key;
                if (!file_exists($path))
                    $this->Form->AddError('Could not find the selected locale pack.');

                if ($this->Form->ErrorCount() == 0) {
                    try {
                        $localeModel->CopyDefinitions($path, $this->LocalePath.'/copied.php');
                        $sender->StatusMessage = T('Your changes have been saved.');
                    } catch (Exception $ex) {
                        $this->Form->AddError($ex);
                    }
                }
            } elseif ($this->Form->GetFormValue('Remove')) {
                $files = SafeGlob($this->LocalePath.'/*');
                foreach ($files as $file) {
                    $result = unlink($file);
                    if (!$result) {
                        $this->Form->AddError('@'.sprintf(T('Could not remove %s.'), $file));
                    }
                }
                if ($this->Form->ErrorCount() == 0)
                    $sender->StatusMessage = T('Your changes have been saved.');
            }
        } else {
            $values = C('Plugins.LocaleDeveloper');
            foreach ($values as $key => $value) {
                $this->Form->SetFormValue($key, $value);
            }
        }

        $sender->SetData('LocalePath', $this->LocalePath);

        $sender->Render('', '', 'plugins/LocaleDeveloper');
    }

    public function WriteInfoArray($fp) {
        $info = C('Plugins.LocaleDeveloper');

        // Write the info array.
        $infoArray = $this->GetInfoArray();

        $infoString = self::FormatInfoArray('$LocaleInfo', $infoArray);
        fwrite($fp, $infoString);
    }

    public function CreateZip() {
        if (!class_exists('ZipArchive')) {
            throw new Exception('Your server does not support zipping files.', 400);
        }

        $info = $this->GetInfoArray();
        $this->EnsureDefinitionFile();

        // Get the basename of the locale.
        $key = key($info);

        $zipPath = PATH_UPLOADS."/$key.zip";
        $tmpPath = PATH_UPLOADS."/tmp_".RandomString(10);

        $zip = new ZipArchive();
        $zip->open($tmpPath, ZIPARCHIVE::CREATE);

        // Add all of the files in the locale to the zip.
        $files = SafeGlob(rtrim($this->LocalePath, '/').'/*.*', ['php', 'txt']);
        foreach ($files as $file) {
            $localPath = $key.'/'.basename($file);
            $zip->addFile($file, $localPath);
        }

        $zip->close();

        rename($tmpPath, $zipPath);

        return $zipPath;
    }
}
