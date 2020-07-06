<?php
/**
 * @copyright 2009-2020 Vanilla Forums Inc.
 * @license Proprietary
 */

namespace Vanilla\QnA\Models;

use Garden\Web\Exception\HttpException;
use Vanilla\Forum\Navigation\ForumCategoryRecordType;
use Vanilla\Forum\Search\CommentSearchType;
use Vanilla\Navigation\BreadcrumbModel;
use Vanilla\Search\SearchQuery;
use Vanilla\Search\SearchResultItem;
use Vanilla\Utility\ArrayUtils;

/**
 * Search record type for a questions
 */
class AnswerSearchType extends CommentSearchType {
    const TYPE_ANSWER = 101;

    /** @var \CommentsApiController */
    private $commentsApi;

    /** @var \CommentModel */
    private $commentModel;

    /**
     * @inheritdoc
     */
    public function __construct(
        \CommentsApiController $commentsApi,
        \CommentModel $commentModel,
        \DiscussionsApiController $discussionsApi,
        \CategoryModel $categoryModel,
        \UserModel $userModel,
        \TagModel $tagModel,
        BreadcrumbModel $breadcrumbModel
    ) {
        parent::__construct(
            $commentsApi,
            $commentModel,
            $discussionsApi,
            $categoryModel,
            $userModel,
            $tagModel,
            $breadcrumbModel
        );

        $this->commentsApi = $commentsApi;
        $this->commentModel = $commentModel;
    }


    /**
     * @inheritdoc
     */
    public function getKey(): string {
        return 'answer';
    }

    /**
     * @inheritdoc
     */
    public function getSearchGroup(): string {
        return 'comment';
    }

    /**
     * @inheritdoc
     */
    public function getType(): string {
        return 'answer';
    }

    /**
     * @inheritdoc
     */
    public function getResultItems(array $recordIDs): array {
        try {
            $results = $this->commentsApi->index([
                'commentID' => implode(",", $recordIDs),
                'limit' => 100,
            ]);
            $results = $results->getData();

            $resultItems = array_map(function ($result) {
                $mapped = ArrayUtils::remapProperties($result, [
                    'recordID' => 'commentID',
                ]);
                $mapped['recordType'] = $this->getSearchGroup();
                $mapped['type'] = $this->getType();
                $mapped['breadcrumbs'] = $this->breadcrumbModel->getForRecord(
                    new ForumCategoryRecordType($mapped['categoryID'])
                );
                return new SearchResultItem($mapped);
            }, $results);
            return $resultItems;
        } catch (HttpException $exception) {
            trigger_error($exception->getMessage(), E_USER_WARNING);
            return [];
        }
    }
}
