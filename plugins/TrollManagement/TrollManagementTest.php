<?php
/**
 * @author David Barbier<david.barbier@vanillaforums.com>
 * @copyright 2009-2021 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

namespace VanillaTests\TrollManagement;

use UserModel;
use RoleModel;
use VanillaTests\Models\UserModelTest;
use VanillaTests\SetupTraitsTrait;

/**
 * Class TrollManagementTest
 */
class TrollManagementTest extends UserModelTest {
    use SetupTraitsTrait;

    /** @var UserModel */
    protected $userModel;

    /**
     * @inheritDoc
     */
    public function setUp(): void {
        parent::setUp();
    }

    /**
     * Setup routine, run before the test class is instantiated.
     */
    public static function setupBeforeClass(): void {
        self::$addons = ['vanilla', 'trollmanagement'];

        parent::setupBeforeClass();
    }

    /**
     * Tests the automatic assignment of the "applicant" status to a new user registration if the maximum amount of
     * user accounts sharing the same fingerprint is reached.
     */
    public function testRegisterApplicant(): void {
        /** @var \Gdn_Configuration $configuration */
        $configuration = static::container()->get('Config');

        $configuration->set('TrollManagement.PerFingerPrint.Enabled', true);
        $configuration->set('TrollManagement.PerFingerPrint.MaxUserAccounts', 3);

        // Ensure all future registered dummy users uses the same fingerprint.
        $_COOKIE['__vnf'] = 'THISISAFAKEFINGERPRINT';

        // Create 3 dummy accounts. (The third should be automatically set as "applicant")
        $importedUsers[] = $this->insertDummyUser();
        $importedUsers[] = $this->insertDummyUser();
        $importedUsers[] = $this->insertDummyUser();

        // We pull the associated user's roles.
        foreach ($importedUsers as $importedUser) {
            $importedUsersRolesIDs[] = $this->userModel->getRoleIDs($importedUser['UserID']);
        }

        // The FIRST dummy user account is NOT an applicant
        $this->assertNotContains(RoleModel::APPLICANT_ID, $importedUsersRolesIDs['0']);
        // The SECOND dummy user account is NOT an applicant
        $this->assertNotContains(RoleModel::APPLICANT_ID, $importedUsersRolesIDs['1']);
        // The THIRD dummy user account is NOT an applicant
        $this->assertContains(RoleModel::APPLICANT_ID, $importedUsersRolesIDs['2']);
    }

    /**
     * Tests the automatic assignment of the "applicant" status to EVERY new user registration.
     */
    public function testRegisterAllApplicants(): void {
        /** @var \Gdn_Configuration $configuration */
        $configuration = static::container()->get('Config');

        $configuration->set('TrollManagement.PerFingerPrint.Enabled', true);
        $configuration->set('TrollManagement.PerFingerPrint.MaxUserAccounts', 0);

        // Create 3 dummy accounts. (They should all be automatically set as "applicant")
        $importedUsers[] = $this->insertDummyUser();
        $importedUsers[] = $this->insertDummyUser();
        $importedUsers[] = $this->insertDummyUser();

        // We pull the associated user's roles.
        foreach ($importedUsers as $importedUser) {
            $importedUsersRolesIDs = $this->userModel->getRoleIDs($importedUser['UserID']);
            // The dummy user account is an applicant.
            $this->assertContains(RoleModel::APPLICANT_ID, $importedUsersRolesIDs);
        }
    }

    /**
     * The plugin is disabled. Even if every users are using the same fingerprint, none are flagged as "applicant".
     */
    public function testRegisterDisabledPlugin(): void {
        /** @var \Gdn_Configuration $configuration */
        $configuration = static::container()->get('Config');

        $configuration->set('TrollManagement.PerFingerPrint.Enabled', false);
        $configuration->set('TrollManagement.PerFingerPrint.MaxUserAccounts', 1);

        // Ensure all future registered dummy users uses the same fingerprint.
        $_COOKIE['__vnf'] = 'THISISAFAKEFINGERPRINT';

        // Create 3 dummy accounts. (None should be automatically set as "applicant")
        $importedUsers[] = $this->insertDummyUser();
        $importedUsers[] = $this->insertDummyUser();
        $importedUsers[] = $this->insertDummyUser();

        // We pull the associated user's roles.
        foreach ($importedUsers as $importedUser) {
            $importedUsersRolesIDs = $this->userModel->getRoleIDs($importedUser['UserID']);
            // The dummy user account is NOT an applicant
            $this->assertNotContains(RoleModel::APPLICANT_ID, $importedUsersRolesIDs);
        }
    }
}
