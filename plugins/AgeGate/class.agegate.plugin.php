<?php

$PluginInfo['AgeGate'] = array(
    'Name' => 'Age Gate',
    'Description' => 'Add Date of Birth to the registration form, and require a certain age to register.',
    'Version' => '1.2.0',
    'Author' => "Becky Van Bussel",
    'AuthorEmail' => 'becky@vanillaforums.com',
    'SettingsUrl' => '/settings/agegate', // Url of the plugin's settings page.

);

/**
 * Class AgeGatePlugin
 *
 * Adds Date of Birth to the registration form and requires a certain age to register.
 *
 * Hidden config setting Plugins.AgeGate.MinimumAgeWithConsent makes the Plugins.AgeGate.MinimumAge setting
 * the age the user must be in order to register. Anybody between the MinimumAge and MinimumAgeWithConsent
 * must confirm they have consent to use the forums.
 */
class AgeGatePlugin extends Gdn_Plugin {

    /**
     * Add AgeGate fields to registration form.
     *
     * @param EntryController $sender Sending Controller.
     * @param array $args Arguments.
     */
    public function entryController_registerBeforeTerms_handler($sender, $args) {
        $this->entryController_registerFormBeforeTerms_handler($sender, $args);
    }

    /**
     * Add AgeGate javascript file.
     *
     * @param EntryController $sender Sending Controller.
     */
    public function entryController_render_before($sender) {
        $sender->addJsFile('agegate.js', 'plugins/AgeGate');
    }

    /**
     * Add AgeGate fields to registration form.
     *
     * @param EntryController $sender Sending Controller.
     * @param array $args Arguments.
     */
    public function entryController_registerFormBeforeTerms_handler($sender, $args) {

        $days = array_merge(
            array(0 => T('Day')),
            array_combine(range(1, 31), range(1, 31))
        );
        $months = array_merge(
            array(0 => T('Month')),
            array_combine(range(1, 12), range(1, 12))
        );
        $years = array_combine(
            range(C('Plugins.AgeGate.StartYear', date('Y')), C('Plugins.AgeGate.StartYear', date('Y') - 100)),
            range(C('Plugins.AgeGate.StartYear', date('Y')), C('Plugins.AgeGate.StartYear', date('Y') - 100))
        );
        $years = array(0 => T('Year')) + $years;

        $minimumAge = C('Plugins.AgeGate.MinimumAge', 0);
        $minimumAgeWithConsent = C('Plugins.AgeGate.MinimumAgeWithConsent', false);
        $addConfirmation = C('Plugins.AgeGate.AddConfirmation', false);

        echo '<li class="agegate-dob">';
        echo $sender->Form->label('Birthday', 'DateOfBirth');
        echo $sender->Form->date('DateOfBirth', array('class' => 'AgeGate'));
        echo '</li>';

        if ($addConfirmation) {
            echo '<input type="hidden" id="Form_MinimumAge" name="MinimumAge" value="'.$minimumAge.'">';
            if ($minimumAgeWithConsent) {
                echo '<input type="hidden" id="Form_MinimumAgeWithConsent" name="MinimumAgeWithConsent" value="'.$minimumAgeWithConsent.'">';
                $minimumAge = $minimumAgeWithConsent;
            }
            echo '<li class="agegate-confirmation js-agegate-confirmation Hidden">';
            echo $sender->Form->CheckBox(
                'AgeGateConfirmation',
                '@'.sprintf(
                    t(
                        'I confirm that I have received consent to join this community.',
                        'Since I\'m under %d years old, I confirm that I have received consent to join this community.'
                    ),
                    $minimumAge
                )
            );
            echo '</li>';
        }
    }

    /**
     * Enforces AgeGate verification at registration submission.
     *
     * @param EntryController $sender Sending Controller.
     * @param array $args Arguments.
     */
    public function entryController_registerValidation_handler($sender, $args) {

        $day = (int)$sender->Form->getFormValue('DateOfBirth_Day', 0);
        $month = (int)$sender->Form->getFormValue('DateOfBirth_Month', 0);
        $year = (int)$sender->Form->getFormValue('DateOfBirth_Year', 0);

        if ($day == 0 || $year == 0 || $month == 0) {
            $sender->UserModel->Validation->addValidationResult('', "Please select a valid Date of Birth.");
            return;
        }

        $dob = Gdn_Format::toDateTime(mktime(0, 0, 0, $month, $day, $year));
        $datetime1 = new DateTime($year . '-' . $month . '-' . $day);
        $datetime2 = new DateTime();

        $interval = $datetime1->diff($datetime2);
        $age =  $interval->format('%y');
        $minimumAge = C('Plugins.AgeGate.MinimumAge', 0);
        $minimumAgeWithConsent = C('Plugins.AgeGate.MinimumAgeWithConsent', false);
        $addConfirmation = C('Plugins.AgeGate.AddConfirmation', false);

        if ($minimumAgeWithConsent) {
            if ($addConfirmation && $age < $minimumAgeWithConsent && $age >= $minimumAge) {
                $sender->UserModel->Validation->applyRule('AgeGateConfirmation', 'Required', 'You must confirm you have received consent to register.');
                return;
            }
            if ($age < $minimumAge) {
                $sender->UserModel->Validation->addValidationResult('', sprintf("You must be at least %d years old to register.", $minimumAge));
                return;
            }
        } elseif ($age < $minimumAge) {
            if ($addConfirmation) {
                $sender->UserModel->Validation->applyRule('AgeGateConfirmation', 'Required', 'You must confirm you have received consent to register.');
            } else {
                $sender->UserModel->Validation->addValidationResult('', sprintf("You must be at least %d years old to register.", $minimumAge));
            }
            return;
        }

        // Set the value on the form so that it will be saved to user model
        if ($sender->Form->errorCount() == 0 && !$sender->UserModel->Validation->results()) {
            $sender->Form->_FormValues['DateOfBirth'] = $dob;
        }

    }

    /**
     * AgeGate settings page.
     *
     * @param SettingsController $sender
     */
    public function settingsController_ageGate_create($sender) {

        $sender->permission('Garden.Settings.Manage');
        $sender->setData('Title', T('Age Gate Settings'));
        $sender->addSideMenu();

        if ($sender->Form->authenticatedPostBack()) {
            $minimumAge = $sender->Form->getValue('MinimumAge');
            $addConfirmation = $sender->Form->getValue('AddConfirmation');

            if (!is_numeric($minimumAge)) {
                $sender->Form->addError('Please enter a valid number.');
            }
            if ($sender->Form->errorCount() == 0) {
                saveToConfig('Plugins.AgeGate.MinimumAge', $minimumAge);
                saveToConfig('Plugins.AgeGate.AddConfirmation', $addConfirmation);
                $sender->informMessage(T('Saved'));
            }
        } else {
            $sender->Form->setData(array(
               'MinimumAge' => C('Plugins.AgeGate.MinimumAge'),
               'AddConfirmation' => C('Plugins.AgeGate.AddConfirmation')
            ));
        }

        $sender->render($sender->fetchViewLocation('settings', '', 'plugins/AgeGate'));
    }

    public function setup() {
        // No setup required
    }

}
