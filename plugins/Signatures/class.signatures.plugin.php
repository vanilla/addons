<?php if (!defined('APPLICATION')) { exit(); }

/**
 * Signatures Plugin
 *
 * This plugin allows users to maintain a 'Signature' which is automatically
 * appended to all discussions and comments they make.
 *
 * Changes:
 *  1.0     Initial release
 *  1.4     Add SimpleAPI hooks
 *  1.4.1   Allow self-API access
 *  1.5     Improve "Hide Images"
 *  1.5.1   Improve permission checking granularity
 *  1.5.3-5 Disallow images plugin-wide from dashboard
 *  1.6     Add signature constraints and enhance mobile capacity
 *  1.6.1   The spacening.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @copyright 2003 Vanilla Forums, Inc
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPL
 * @package Addons
 */

class SignaturesPlugin extends Gdn_Plugin {
    public $Disabled = false;

    /**
     * @var array List of config settings can be overridden by sessions in other plugins
     */
    private $overriddenConfigSettings = ['MaxNumberImages', 'MaxLength'];


    /**
     * Add mapper methods
     *
     * @param SimpleApiPlugin $Sender
     */
    public function simpleApiPlugin_mapper_handler($Sender) {
        switch ($Sender->Mapper->Version) {
            case '1.0':
                $Sender->Mapper->addMap([
                    'signature/get' => 'profile/signature/modify',
                    'signature/set' => 'profile/signature/modify',
                ], null, [
                    'signature/get' => ['Signature'],
                    'signature/set' => ['Success'],
                ]);
                break;
        }
    }

    /**
     * Add "Signature Settings" to profile edit mode side menu.
     *
     * @param $Sender
     */
    public function profileController_afterAddSideMenu_handler($Sender) {
        if (!CheckPermission('Garden.SignIn.Allow')) {
            return;
        }

        $SideMenu = $Sender->EventArguments['SideMenu'];
        $ViewingUserID = Gdn::session()->UserID;

        if ($Sender->User->UserID == $ViewingUserID) {
            $SideMenu->addLink('Options', sprite('SpSignatures').' '.t('Signature Settings'), '/profile/signature', false, ['class' => 'Popup']);
        } else {
            $SideMenu->addLink('Options', sprite('SpSignatures').' '.t('Signature Settings'), userUrl($Sender->User, '', 'signature'), ['Garden.Users.Edit', 'Moderation.Signatures.Edit'], ['class' => 'Popup']);
        }
    }

    /**
     * Add "Signature Settings" to Profile Edit button group.
     * Only do this if they cannot edit profiles because otherwise they can't navigate there.
     *
     * @param $Sender
     */
    public function profileController_beforeProfileOptions_handler($Sender, $Args) {
        $CanEditProfiles = checkPermission('Garden.Users.Edit') || checkPermission('Moderation.Profiles.Edit');
        if (checkPermission('Moderation.Signatures.Edit') && !$CanEditProfiles) {
            $Args['ProfileOptions'][] = ['Text' => sprite('SpSignatures').' '.t('Signature Settings'), 'Url' => userUrl($Sender->User, '', 'signature')];
        }
    }

    /**
     * Profile settings
     *
     * @param ProfileController $Sender
     */
    public function profileController_signature_create($Sender) {
        $Sender->permission('Garden.SignIn.Allow');
        $Sender->title(t('Signature Settings'));

        $this->dispatch($Sender);
    }


