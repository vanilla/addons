<?php
/**
 * @copyright 2015 Vanilla Forums, Inc.
 */

/**
 * Class BurnerBlockerPlugin
 *
 * Periodically the lists in /payloads should be upgraded from these sources:
 *
 * @see https://raw.githubusercontent.com/ivolo/disposable-email-domains/master/index.json
 * @see https://raw.githubusercontent.com/lavab/disposable/master/domains.json
 *
 * @todo Add settings page for BlockFreeEmails country configuration.
 */
class BurnerBlockerPlugin extends Gdn_Plugin {

    /** @var array Add burner domains not in our payloads. */
    protected $burnerDomains = [
        'slipry.net',
    ];

    /** @var array Chinese free email domains. */
    protected $cnDomains = [
        '139.com',
        'daum.net',
        'hanmail.net',
    ];

    /** @var array Russian free email domains. */
    protected $ruDomains = [
        'yandex.ru',
    ];

    /** @var array US free email domains. */
    protected $usDomains = [
        'gmail.com',
        'hotmail.com',
        'outlook.com',
        'yahoo.com',
    ];

    /**
     * Get & validate our JSON payloads.
     *
     * Pro-tip: Never get clever and loop this array_merge().
     *
     * @return array Domains to block.
     */
    public function getBurnerDomains() {
        $ivolo = json_decode(file_get_contents(__DIR__.'/payloads/ivolo.json'));
        if (!is_array($ivolo)) {
            $ivolo = [];
        }

        $lavab = json_decode(file_get_contents(__DIR__.'/payloads/lavab.json'));
        if (!is_array($lavab)) {
            $lavab = [];
        }

        return array_merge($ivolo, $lavab, $this->burnerDomains);
    }

    /**
     * Evaluate which countries' freemail domains to block.
     *
     * @return array Domains to block.
     */
    public function getFreemailDomains() {
        $cn = (c('Registration.BlockFreeEmails.CN')) ? $this->cnDomains : [];
        $ru = (c('Registration.BlockFreeEmails.RU')) ? $this->ruDomains : [];
        $us = (c('Registration.BlockFreeEmails.US')) ? $this->usDomains : [];

        return array_merge($cn, $ru, $us);
    }

    /**
     * Block registration of users with a known burner email domain.
     *
     * @param $sender UserModel Triggering object.
     * @param $args array Event arguments.
     */
    public function userModel_beforeRegister_handler($sender, &$args) {
        // Get the user's email domain.
        $email = val('Email', $args['RegisteringUser']);
        $domain = substr($email, strpos($email, '@')+1);

        // Check the user's email domain against our private lists.
        $domainList = array_merge($this->getBurnerDomains(), $this->getFreemailDomains());
        if (in_array($domain, $domainList)) {
            // Block the registration.
            $args['Valid'] = false;
            // Provide matching error as if a ban rule was invoked.
            $sender->Validation->addValidationResult('UserID', 'Sorry, permission denied.');
        }
    }
 }
