<?php

use Garden\Http\HttpClient;

class AkismetAPI extends HttpClient {

    /** Target version of the Akismet API */
    const API_VERSION = '1.1';

    /** @var string */
    private $key;

    /** @var string */
    private $blog;

    /** @var bool */
    private $includeServer = false;

    /**
     * AkismetAPI constructor.
     *
     * @param string|null $blog
     * @param string|null $key
     */
    public function __construct($blog = null, $key = null) {
        if ($blog !== null) {
            $this->setBlog($blog);
        }        if ($key !== null) {
            $this->setKey($key);
        }
        parent::__construct();
    }

    /**
     * Build a comment payload.
     *
     * @param AkismetComment $comment
     * @param bool $includeServer
     * @return array
     */
    private function buildRequestComment(AkismetComment $comment, $includeServer = false) {
        $result = ['blog' => $this->getBlog()];
        $result += $comment->getComment();

        if ($includeServer) {
            $result += $this->getServerVars();
        }

        return $result;
    }

    /**
     * Check if comment is SPAM.
     *
     * @param AkismetComment $comment
     * @return bool
     */
    public function commentCheck(AkismetComment $comment) {
        $url = $this->getUrl('comment-check');
        $body = $this->buildRequestComment($comment, $this->getIncludeServer());
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
     * Get whether or not server vars should be included in comment requests.
     *
     * @return bool
     */
    public function getIncludeServer() {
        return $this->includeServer;
    }

    /**
     * Get values from $_SERVER global to include in comment requests.
     *
     * @return array
     */
    private function getServerVars() {
        $result = [];
        $additional = ['REMOTE_ADDR', 'REQUEST_URI', 'DOCUMENT_URI'];

        foreach ($_SERVER as $key => $val) {
            if (!is_string($val)) {
                continue;
            }
            if (substr($key, 0, 11) === 'HTTP_COOKIE') {
                continue;
            }
            if (substr($key, 0, 5) !== 'HTTP_' || in_array($key, $additional)) {
                continue;
            }
            $result[$key] = $val;
        }

        return $result;
    }

    /**
     * Get an API URL.
     *
     * @param string $path
     * @param bool $useKeyDomain
     * @return string
     */
    private function getUrl($path, $useKeyDomain = true) {
        $domain = 'rest.akismet.com';

        if ($useKeyDomain) {
            $key = $this->getKey();
            if (!$key) {
                throw new Exception('key not set.');
            }
            $domain = "{$key}.{$domain}";
        }

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
     * Set whether or not server vars should be included in comment requests.
     *
     * @param $includeServer
     * @return $this
     */
    public function setIncludeServer($includeServer) {
        $this->includeServer = (bool)$includeServer;
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
        $body = $this->buildRequestComment($comment);
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
        $url = $this->getUrl('verify-key', false);
        $response = $this->post($url, [
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
