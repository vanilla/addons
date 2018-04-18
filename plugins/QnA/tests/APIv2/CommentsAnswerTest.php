<?php
/**
 * @author Alexandre (DaazKu) Chouinard <alexandre.c@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license https://opensource.org/licenses/GPL-2.0 GPL-2.0
 */

namespace VanillaTests\APIv2;

require_once(__DIR__.'/QnaTestHelperTrait.php');

/**
 * Test managing answers with the /api/v2/comments endpoint.
 */
class CommentsAnswerTest extends AbstractAPIv2Test {

    use QnaTestHelperTrait;

    private static $category;

    /**
     * {@inheritdoc}
     */
    public static function setupBeforeClass() {
        self::$addons = ['vanilla', 'qna'];
        parent::setupBeforeClass();

        /**
         * @var \Gdn_Session $session
         */
        $session = self::container()->get(\Gdn_Session::class);
        $session->start(self::$siteInfo['adminUserID'], false, false);

        /** @var \CategoriesApiController $categoryAPIController */
        $categoryAPIController = static::container()->get('CategoriesApiController');

        self::$category = $categoryAPIController->post([
            'name' => 'answerTest',
            'urlcode' => 'answertest',
        ]);

        $session->end();
    }

    /**
     * Create a question.
     *
     * @return array The question.
     */
    protected function createQuestion() {
        $record = [
            'categoryID' => self::$category['categoryID'],
            'name' => 'Test Question For Answer',
            'body' => 'Hello world!',
            'format' => 'markdown',
        ];
        $response = $this->api()->post('discussions/question', $record);
        $this->assertEquals(201, $response->getStatusCode());

        return $response->getBody();
    }

    /**
     * Get a question.
     *
     * @param int $discussionID
     * @return array The question.
     */
    protected function getQuestion($discussionID) {
        $response = $this->api()->get("discussions/$discussionID");
        $this->assertEquals(200, $response->getStatusCode());

        return $response->getBody();
    }

    /**
     * Test answer creation.
     *
     * @param int $discussionID If omitted the answer will be created on a new Question.
     * @return mixed
     */
    public function testPostAnswer($discussionID = null) {
        if ($discussionID === null) {
            $question = $this->createQuestion();
            $discussionID = $question['discussionID'];
        }

        $record = [
            'discussionID' => $discussionID,
            'body' => 'Hello world!',
            'format' => 'markdown',
        ];
        $response = $this->api()->post('comments', $record);
        $this->assertEquals(201, $response->getStatusCode());

        $body = $response->getBody();

        $this->assertTrue(is_int($body['commentID']));
        $this->assertTrue($body['commentID'] > 0);

        $this->assertRowsEqual($record, $body);

        $this->assertIsAnswer($body);

        $question = $this->getQuestion($discussionID);
        $this->assertIsQuestion($question, ['status' => 'answered']);

        return $body;
    }

    /**
     * Test getting an answer.
     *
     * @depends testPostAnswer
     */
    public function testGetAnswer() {
        $answer = $this->testPostAnswer();
        $commentID = $answer['commentID'];

        $response = $this->api()->get("comments/{$commentID}");
        $this->assertEquals(200, $response->getStatusCode());

        $body = $response->getBody();
        $this->assertIsAnswer($body, ['status' => 'pending']);
    }

    /**
     * Test accepting an answer.
     *
     * @depends testPostAnswer
     */
    public function testAcceptAnswer() {
        $question = $this->createQuestion();
        $answer = $this->testPostAnswer($question['discussionID']);

        $response = $this->api()->patch('comments/answer/'.$answer['commentID'], [
            'status' => 'accepted',
        ]);
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertIsAnswer($response->getBody(), ['status' => 'accepted']);

        $updatedQuestion = $this->getQuestion($question['discussionID']);
        $this->assertIsQuestion($updatedQuestion, ['status' => 'accepted']);
    }

    /**
     * Test rejecting an answer.
     *
     * @depends testPostAnswer
     */
    public function testRejectAnswer() {
        $question = $this->createQuestion();
        $answer = $this->testPostAnswer($question['discussionID']);

        $response = $this->api()->patch('comments/answer/'.$answer['commentID'], [
            'status' => 'rejected',
        ]);
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertIsAnswer($response->getBody(), ['status' => 'rejected']);

        $updatedQuestion = $this->getQuestion($question['discussionID']);
        $this->assertIsQuestion($updatedQuestion, ['status' => 'rejected']);
    }

    /**
     * Test accepting and then setting back an answer to pending.
     *
     * @depends testPostAnswer
     */
    public function testResetAnswer() {
        $question = $this->createQuestion();
        $answer = $this->testPostAnswer($question['discussionID']);

        $response = $this->api()->patch('comments/answer/'.$answer['commentID'], [
            'status' => 'accepted',
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $response = $this->api()->patch('comments/answer/'.$answer['commentID'], [
            'status' => 'pending',
        ]);
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertIsAnswer($response->getBody(), ['status' => 'pending']);

        $updatedQuestion = $this->getQuestion($question['discussionID']);
        $this->assertIsQuestion($updatedQuestion, ['status' => 'answered']);
    }

    /**
     * Test accepting and then rejecting an answer.
     *
     * @depends testPostAnswer
     */
    public function testAcceptRejectAnswer() {
        $question = $this->createQuestion();
        $answer = $this->testPostAnswer($question['discussionID']);

        $response = $this->api()->patch('comments/answer/'.$answer['commentID'], [
            'status' => 'accepted',
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $response = $this->api()->patch('comments/answer/'.$answer['commentID'], [
            'status' => 'rejected',
        ]);
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertIsAnswer($response->getBody(), ['status' => 'rejected']);

        $updatedQuestion = $this->getQuestion($question['discussionID']);
        $this->assertIsQuestion($updatedQuestion, ['status' => 'rejected']);
    }
}
