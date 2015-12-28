<?php
/**
 * Parses and verifies the doc comments for functions.
 *
 * PHP version 5
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @copyright 2006-2012 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */

if (class_exists('PHP_CodeSniffer_CommentParser_FunctionCommentParser', true) === false) {
    $error = 'Class PHP_CodeSniffer_CommentParser_FunctionCommentParser not found';
    throw new PHP_CodeSniffer_Exception($error);
}

/**
 * Parses and verifies the doc comments for functions.
 *
 * Verifies that :
 * <ul>
 *  <li>A comment exists</li>
 *  <li>There is a blank newline after the short description</li>
 *  <li>There is a blank newline between the long and short description</li>
 *  <li>There is a blank newline between the long description and tags</li>
 *  <li>Parameter names represent those in the method</li>
 *  <li>Parameter comments are in the correct order</li>
 *  <li>Parameter comments are complete</li>
 *  <li>A type hint is provided for array and custom class</li>
 *  <li>Type hint matches the actual variable/class type</li>
 *  <li>A blank line is present before the first and after the last parameter</li>
 *  <li>Any throw tag must have a comment</li>
 *  <li>The tag order and indentation are correct</li>
 * </ul>
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @copyright 2006-2012 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 * @version   Release: @package_version@
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */
class Vanilla_Sniffs_Commenting_FunctionCommentSniff implements PHP_CodeSniffer_Sniff
{

    /**
     * The name of the method that we are currently processing.
     *
     * @var string
     */
    private $_methodName = '';

    /**
     * The position in the stack where the function token was found.
     *
     * @var int
     */
    private $_functionToken = null;

    /**
     * The position in the stack where the class token was found.
     *
     * @var int
     */
    private $_classToken = null;

    /**
     * The index of the current tag we are processing.
     *
     * @var int
     */
    private $_tagIndex = 0;

    /**
     * The function comment parser for the current method.
     *
     * @var PHP_CodeSniffer_Comment_Parser_FunctionCommentParser
     */
    protected $commentParser = null;