    public function controller_index($Sender) {
        $Sender->permission([
            'Garden.Profiles.Edit'
        ]);

        $Args = $Sender->RequestArgs;
        if (sizeof($Args) < 2) {
            $Args = array_merge($Args, [0, 0]);
        } elseif (sizeof($Args) > 2) {
            $Args = array_slice($Args, 0, 2);
        }

        list($UserReference, $Username) = $Args;

        $canEditSignatures = checkPermission('Plugins.Signatures.Edit');

        // Normalize no image config setting
        if (c('Plugins.Signatures.MaxNumberImages') === 0 || c('Plugins.Signatures.MaxNumberImages') === '0') {
            saveToConfig('Plugins.Signatures.MaxNumberImages', 'None');
        }

        $Sender->getUserInfo($UserReference, $Username);
        $UserPrefs = dbdecode($Sender->User->Preferences);
        if (!is_array($UserPrefs)) {
            $UserPrefs = [];
        }

        $Validation = new Gdn_Validation();
        $ConfigurationModel = new Gdn_ConfigurationModel($Validation);
        $ConfigArray = [
            'Plugin.Signatures.Sig' => null,
            'Plugin.Signatures.HideAll' => null,
            'Plugin.Signatures.HideImages' => null,
            'Plugin.Signatures.HideMobile' => null,
            'Plugin.Signatures.Format' => null
        ];
        $SigUserID = $ViewingUserID = Gdn::session()->UserID;

        if ($Sender->User->UserID != $ViewingUserID) {
            $Sender->permission(['Garden.Users.Edit', 'Moderation.Signatures.Edit'], false);
            $SigUserID = $Sender->User->UserID;
            $canEditSignatures = true;
        }

        $Sender->setData('CanEdit', $canEditSignatures);
        $Sender->setData('Plugin-Signatures-ForceEditing', ($SigUserID == Gdn::session()->UserID) ? false : $Sender->User->Name);
        $UserMeta = $this->getUserMeta($SigUserID, '%');

        if ($Sender->Form->authenticatedPostBack() === false && is_array($UserMeta)) {
            $ConfigArray = array_merge($ConfigArray, $UserMeta);
        }

        $ConfigurationModel->setField($ConfigArray);

        // Set the model on the form.
        $Sender->Form->setModel($ConfigurationModel);

        $Data = $ConfigurationModel->Data;
        $Sender->setData('Signature', $Data);

        $this->setSignatureRules($Sender);

        // Form submission handling.
        if ($Sender->Form->authenticatedPostBack()) {
            $Values = $Sender->Form->formValues();

            if ($canEditSignatures) {
                $Values['Plugin.Signatures.Sig'] = val('Body', $Values, null);
                $Values['Plugin.Signatures.Format'] = val('Format', $Values, null);
            }

            //$this->stripLineBreaks($Values['Plugin.Signatures.Sig']);

            $FrmValues = array_intersect_key($Values, $ConfigArray);

            if (sizeof($FrmValues)) {

                if (!GetValue($this->makeMetaKey('Sig'), $FrmValues)) {
                    // Delete the signature.
                    $FrmValues[$this->makeMetaKey('Sig')] = null;
                    $FrmValues[$this->makeMetaKey('Format')] = null;
                }

                $this->crossCheckSignature($Values, $Sender);

                if ($Sender->Form->errorCount() == 0) {
                    foreach ($FrmValues as $UserMetaKey => $UserMetaValue) {
                        $Key = $this->trimMetaKey($UserMetaKey);

                        switch ($Key) {
                            case 'Format':
                                if (strcasecmp($UserMetaValue, 'Raw') == 0) {
                                    $UserMetaValue = null;
                                } // don't allow raw signatures.
                                break;
                        }

                        $this->setUserMeta($SigUserID, $Key, $UserMetaValue);
                    }
                    $Sender->informMessage(T("Your changes have been saved."));
                }
            }
        } else {
            // Load form data.
            $Data['Body'] = val('Plugin.Signatures.Sig', $Data);
            $Data['Format'] = val('Plugin.Signatures.Format', $Data) ?: Gdn_Format::defaultFormat();

            // Apply the config settings to the form.
            $Sender->Form->setData($Data);
        }

        $Sender->render('signature', '', 'plugins/Signatures');
    }

    /**
     * Checks signature against constraints set in config settings,
     * and executes the external ValidateSignature function, if it exists.
     *
     * @param $Values Signature settings form values
     * @param $Sender Controller
     */
    public function crossCheckSignature($Values, &$Sender) {
        $this->checkSignatureLength($Values, $Sender);
        $this->checkNumberOfImages($Values, $Sender);

        // Validate the signature.
        if (function_exists('ValidateSignature')) {
            $Sig = trim(val('Plugin.Signatures.Sig', $Values));
            if (validateRequired($Sig) && !ValidateSignature($Sig, val('Plugin.Signatures.Format', $Values))) {
                $Sender->Form->addError('Signature invalid.');
            }
        }
    }

