<?php
/**
 * PSR1_Sniffs_Methods_CamelCapsMethodNameSniff.
 *
 * PHP version 5
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2012 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */

if (class_exists('PHP_CodeSniffer_Standards_AbstractScopeSniff', true) === false) {
    throw new PHP_CodeSniffer_Exception('Class PHP_CodeSniffer_Standards_AbstractScopeSniff not found');
}

/**
 * PSR1_Sniffs_Methods_CamelCapsMethodNameSniff.
 *
 * Ensures method names are defined using camel case.
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2012 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 * @version   Release: @package_version@
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */
class Vanilla_Sniffs_Methods_CamelCapsMethodNameSniff extends PHP_CodeSniffer_Standards_AbstractScopeSniff
{

    protected $magicMethods = [
        '__construct', '__destruct', '__call', '__callStatic', '__get', '__set', '__isset', '__unset',
        '__sleep', '__wakeup', '__toString', '__invoke', '__set_state', '__clone', 'debugInfo()'
    ];


    /**
     * Constructs a PSR1_Sniffs_Methods_CamelCapsMethodNameSniff.
     */
    public function __construct()
    {
        parent::__construct(array(T_CLASS, T_INTERFACE, T_TRAIT), array(T_FUNCTION), true);

    }//end __construct()


    /**
     * Processes the tokens within the scope.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file being processed.
     * @param int                  $stackPtr  The position where this token was
     *                                        found.
     * @param int                  $currScope The position of the current scope.
     *
     * @return void
     */
    protected function processTokenWithinScope(PHP_CodeSniffer_File $phpcsFile, $stackPtr, $currScope)
    {
        $methodName = $phpcsFile->getDeclarationName($stackPtr);
        if ($methodName === null) {
            // Ignore closures.
            return;
        }

        // Check for magic methods.
        if (in_array($methodName, $this->magicMethods)) {
            return;
        }

        $testName = ltrim($methodName, '_');

        if (self::isVanillaMethod($testName) === true) {
            if ($this->processVanillaMethod($testName) === false) {
                $error     = 'Method name "%s" is not in valid vanilla format';
                $className = $phpcsFile->getDeclarationName($currScope);
                $errorData = array($className.'::'.$methodName);
                $phpcsFile->addError($error, $stackPtr, 'NotVanilla', $errorData);
            }

        } elseif (!PHP_CodeSniffer::isCamelCaps($testName, false, true, false)) {
            $error     = 'Method name "%s" is not in camel caps format';
            $className = $phpcsFile->getDeclarationName($currScope);
            $errorData = array($className.'::'.$methodName);
            $phpcsFile->addError($error, $stackPtr, 'NotCamelCaps', $errorData);
        }

    }//end processTokenWithinScope()

    /**
     * Checks if given method is a part of vanilla events
     *
     * @param string $name Method Name
     * @return bool
     */
    public static function isVanillaMethod($name) {
        $patterns = array(
            '_handler',
            '_create',
            '_before',
            '_override',
            '_after',
            'controller_'
        );
        foreach ($patterns as $pattern) {
            if (stristr($name, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }

    public function processVanillaMethod($name) {

        $parts = preg_split('/_/', $name);
        foreach ($parts as $part) {
            if (PHP_CodeSniffer::isCamelCaps($part, false, true, false) === false) {
                return false;
            }
        }
        return true;
    }


    /**
     * Processes the tokens outside the scope.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file being processed.
     * @param int                  $stackPtr  The position where this token was
     *                                        found.
     *
     * @return void
     */
    protected function processTokenOutsideScope(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $functionName = $phpcsFile->getDeclarationName($stackPtr);
        if ($functionName === null) {
            // Ignore closures.
            return;
        }

        // allow things like php array_something..
        if (stristr($functionName, '_') !== false) {
            $parts = preg_split('/_/', $functionName);
            foreach ($parts as $part) {
                if ($part != strtolower($part)) {
                    $error     = 'Function name "%s" should all be lowercase';
                    $errorData = array($functionName);
                    $phpcsFile->addError($error, $stackPtr, 'globalFunctionNaming', $errorData);
                }
            }

        } else {
            if (PHP_CodeSniffer::isCamelCaps($functionName, false, true, false) === false) {
                $error     = 'Function name "%s" is not in valid vanilla format';
                $errorData = array($functionName);
                $phpcsFile->addError($error, $stackPtr, 'globalFunctionNaming', $errorData);
            }
        }


    }//end processTokenOutsideScope()


}//end class

?>
