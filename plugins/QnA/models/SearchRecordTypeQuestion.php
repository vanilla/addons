<?php
/**
 * @author Alexander Kim <alexander.k@vanillaforums.com>
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

namespace Vanilla\QnA\Models;

use Vanilla\Contracts\Search\SearchRecordTypeInterface;

/**
 * Class SearchRecordTypeQuestion
 * @package Vanilla\QnA\Models
 */
class SearchRecordTypeQuestion implements SearchRecordTypeInterface {
    const PROVIDER_GROUP = 'sphinx';

    const TYPE = 'discussion';

    const CHECKBOX_ID = 'question';

    const CHECKBOX_LABEL = 'questions';

    /**
     * SearchRecordTypeQuestion constructor.
     */
    public function __construct() {
        $this->key = self::TYPE;
    }

    /**
     * @inheritdoc
     */
    public function getKey(): string {
        return $this->key;
    }

    /**
     * @inheritdoc
     */
    public function getCheckBoxId(): string {
        return self::TYPE.'_'.self::CHECKBOX_ID;
    }

    /**
     * @inheritdoc
     */
    public function getCheckBoxLabel(): string {
        return self::CHECKBOX_LABEL;
    }

    /**
     * @inheritdoc
     */
    public function getFeatures(): array {
        return $this->structure;
    }

    /**
     * @inheritdoc
     */
    public function getModel() {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getProviderGroup(): string {
        return self::PROVIDER_GROUP;
    }
}