    /**
     * Checks signature length against Plugins.Signatures.MaxLength
     *
     * @param $Values Signature settings form values
     * @param $Sender Controller
     */
    public function checkSignatureLength($Values, &$Sender) {
        if (c('Plugins.Signatures.MaxLength') && c('Plugins.Signatures.MaxLength') > 0) {
            $Sig = Gdn_Format::to($Values['Plugin.Signatures.Sig'], $Sender->Form->getFormValue('Format'));
            $TextValue = html_entity_decode(trim(strip_tags($Sig)));

            // Validate the amount of text.
            if (strlen($TextValue) > c('Plugins.Signatures.MaxLength')) {
                $Sender->Form->addError(sprintf(t('ValidateLength'), t('Signature'), (strlen($TextValue) - c('Plugins.Signatures.MaxLength'))));
            }
        }
    }

    /**
     * Checks number of images in signature against Plugins.Signatures.MaxNumberImages
     *
     * @param $Values Signature settings form values
     * @param $Sender Controller
     */
    public function checkNumberOfImages($Values, &$Sender) {
        if (c('Plugins.Signatures.MaxNumberImages') && c('Plugins.Signatures.MaxNumberImages') !== 'Unlimited') {
            $max = c('Plugins.Signatures.MaxNumberImages');
            $Sig = Gdn_Format::to(val('Plugin.Signatures.Sig', $Values), val('Plugin.Signatures.Format', $Values, c('Garden.InputFormatter')));
            $numMatches = preg_match_all('/<img/i', $Sig);
            if (c('Plugins.Signatures.MaxNumberImages') === 'None' && $numMatches > 0) {
                $Sender->Form->addError('Images not allowed');
            } else {
                if ($numMatches > $max) {
                    $Sender->Form->addError('@'.formatString('You are only allowed {maxImages,plural,%s image,%s images}.',
                            ['maxImages' => $max]));
                }
            }
        }
    }

    public function setSignatureRules($Sender) {
        $rules = [];
        $rulesParams = [];
        $imagesAllowed = true;


        if (c('Plugins.Signatures.MaxNumberImages', 'Unlimited') !== 'Unlimited') {
            if (c('Plugins.Signatures.MaxNumberImages') === 'None') {
                $rules[] = t('Images not allowed').'.';
                $imagesAllowed = false;
            } else {
                $rulesParams['maxImages'] = c('Plugins.Signatures.MaxNumberImages');
                $rules[] = formatString(t('Use up to {maxImages,plural,%s image, %s images}.'), $rulesParams);
            }
        }
        if ($imagesAllowed && c('Plugins.Signatures.MaxImageHeight') && c('Plugins.Signatures.MaxImageHeight') > 0) {
            $rulesParams['maxImageHeight'] = c('Plugins.Signatures.MaxImageHeight');
            $rules[] = formatString(t('Images will be scaled to a maximum height of {maxImageHeight}px.'), $rulesParams);

        }
        if (c('Plugins.Signatures.MaxLength') && c('Plugins.Signatures.MaxLength') > 0) {
            $rulesParams['maxLength'] = c('Plugins.Signatures.MaxLength');
            $rules[] = formatString(t('Signatures can be up to {maxLength} characters long.'), $rulesParams);
        }

        $Sender->setData('SignatureRules', implode(' ', $rules));
    }


    /**
     * Strips all line breaks from text
     *
     * @param string $Text
     * @param string $Delimiter
     */
    public function stripLineBreaks(&$Text, $Delimiter = ' ') {
        $Text = str_replace(["\r\n", "\r"], "\n", $Text);
        $lines = explode("\n", $Text);
        $new_lines = [];
        foreach ($lines as $i => $line) {
            $line = trim($line);
            if (!empty($line)) {
                $new_lines[] = $line;
            }
        }
        $Text = implode($new_lines, $Delimiter);
    }

    public function stripFormatting() {

    }

    /*
     * API METHODS
     */

