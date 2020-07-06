<?php
/**
 * @copyright 2009-2020 Vanilla Forums Inc.
 * @license Proprietary
 */

namespace Vanilla\QnA\Models;

use Garden\Web\Exception\HttpException;
use Vanilla\Forum\Navigation\ForumCategoryRecordType;
use Vanilla\Forum\Search\DiscussionSearchType;
use Vanilla\Navigation\BreadcrumbModel;
use Vanilla\Search\SearchQuery;
use Vanilla\Search\SearchResultItem;
use Vanilla\Utility\ArrayUtils;

/**
 * Search record type for a questions
 */
class QuestionSearchType extends DiscussionSearchType {

    const TYPE_QUESTION = 1;

    /** @var \DiscussionsApiController */
    protected $discussionsApi;

    /** @var BreadcrumbModel */
    protected $breadcrumbModel;

    /**
     * DI.
     *
     * @param \DiscussionsApiController $discussionsApi
     * @param \CategoryModel $categoryModel
     * @param \UserModel $userModel
     * @param \TagModel $tagModel
     * @param BreadcrumbModel $breadcrumbModel
     */
    public function __construct(
        \DiscussionsApiController $discussionsApi,
        \CategoryModel $categoryModel,
        \UserModel $userModel,
        \TagModel $tagModel,
        BreadcrumbModel $breadcrumbModel
    ) {
        parent::__construct(
            $discussionsApi,
            $categoryModel,
            $userModel,
            $tagModel,
            $breadcrumbModel
        );
    }

    /**
     * @inheritdoc
     */
    public function getKey(): string {
        return 'question';
    }

    /**
     * @inheritdoc
     */
    public function getSearchGroup(): string {
        return 'discussion';
    }

    /**
     * @inheritdoc
     */
    public function getType(): string {
        return 'question';
    }

    /**
     * @inheritdoc
     */
    public function getResultItems(array $recordIDs): array {
        try {
            $results = $this->discussionsApi->index([
                'discussionID' => implode(",", $recordIDs),
                'limit' => 100,
            ]);
            $results = $results->getData();

            $resultItems = array_map(function ($result) {
                $mapped = ArrayUtils::remapProperties($result, [
                    'recordID' => 'discussionID',
                ]);
                $mapped['recordType'] = $this->getSearchGroup();
                $mapped['type'] = $this->getType();
                $mapped['breadcrumbs'] = $this->breadcrumbModel->getForRecord(new ForumCategoryRecordType($mapped['categoryID']));
                return new SearchResultItem($mapped);
            }, $results);
            return $resultItems;
        } catch (HttpException $exception) {
            trigger_error($exception->getMessage(), E_USER_WARNING);
            return [];
        }
    }
}
