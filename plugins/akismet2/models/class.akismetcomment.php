<?php

/**
 * Akismet comment payload
 */
class AkismetComment {
    /** @var string The character encoding for the form values included in comment_* parameters, such as “UTF-8” or “ISO-8859-1”. */
    private $blog_charset;

    /** @var string Indicates the language(s) in use on the blog or site, in ISO 639-1 format, comma-separated. */
    private $blog_lang;

    /** @var string Name submitted with the comment. */
    private $comment_author;

    /** @var string Email address submitted with the comment. */
    private $comment_author_email;

    /** @var string URL submitted with comment. */
    private $comment_author_url;

    /** @var string The content that was submitted. */
    private $comment_content;

    /** @var string The UTC timestamp of the creation of the comment, in ISO 8601 format. */
    private $comment_date_gmt;

    /** @var string The UTC timestamp of the publication time for the post, page or thread on which the comment was posted. */
    private $comment_post_modified_gmt;

    /** @var string A string that describes the type of content being sent. */
    private $comment_type;

    /** @var string This is an optional parameter. You can use it when submitting test queries to Akismet. */
    private $is_test;

    /** @var string The full permanent URL of the entry the comment was submitted to. */
    private $permalink;

    /** @var string The content of the HTTP_REFERER header should be sent here. */
    private $referrer;

    /** @var string User agent string of the web browser submitting the comment. */
    private $user_agent;

    /** @var string IP address of the comment submitter. */
    private $user_ip;

    /** @var string The user role of the user who submitted the comment. This is an optional parameter. If you set it to “administrator”, Akismet will always return false. */
    private $user_role;

    /**
     * Get an API-ready array of fields.
     *
     * @return array
     */
    public function getComment() {
        $fields = get_object_vars($this);
        foreach ($fields as $name => $val) {
            if ($val === null) {
                unset($fields[$name]);
            }
        }
        return $fields;
    }

    /**
     * Set the site character set.
     *
     * @param string $blogCharset
     * @return $this
     */
    public function setBlogCharset($blogCharset) {
        $this->blog_charset = $blogCharset;
        return $this;
    }

    /**
     * Set the site language.
     *
     * @param string $blogLang
     * @return $this
     */
    public function setBlogLang($blogLang) {
        $this->blog_lang = $blogLang;
        return $this;
    }

    /**
     * Set the author's name.
     *
     * @param string $commentAuthor
     * @return $this
     */
    public function setCommentAuthor($commentAuthor) {
        $this->comment_author = $commentAuthor;
        return $this;
    }

    /**
     * Set the author's email address.
     *
     * @param string $commentAuthorEmail
     * @return $this
     */
    public function setCommentAuthorEmail($commentAuthorEmail) {
        $this->comment_author_email = $commentAuthorEmail;
        return $this;
    }

    /**
     * Set the author's URL, if available.
     *
     * @param string $commentAuthorUrl
     * @return $this
     */
    public function setCommentAuthorUrl($commentAuthorUrl) {
        $this->comment_author_url = $commentAuthorUrl;
        return $this;
    }

    /**
     * Set the post content.
     *
     * @param string $commentContent
     * @return $this
     */
    public function setCommentContent($commentContent) {
        $this->comment_content = $commentContent;
        return $this;
    }

    /**
     * Set the datetime the post was created (GMT).
     *
     * @param string $commentDateGMT
     * @return $this
     */
    public function setCommentDateGMT($commentDateGMT) {
        $this->comment_date_gmt = $commentDateGMT;
        return $this;
    }

    /**
     * Set the datetime the post was modified (GMT).
     * @param string $commentPostModifiedGMT
     * @return $this
     */
    public function setCommentPostModifiedGMT($commentPostModifiedGMT) {
        $this->comment_post_modified_gmt = $commentPostModifiedGMT;
        return $this;
    }

    /**
     * Set the comment type.
     *
     * @param string $type
     * @return $this
     */
    public function setCommentType($type) {
        $valid = [
            'blog-post', //A blog post.
            'comment', //A blog comment.
            'contact-form', //A contact form or feedback form submission.
            'forum-post', //A top-level forum post.
            'message', //A message sent between just a few users.
            'reply', //A reply to a top-level forum post.
            'signup' //A new user account.
        ];
        if (!in_array($type, $valid)) {
            throw new InvalidArgumentException('Invalid value for comment_type.');
        }

        $this->comment_type = $type;
        return $this;
    }

    /**
     * Set the "is test" flag on this comment.
     *
     * @param bool $isTest
     * @return $this
     */
    public function setIsTest($isTest) {
        $this->is_test = $isTest ? 'true' : 'false';
        return $this;
    }

    /**
     * Set the permalink to this post.
     *
     * @param string $permalink
     * @return $this
     */
    public function setPermalink($permalink) {
        $this->permalink = $permalink;
        return $this;
    }

    /**
     * Set the referrer that was sent with the request.
     *
     * @param string $referrer
     * @return $this
     */
    public function setReferrer($referrer) {
        $this->referrer = $referrer;
        return $this;
    }

    /**
     * Set the user agent that was sent with the request.
     *
     * @param string $userAgent
     * @return $this
     */
    public function setUserAgent($userAgent) {
        $this->user_agent = $userAgent;
        return $this;
    }

    /**
     * Set the IP of the author at the time they made this request.
     *
     * @param string $userIP
     * @return $this
     */
    public function setUserIP($userIP) {
        $this->user_ip = $userIP;
        return $this;
    }

    /**
     * Set the site role of the author.
     *
     * @param string $userRole
     * @return $this
     */
    public function setUserRole($userRole) {
        $this->user_role = $userRole;
        return $this;
    }
}