    public function controller_Modify($Sender) {
        if (!Gdn::request()->isAuthenticatedPostBack(true)) {
            throw new Exception('Requires POST', 405);
        }
        $Sender->deliveryMethod(DELIVERY_METHOD_JSON);
        $Sender->deliveryType(DELIVERY_TYPE_DATA);

        $UserID = Gdn::request()->get('UserID');
        if ($UserID != Gdn::session()->UserID) {
            $Sender->permission(['Garden.Users.Edit', 'Moderation.Signatures.Edit'], false);
        } else {
            $Sender->permission(['Garden.Profiles.Edit', 'Plugins.Signatures.Edit']);
        }
        $User = Gdn::userModel()->getID($UserID);
        if (!$User) {
            throw new Exception("No such user '{$UserID}'", 404);
        }

        $Translation = [
            'Plugin.Signatures.Sig' => 'Body',
            'Plugin.Signatures.Format' => 'Format',
            'Plugin.Signatures.HideAll' => 'HideAll',
            'Plugin.Signatures.HideImages' => 'HideImages',
            'Plugin.Signatures.HideMobile' => 'HideMobile'
        ];

        $UserMeta = $this->getUserMeta($UserID, '%');
        $SigData = [];
        foreach ($Translation as $TranslationField => $TranslationShortcut) {
            $SigData[$TranslationShortcut] = val($TranslationField, $UserMeta, null);
        }

        $Sender->setData('Signature', $SigData);

        if ($Sender->Form->isPostBack()) {
            $Sender->setData('Success', false);

            // Validate the signature.
            if (function_exists('ValidateSignature')) {
                $Sig = $Sender->Form->getFormValue('Body');
                $Format = $Sender->Form->getFormValue('Format');
                if (validateRequired($Sig) && !ValidateSignature($Sig, $Format)) {
                    $Sender->Form->addError('Signature invalid.');
                }
            }

            if ($Sender->Form->errorCount() == 0) {
                foreach ($Translation as $TranslationField => $TranslationShortcut) {
                    $UserMetaValue = $Sender->Form->getValue($TranslationShortcut, null);
                    if (is_null($UserMetaValue)) {
                        continue;
                    }

                    if ($TranslationShortcut == 'Body' && empty($UserMetaValue)) {
                        $UserMetaValue = null;
                    }

                    $Key = $this->trimMetaKey($TranslationField);

                    switch ($Key) {
                        case 'Format':
                            if (strcasecmp($UserMetaValue, 'Raw') == 0) {
                                $UserMetaValue = null;
                            } // don't allow raw signatures.
                            break;
                    }

                    if ($Sender->Form->errorCount() == 0) {
                        $this->setUserMeta($UserID, $Key, $UserMetaValue);
                    }
                }
                $Sender->setData('Success', true);
            }
        }

        $Sender->render();
    }

    protected function userPreferences($SigKey = null, $Default = null) {
        static $UserSigData = null;
        if (is_null($UserSigData)) {
            $UserSigData = $this->getUserMeta(Gdn::session()->UserID, '%');

//         decho($UserSigData);
        }

        if (!is_null($SigKey)) {
            return val($SigKey, $UserSigData, $Default);
        }

        return $UserSigData;
    }

    protected function signatures($Sender, $RequestUserID = null, $Default = null) {
        static $Signatures = null;

        if (is_null($Signatures)) {
            $Signatures = [];

            // Short circuit if not needed.
            if ($this->hide()) {
                return $Signatures;
            }

            $Discussion = $Sender->data('Discussion');
            $Comments = $Sender->data('Comments');
            $UserIDList = [];

            if ($Discussion) {
                $UserIDList[GetValue('InsertUserID', $Discussion)] = 1;
            }

            if ($Comments && $Comments->numRows()) {
                $Comments->dataSeek(-1);
                while ($Comment = $Comments->nextRow()) {
                    $UserIDList[GetValue('InsertUserID', $Comment)] = 1;
                }
            }

            if (sizeof($UserIDList)) {
                $DataSignatures = $this->getUserMeta(array_keys($UserIDList), 'Sig');
                $Formats = (array)$this->getUserMeta(array_keys($UserIDList), 'Format');

                if (is_array($DataSignatures)) {
                    foreach ($DataSignatures as $UserID => $UserSig) {
                        $Sig = val($this->makeMetaKey('Sig'), $UserSig);
                        if (isset($Formats[$UserID])) {
                            $Format = val($this->makeMetaKey('Format'), $Formats[$UserID], c('Garden.InputFormatter'));
                        } else {
                            $Format = c('Garden.InputFormatter');
                        }

                        $Signatures[$UserID] = [$Sig, $Format];
                    }
                }
            }

        }

        if (!is_null($RequestUserID)) {
            return val($RequestUserID, $Signatures, $Default);
        }

        return $Signatures;
    }