    /**
     * The current PHP_CodeSniffer_File object we are processing.
     *
     * @var PHP_CodeSniffer_File
     */
    protected $currentFile = null;


    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return array(T_FUNCTION);

    }//end register()


    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
     * @param int                  $stackPtr  The position of the current token
     *                                        in the stack passed in $tokens.
     *
     * @return void
     */
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $this->currentFile = $phpcsFile;

        $tokens = $phpcsFile->getTokens();

        $find = array(
                 T_COMMENT,
                 T_DOC_COMMENT,
                 T_CLASS,
                 T_FUNCTION,
                 T_OPEN_TAG,
                );

        $commentEnd = $phpcsFile->findPrevious($find, ($stackPtr - 1));

        if ($commentEnd === false) {
            return;
        }

        // If the token that we found was a class or a function, then this
        // function has no doc comment.
        $code = $tokens[$commentEnd]['code'];

        if ($code === T_COMMENT) {
            // The function might actually be missing a comment, and this last comment
            // found is just commenting a bit of code on a line. So if it is not the
            // only thing on the line, assume we found nothing.
            $prevContent = $phpcsFile->findPrevious(PHP_CodeSniffer_Tokens::$emptyTokens, $commentEnd);
            if ($tokens[$commentEnd]['line'] === $tokens[$commentEnd]['line']) {
                $error = 'Missing function doc comment';
                $phpcsFile->addError($error, $stackPtr, 'Missing');
            } else {
                $error = 'You must use "/**" style comments for a function comment';
                $phpcsFile->addError($error, $stackPtr, 'WrongStyle');
            }
            return;
        } else if ($code !== T_DOC_COMMENT) {
            $error = 'Missing function doc comment';
            $phpcsFile->addError($error, $stackPtr, 'Missing');
            return;
        } else if (trim($tokens[$commentEnd]['content']) !== '*/') {
            $error = 'You must use "*/" to end a function comment; found "%s"';
            $phpcsFile->addError($error, $commentEnd, 'WrongEnd', array(trim($tokens[$commentEnd]['content'])));
            return;
        }

        // If there is any code between the function keyword and the doc block
        // then the doc block is not for us.
        $ignore    = PHP_CodeSniffer_Tokens::$scopeModifiers;
        $ignore[]  = T_STATIC;
        $ignore[]  = T_WHITESPACE;
        $ignore[]  = T_ABSTRACT;
        $ignore[]  = T_FINAL;
        $prevToken = $phpcsFile->findPrevious($ignore, ($stackPtr - 1), null, true);
        if ($prevToken !== $commentEnd) {
            $phpcsFile->addError('Missing function doc comment', $stackPtr, 'Missing');
            return;
        }

        $this->_functionToken = $stackPtr;

        $this->_classToken = null;
        foreach ($tokens[$stackPtr]['conditions'] as $condPtr => $condition) {
            if ($condition === T_CLASS || $condition === T_INTERFACE) {
                $this->_classToken = $condPtr;
                break;
            }
        }

        // Find the first doc comment.
        $commentStart      = ($phpcsFile->findPrevious(T_DOC_COMMENT, ($commentEnd - 1), null, true) + 1);
        $commentString     = $phpcsFile->getTokensAsString($commentStart, ($commentEnd - $commentStart + 1));
        $this->_methodName = $phpcsFile->getDeclarationName($stackPtr);

        try {
            $this->commentParser = new PHP_CodeSniffer_CommentParser_FunctionCommentParser($commentString, $phpcsFile);
            $this->commentParser->parse();
        } catch (PHP_CodeSniffer_CommentParser_ParserException $e) {
            $line = ($e->getLineWithinComment() + $commentStart);
            $phpcsFile->addError($e->getMessage(), $line, 'FailedParse');
            return;
        }

        $comment = $this->commentParser->getComment();
        if (is_null($comment) === true) {
            $error = 'Function doc comment is empty';
            $phpcsFile->addError($error, $commentStart, 'Empty');
            return;
        }

        // The first line of the comment should just be the /** code.
        $eolPos    = strpos($commentString, $phpcsFile->eolChar);
        $firstLine = substr($commentString, 0, $eolPos);
        if ($firstLine !== '/**') {
            $error = 'The open comment tag must be the only content on the line';
            $phpcsFile->addError($error, $commentStart, 'ContentAfterOpen');
        }

        // Check for a comment description.
        $short = $comment->getShortComment();
        //ignore bocks with inheritdoc tags
        if (stristr($short, '{@inheritdoc}') !== false) {
            return;
        }

        $this->processParams($commentStart, $commentEnd);
        $this->processSees($commentStart);
        $this->processThrows($commentStart);

        // Check for a comment description.
        $short = $comment->getShortComment();
        if (trim($short) === '') {
            $error = 'Missing short description in function doc comment';
            $phpcsFile->addError($error, $commentStart, 'MissingShort');
            return;
        }

        //ignore bocks with inheritdoc tags
        if (stristr($short, '{@inheritdoc}') !== false) {
            return;
        }

        // No extra newline before short description.
        $newlineCount = 0;
        $newlineSpan  = strspn($short, $phpcsFile->eolChar);
        if ($short !== '' && $newlineSpan > 0) {
            $error = 'Extra newline(s) found before function comment short description';
            $phpcsFile->addError($error, ($commentStart + 1), 'SpacingBeforeShort');
        }

        $newlineCount = (substr_count($short, $phpcsFile->eolChar) + 1);

        // Exactly one blank line between short and long description.
        $long = $comment->getLongComment();
        if (empty($long) === false) {
            $between        = $comment->getWhiteSpaceBetween();
            $newlineBetween = substr_count($between, $phpcsFile->eolChar);
            if ($newlineBetween !== 2) {
                $error = 'There must be exactly one blank line between descriptions in function comment';
                $phpcsFile->addError($error, ($commentStart + $newlineCount + 1), 'SpacingBetween');
            }

            $newlineCount += $newlineBetween;

            $testLong = trim($long);
            if (preg_match('|\p{Lu}|u', $testLong[0]) === 0) {
                $error = 'Function comment long description must start with a capital letter';
                $phpcsFile->addError($error, ($commentStart + $newlineCount), 'LongNotCapital');
            }
        }

        //ignore bocks with inheritdoc tags
        if (stristr($long, '{inheritdoc}') !== false) {
            return;
        }

        // Exactly one blank line before tags.
        $params = $this->commentParser->getTagOrders();
        if (count($params) > 1) {
            $newlineSpan = $comment->getNewlineAfter();
            if ($newlineSpan !== 2) {
                $error = 'There must be exactly one blank line before the tags in function comment';
                if ($long !== '') {
                    $newlineCount += (substr_count($long, $phpcsFile->eolChar) - $newlineSpan + 1);
                }

                $phpcsFile->addError($error, ($commentStart + $newlineCount), 'SpacingBeforeTags');
                $short = rtrim($short, $phpcsFile->eolChar.' ');
            }
        }

        // Short description must be single line and end with a full stop.
        $testShort = trim($short);
        $lastChar  = $testShort[(strlen($testShort) - 1)];
        if (substr_count($testShort, $phpcsFile->eolChar) !== 0) {
            $error = 'Function comment short description must be on a single line';
            $phpcsFile->addError($error, ($commentStart + 1), 'ShortSingleLine');
        }

        if (preg_match('|\p{Lu}|u', $testShort[0]) === 0) {
            $error = 'Function comment short description must start with a capital letter';
            $phpcsFile->addError($error, ($commentStart + 1), 'ShortNotCapital');
        }

        if (!in_array($lastChar,  ['.', '?', '!'])) {
            $error = 'Function comment short descriptions must end with punctuation';
            $phpcsFile->addError($error, ($commentStart + 1), 'ShortPunctuation');
        }

        // Check for unknown/deprecated tags.
        $this->processUnknownTags($commentStart, $commentEnd);

        // The last content should be a newline and the content before
        // that should not be blank. If there is more blank space
        // then they have additional blank lines at the end of the comment.
        $words   = $this->commentParser->getWords();
        $lastPos = (count($words) - 1);
        if (trim($words[($lastPos - 1)]) !== ''
            || strpos($words[($lastPos - 1)], $this->currentFile->eolChar) === false
            || trim($words[($lastPos - 2)]) === ''
        ) {
            $error = 'Additional blank lines found at end of function comment';
            $this->currentFile->addError($error, $commentEnd, 'SpacingAfter');
        }

    }//end process()


    /**
     * Process the see tags.
     *
     * @param int $commentStart The position in the stack where the comment started.
     *
     * @return void
     */
    protected function processSees($commentStart)
    {
        $sees = $this->commentParser->getSees();
        if (empty($sees) === false) {
            $tagOrder = $this->commentParser->getTagOrders();
            $index    = array_keys($this->commentParser->getTagOrders(), 'see');
            foreach ($sees as $i => $see) {
                $errorPos = ($commentStart + $see->getLine());
                $since    = array_keys($tagOrder, 'since');
                if (count($since) === 1 && $this->_tagIndex !== 0) {
                    $this->_tagIndex++;
                    if ($index[$i] !== $this->_tagIndex) {
                        $error = 'The @see tag is in the wrong order; the tag precedes @return';
                        $this->currentFile->addError($error, $errorPos, 'SeeOrder');
                    }
                }

                $content = $see->getContent();
                if (empty($content) === true) {
                    $error = 'Content missing for @see tag in function comment';
                    $this->currentFile->addError($error, $errorPos, 'EmptySee');
                    continue;
                }


            }//end foreach
        }//end if

    }//end processSees()



    /**
     * Process any throw tags that this function comment has.
     *
     * @param int $commentStart The position in the stack where the comment started.
     *
     * @return void
     */
    protected function processThrows($commentStart)
    {
        if (count($this->commentParser->getThrows()) === 0) {
            return;
        }

        $tagOrder = $this->commentParser->getTagOrders();
        $index    = array_keys($this->commentParser->getTagOrders(), 'throws');

        foreach ($this->commentParser->getThrows() as $i => $throw) {
            $exception = $throw->getValue();
            $content   = trim($throw->getComment());
            $errorPos  = ($commentStart + $throw->getLine());
            if (empty($exception) === true) {
                $error = 'Exception type and comment missing for @throws tag in function comment';
                $this->currentFile->addError($error, $errorPos, 'InvalidThrows');
            } else if (empty($content) === true) {
                $error = 'Comment missing for @throws tag in function comment';
                $this->currentFile->addError($error, $errorPos, 'EmptyThrows');
            } else {
                // Starts with a capital letter and ends with a fullstop.
                $firstChar = $content{0};
                if (strtoupper($firstChar) !== $firstChar) {
                    $error = '@throws tag comment must start with a capital letter';
                    $this->currentFile->addError($error, $errorPos, 'ThrowsNotCapital');
                }

                $lastChar = $content[(strlen($content) - 1)];
                if ($lastChar !== '.') {
                    $error = '@throws tag comment must end with a full stop';
                    $this->currentFile->addError($error, $errorPos, 'ThrowsNoFullStop');
                }
            }

            $since = array_keys($tagOrder, 'since');
            if (count($since) === 1 && $this->_tagIndex !== 0) {
                $this->_tagIndex++;
                if ($index[$i] !== $this->_tagIndex) {
                    $error = 'The @throws tag is in the wrong order; the tag follows @return';
                    $this->currentFile->addError($error, $errorPos, 'ThrowsOrder');
                }
            }
        }//end foreach

    }//end processThrows()


    /**
     * Process the function parameter comments.
     *
     * @param int $commentStart The position in the stack where
     *                          the comment started.
     * @param int $commentEnd   The position in the stack where
     *                          the comment ended.
     *
     * @return void
     */
    protected function processParams($commentStart, $commentEnd)
    {
        $realParams  = $this->currentFile->getMethodParameters($this->_functionToken);
        $params      = $this->commentParser->getParams();
        $foundParams = array();

        if (empty($params) === false) {

            // if (substr_count($params[(count($params) - 1)]->getWhitespaceAfter(), $this->currentFile->eolChar) !== 2) {
            //     $error    = 'Last parameter comment requires a blank newline after it';
            //     $errorPos = ($params[(count($params) - 1)]->getLine() + $commentStart);
            //     $this->currentFile->addError($error, $errorPos, 'SpacingAfterParams');
            // }

            // Parameters must appear immediately after the comment.
            if ($params[0]->getOrder() !== 2) {
                $error    = 'Parameters must appear immediately after the comment';
                $errorPos = ($params[0]->getLine() + $commentStart);
                $this->currentFile->addError($error, $errorPos, 'SpacingBeforeParams');
            }

            $previousParam      = null;
            $spaceBeforeVar     = 10000;
            $spaceBeforeComment = 10000;
            $longestType        = 0;
            $longestVar         = 0;

            foreach ($params as $param) {

                $paramComment = trim($param->getComment());
                $errorPos     = ($param->getLine() + $commentStart);

                // Make sure that there is only one space before the var type.
                if ($param->getWhitespaceBeforeType() !== ' ') {
                    $error = 'Expected 1 space before variable type';
                    $this->currentFile->addError($error, $errorPos, 'SpacingBeforeParamType');
                }

                $spaceCount = substr_count($param->getWhitespaceBeforeVarName(), ' ');
                if ($spaceCount < $spaceBeforeVar) {
                    $spaceBeforeVar = $spaceCount;
                    $longestType    = $errorPos;
                }

                $spaceCount = substr_count($param->getWhitespaceBeforeComment(), ' ');

                if ($spaceCount < $spaceBeforeComment && $paramComment !== '') {
                    $spaceBeforeComment = $spaceCount;
                    $longestVar         = $errorPos;
                }

                // Make sure they are in the correct order, and have the correct name.
                $pos       = $param->getPosition();
                $paramName = ($param->getVarName() !== '') ? $param->getVarName() : '[ UNKNOWN ]';

//                if ($previousParam !== null) {
//                    $previousName = ($previousParam->getVarName() !== '') ? $previousParam->getVarName() : 'UNKNOWN';
//
////                    // Check to see if the parameters align properly.
////                    if ($param->alignsVariableWith($previousParam) === false) {
////                        $error = 'The variable names for parameters %s (%s) and %s (%s) do not align';
////                        $data  = array(
////                                  $previousName,
////                                  ($pos - 1),
////                                  $paramName,
////                                  $pos,
////                                 );
////                        $this->currentFile->addError($error, $errorPos, 'ParameterNamesNotAligned', $data);
////                    }
////
////                    if ($param->alignsCommentWith($previousParam) === false) {
////                        $error = 'The comments for parameters %s (%s) and %s (%s) do not align';
////                        $data  = array(
////                                  $previousName,
////                                  ($pos - 1),
////                                  $paramName,
////                                  $pos,
////                                 );
////                        $this->currentFile->addError($error, $errorPos, 'ParameterCommentsNotAligned', $data);
////                    }
//                }

//                // Variable must be one of the supported standard type.
//                $typeNames = explode('|', $param->getType());
//                foreach ($typeNames as $typeName) {
//                    $suggestedName = PHP_CodeSniffer::suggestType($typeName);
//                    if ($typeName !== $suggestedName) {
//                        $error = 'Expected "%s"; found "%s" for %s at position %s';
//                        $data  = array(
//                                  $suggestedName,
//                                  $typeName,
//                                  $paramName,
//                                  $pos,
//                                 );
//                        $this->currentFile->addError($error, $errorPos, 'IncorrectParamVarName', $data);
//                    } else if (count($typeNames) === 1) {
//                        // Check type hint for array and custom type.
//                        $suggestedTypeHint = '';
//                        if (strpos($suggestedName, 'array') !== false) {
//                            $suggestedTypeHint = 'array';
//                        } else if (strpos($suggestedName, 'callable') !== false) {
//                            $suggestedTypeHint = 'callable';
//                        } else if (in_array($typeName, PHP_CodeSniffer::$allowedTypes) === false) {
//                            $suggestedTypeHint = $suggestedName;
//                        }
//
//                        if ($suggestedTypeHint !== '' && isset($realParams[($pos - 1)]) === true) {
//                            $typeHint = $realParams[($pos - 1)]['type_hint'];
//                            if ($typeHint === '') {
//                                $error = 'Type hint "%s" missing for %s at position %s';
//                                $data  = array(
//                                          $suggestedTypeHint,
//                                          $paramName,
//                                          $pos,
//                                         );
//                                $this->currentFile->addError($error, ($commentEnd + 2), 'TypeHintMissing', $data);
//                            } else if ($typeHint !== $suggestedTypeHint) {
//                                $error = 'Expected type hint "%s"; found "%s" for %s at position %s';
//                                $data  = array(
//                                          $suggestedTypeHint,
//                                          $typeHint,
//                                          $paramName,
//                                          $pos,
//                                         );
//                                $this->currentFile->addError($error, ($commentEnd + 2), 'IncorrectTypeHint', $data);
//                            }
//                        } else if ($suggestedTypeHint === '' && isset($realParams[($pos - 1)]) === true) {
//                            $typeHint = $realParams[($pos - 1)]['type_hint'];
//                            if ($typeHint !== '') {
//                                $error = 'Unknown type hint "%s" found for %s at position %s';
//                                $data  = array(
//                                          $typeHint,
//                                          $paramName,
//                                          $pos,
//                                         );
//                                $this->currentFile->addError($error, ($commentEnd + 2), 'InvalidTypeHint', $data);
//                            }
//                        }
//                    }//end if
//                }//end foreach

                // Make sure the names of the parameter comment matches the
                // actual parameter.
                if (isset($realParams[($pos - 1)]) === true) {
                    $realName      = $realParams[($pos - 1)]['name'];
                    $foundParams[] = $realName;

                    // Append ampersand to name if passing by reference.
                    if ($realParams[($pos - 1)]['pass_by_reference'] === true) {
                        $realName = '&'.$realName;
                    }

                    if ($realName !== $paramName) {
                        $code = 'ParamNameNoMatch';
                        $data = array(
                                 $paramName,
                                 $realName,
                                 $pos,
                                );

                        $error  = 'Doc comment for var %s does not match ';
                        if (strtolower($paramName) === strtolower($realName)) {
                            $error .= 'case of ';
                            $code   = 'ParamNameNoCaseMatch';
                        }

                        $error .= 'actual variable name %s at position %s';

                        $this->currentFile->addError($error, $errorPos, $code, $data);
                    }
                } else if (substr($paramName, -4) !== ',...') {
                    // We must have an extra parameter comment.
                    $error = 'Superfluous doc comment at position '.$pos;
                    $this->currentFile->addError($error, $errorPos, 'ExtraParamComment');
                }

                if ($param->getVarName() === '') {
                    $error = 'Missing parameter name at position '.$pos;
                     $this->currentFile->addError($error, $errorPos, 'MissingParamName');
                }

                if ($param->getType() === '') {
                    $error = 'Missing type at position '.$pos;
                    $this->currentFile->addError($error, $errorPos, 'MissingParamType');
                }

                if ($paramComment === '') {
                    $error = 'Missing comment for param "%s" at position %s';
                    $data  = array(
                              $paramName,
                              $pos,
                             );
                    $this->currentFile->addError($error, $errorPos, 'MissingParamComment', $data);
                } else {
                    // Param comments must start with a capital letter and
                    // end with the full stop.
                    $firstChar = $paramComment{0};
                    if (preg_match('|\p{Lu}|u', $firstChar) === 0) {
                        $error = 'Param comment must start with a capital letter';
                        $this->currentFile->addError($error, $errorPos, 'ParamCommentNotCapital');
                    }

                    $lastChar = $paramComment[(strlen($paramComment) - 1)];
                    if (!in_array($lastChar, ['.', '?', '!'])) {
                        $error = 'Param comment must end with punctuation';
                        $this->currentFile->addError($error, $errorPos, 'ParamCommentPunctuation');
                    }
                }

                $previousParam = $param;

            }//end foreach

            if ($spaceBeforeVar !== 1 && $spaceBeforeVar !== 10000 && $spaceBeforeComment !== 10000) {
                $error = 'Expected 1 space after the longest type';
                $this->currentFile->addError($error, $longestType, 'SpacingAfterLongType');
            }

            if ($spaceBeforeComment !== 1 && $spaceBeforeComment !== 10000) {
                $error = 'Expected 1 space after the longest variable name';
                $this->currentFile->addError($error, $longestVar, 'SpacingAfterLongName');
            }

        }//end if

        $realNames = array();
        foreach ($realParams as $realParam) {
            $realNames[] = $realParam['name'];
        }

        // Report missing comments.
        $diff = array_diff($realNames, $foundParams);
        foreach ($diff as $neededParam) {
            if (count($params) !== 0) {
                $errorPos = ($params[(count($params) - 1)]->getLine() + $commentStart);
            } else {
                $errorPos = $commentStart;
            }

            $error = 'Doc comment for "%s" missing';
            $data  = array($neededParam);
            $this->currentFile->addError($error, $errorPos, 'MissingParamTag', $data);
        }

    }//end processParams()


    /**
     * Process a list of unknown tags.
     *
     * @param int $commentStart The position in the stack where the comment started.
     * @param int $commentEnd   The position in the stack where the comment ended.
     *
     * @return void
     */
    protected function processUnknownTags($commentStart, $commentEnd)
    {

    }//end processUnknownTags


}//end class

?>
