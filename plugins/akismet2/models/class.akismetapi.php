<?php

use Garden\Http\HttpClient;

class AkismetAPI extends HttpClient {

    /** Target version of the Akismet API */
    const API_VERSION = '1.1';

    /** @var string */
    private $key;

    /** @var string */
    private $blog;

    /**
     * AkismetAPI constructor.
     *
     * @param string|null $key
     * @param string|null $blog
     */
    public function __construct($key = null, $blog = null) {
        if ($key !== null) {
            $this->setKey($key);
        }
        if ($blog !== null) {
            $this->setBlog($blog);
        }
        parent::__construct();
    }

    /**
     * Check if comment is SPAM.
     *
     * @param AkismetComment $comment
     * @return bool
     */
    public function commentCheck(AkismetComment $comment) {
        $url = $this->getUrl('comment-check');
        $body = ['blog' => $this->getBlog()];
        $body += $comment->getComment();
        $response = $this->post($url, $body);

        $result = false;
        if ($response->getStatusCode() === 200) {
            $result = ($response->getRawBody() === 'true');
        }
        return $result;
    }

    /**
     * Get site home URL.
     *
     * @return string|null
     */
    public function getBlog() {
        return $this->blog;
    }

    /**
     * Get an API URL.
     *
     * @param string $path
     * @return string
     */
    private function getUrl($path) {
        $key = $this->getKey();
        if (!$key) {
            throw new Exception('key not set.');
        }

        $domain = "{$key}.rest.akismet.com";
        $result = "https://{$domain}/".self::API_VERSION."/{$path}";
        return $result;
    }

    /**
     * Get Akismet API key.
     *
     * @return string|null
     */
    public function getKey() {
        return $this->key;
    }

    /**
     * Set site home URL.
     *
     * @param string $blog
     * @return $this
     */
    public function setBlog($blog) {
        $blog = filter_var($blog, FILTER_VALIDATE_URL);
        if (!$blog) {
            throw new InvalidArgumentException('blog is not a valid URL.');
        }
        $this->blog = $blog;
        return $this;
    }

    /**
     * Set Akismet API key.
     *
     * @param string $key
     * @return $this
     */
    public function setKey($key) {
        $this->key = $key;
        if (!preg_match('/[A-Za-z0-9]{12}/', $key)) {
            throw new InvalidArgumentException('key is not a valid API key.');
        }
        return $this;
    }

    /**
     * Submit a correction to Akismet.
     *
     * @param AkismetComment $comment
     * @return bool
     */
    private function submitCorrection(AkismetComment $comment, $actualType) {
        $typeEndpoints = [
            'ham' => 'submit-ham',
            'spam' => 'submit-spam'
        ];

        if (!array_key_exists($actualType, $typeEndpoints)) {
            throw new InvalidArgumentException('Invalid correction type.');
        }

        $endpoint = $typeEndpoints[$actualType];
        $url = $this->getUrl($endpoint);
        $body = ['blog' => $this->getBlog()];
        $body += $comment->getComment();
        $response = $this->post($url, $body);

        $result = ($response->getStatusCode() === 200);
        return $result;
    }

    /**
     * Submit a comment as a false-positive.
     *
     * @param AkismetComment $comment
     * @return bool
     */
    public function submitHam(AkismetComment $comment) {
        $result = $this->submitCorrection($comment, 'ham');
        return $result;
    }

    /**
     * Submit a comment as being SPAM.
     *
     * @param AkismetComment $comment
     * @return bool
     */
    public function submitSpam(AkismetComment $comment) {
        $result = $this->submitCorrection($comment, 'spam');
        return $result;
    }

    /**
     * Verify an Akismet API key is valid.
     *
     * @param string $key
     * @return bool
     */
    public function verifyKey($key) {
        $blog = $this->getBlog();
        if (!$blog) {
            throw new Exception('blog must be set to verify key.');
        }
        $response = $this->post('https://rest.akismet.com/'.self::API_VERSION.'/verify-key', [
            'key' => $key,
            'blog' => $blog
        ]);

        $result = false;
        if ($response->getStatusCode() === 200) {
            $result = ($response->getRawBody() === 'valid');
        }
        return $result;
    }
}