    /** Deprecated in 2.1. */
    public function base_afterCommentBody_handler($Sender) {
        if ($this->Disabled) {
            return;
        }

        $this->drawSignature($Sender);
    }

    /**
     * Add a custom signature style tag to enforce image height.
     *
     * @param Gdn_Control $sender
     * @param array $args
     */
    public function base_render_before($sender, $args) {
        if ($maxImageHeight = c('Plugins.Signatures.MaxImageHeight')) {
            $maxImageHeight = (int)$maxImageHeight;

            $style = <<<EOT
.Signature img, .UserSignature img {
   max-height: {$maxImageHeight}px !important;
}
EOT;

            $sender->Head->addTag('style', ['_sort' => 100], $style);
        }
    }

    /** New call for 2.1. */
    public function discussionController_afterDiscussionBody_handler($Sender) {
        if ($this->Disabled) {
            return;
        }
        $this->drawSignature($Sender);
    }

    protected function drawSignature($Sender) {
        if ($this->hide()) {
            return;
        }

        if (isset($Sender->EventArguments['Discussion'])) {
            $Data = $Sender->EventArguments['Discussion'];
        }

        if (isset($Sender->EventArguments['Comment'])) {
            $Data = $Sender->EventArguments['Comment'];
        }

        $SourceUserID = val('InsertUserID', $Data);
        $User = Gdn::userModel()->getID($SourceUserID, DATASET_TYPE_ARRAY);
        if (val('HideSignature', $User, false)) {
            return;
        }


        $Signature = $this->signatures($Sender, $SourceUserID);

        if (is_array($Signature)) {
            list($Signature, $SigFormat) = $Signature;
        } else {
            $SigFormat = c('Garden.InputFormatter');
        }

        if (!$SigFormat) {
            $SigFormat = c('Garden.InputFormatter');
        }

        $this->EventArguments = [
            'UserID' => $SourceUserID,
            'Signature' => &$Signature
        ];
        $this->fireEvent('BeforeDrawSignature');

        $SigClasses = '';
        if (!is_null($Signature)) {
            $HideImages = $this->userPreferences('Plugin.Signatures.HideImages', false);

            if ($HideImages) {
                $SigClasses .= 'HideImages ';
            }

            // Don't show empty sigs
            if ($Signature == '') {
                return;
            }

            // If embeds were disabled from the dashboard, temporarily set the
            // universal config to make sure no URLs are turned into embeds.
            if (!C('Plugins.Signatures.AllowEmbeds', true)) {
                $originalEnableUrlEmbeds = c('Garden.Format.DisableUrlEmbeds', false);
                saveToConfig([
                    'Garden.Format.DisableUrlEmbeds' => true
                ], null, [
                    'Save' => false
                ]);
            }

            $UserSignature = Gdn_Format::to($Signature, $SigFormat)."<!-- $SigFormat -->";

            // Restore original config.
            if (!C('Plugins.Signatures.AllowEmbeds', true)) {
                saveToConfig([
                    'Garden.Format.DisableUrlEmbeds' => $originalEnableUrlEmbeds
                ], null, [
                    'Save' => false
                ]);
            }

            $this->EventArguments = [
                'UserID' => $SourceUserID,
                'String' => &$UserSignature
            ];

            $this->fireEvent('FilterContent');

            if ($UserSignature) {
                echo "<div class=\"Signature UserSignature {$SigClasses}\">{$UserSignature}</div>";
            }
        }
    }

    protected function hide() {
        if ($this->Disabled) {
            return true;
        }

        if (!Gdn::session()->isValid() && c('Plugins.Signatures.HideGuest')) {
            return true;
        }

        if (strcasecmp(Gdn::controller()->RequestMethod, 'embed') == 0 && c('Plugin.Signatures.HideEmbed', true)) {
            return true;
        }

        if ($this->userPreferences('Plugin.Signatures.HideAll', false)) {
            return true;
        }

        if (isMobile() && (c('Plugins.Signatures.HideMobile', true) || $this->userPreferences('Plugin.Signatures.HideMobile', false))) {
            return true;
        }

        return false;
    }

    protected function _stripOnly($str, $tags, $stripContent = false) {
        $content = '';
        if (!is_array($tags)) {
            $tags = (strpos($str, '>') !== false ? explode('>', str_replace('<', '', $tags)) : [$tags]);
            if (end($tags) == '') {
                array_pop($tags);
            }
        }
        foreach ($tags as $tag) {
            if ($stripContent) {
                $content = '(.+</'.$tag.'[^>]*>|)';
            }
            $str = preg_replace('#</?'.$tag.'[^>]*>'.$content.'#is', '', $str);
        }
        return $str;
    }

    public function setup() {
        // Nothing to do here!
    }

    public function structure() {
        // Nothing to do here!
    }

    public function assetModel_styleCss_handler($Sender) {
        $Sender->addCssFile('signature.css', 'plugins/Signatures');
    }


    public function settingsController_signatures_create($Sender) {
        $Sender->permission('Garden.Settings.Manage');

        $Conf = new ConfigurationModule($Sender);
        $Conf->initialize([
            'Plugins.Signatures.Default.MaxNumberImages' => ['Control' => 'Dropdown', 'LabelCode' => '@'.sprintf(t('Max number of %s'), t('images')), 'Items' => ['Unlimited' => t('Unlimited'), 'None' => t('None'), 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5]],
            'Plugins.Signatures.Default.MaxLength' => ['Control' => 'TextBox', 'Type' => 'int', 'LabelCode' => '@'.sprintf(t('Max %s length'), t('signature')), 'Options' => ['class' => 'InputBox SmallInput']],
            'Plugins.Signatures.MaxImageHeight' => ['Control' => 'TextBox', 'LabelCode' => '@'.sprintf(t('Max height of %s'), t('images'))." ".t('in pixels'), 'Options' => ['class' => 'InputBox SmallInput']],
            'Plugins.Signatures.HideGuest' => ['Control' => 'CheckBox', 'LabelCode' => 'Hide signatures for guests'],
            'Plugins.Signatures.HideEmbed' => ['Control' => 'CheckBox', 'LabelCode' => 'Hide signatures on embedded comments', 'Default' => true],
            'Plugins.Signatures.HideMobile' => ['Control' => 'CheckBox', 'LabelCode' => 'Hide signatures on mobile', 'Default' => true],
            'Plugins.Signatures.AllowEmbeds' => ['Control' => 'CheckBox', 'LabelCode' => 'Allow embedded content', 'Default' => true],
        ]);

        $this->setConfigSettingsToDefault('Plugins.Signatures', $this->overriddenConfigSettings);

        $Sender->addSideMenu();
        $Sender->setData('Title', sprintf(t('%s Settings'), t('Signature')));
        $Sender->ConfigurationModule = $Conf;
        $Conf->renderAll();
//      $Sender->render('Settings', '', 'plugins/AmazonS3');
    }

    /**
     * Why do we need this? (i.e., Mantra for the function)
     * We retrieve the signature restraints from the config settings.
     * These are sometimes overridden by plugins (i.e., Ranks)
     * If we load the dashboard signature settings form from the config file,
     * we will get whatever session config settings are present, not
     * the default. As such, we've created default config variables that
     * populate the form, but we've got to transfer them over to the
     * config settings in use.
     *
     * Sets config settings to the default settings.
     *
     *
     * @param string $basename
     * @param array $settings
     *
     */
    public function setConfigSettingsToDefault($basename, $settings) {
        foreach ($settings as $setting) {
            saveToConfig($basename.'.'.$setting, c($basename.'.Default.'.$setting));
        }
    }
}
