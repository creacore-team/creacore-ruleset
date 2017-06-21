<?php
/**
 * Verifies that control statements conform to their coding standards.
 *
 * PHP version 5
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2014 Allman Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */

 namespace PHP_CodeSniffer\Standards\Generic\Sniffs\ControlStructures;

 use PHP_CodeSniffer\Sniffs\Sniff;
 use PHP_CodeSniffer\Files\File;
 use PHP_CodeSniffer\Util\Tokens;

/**
 * Verifies that control statements conform to their coding standards.
 */
class AllmanSniffsControlStructuresControlSignatureSniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supportedTokenizers = array('PHP', 'JS');

    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return int[]
     */
    public function register()
    {
        return array(
            T_TRY,
            T_CATCH,
            T_DO,
            T_WHILE,
            T_FOR,
            T_IF,
            T_FOREACH,
            T_ELSE,
            T_ELSEIF,
            T_SWITCH,
        );
    }

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
     * @param int                  $stackPtr  The position of the current token in the
     *                                        stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        if(isset($tokens[($stackPtr + 1)]) === false)
        {
            return;
        }

        // No space before parenthesis begin
        $parenthesis_token = array(
            T_CATCH => T_CATCH,
            T_WHILE => T_WHILE,
            T_FOR => T_FOR,
            T_IF => T_IF,
            T_FOREACH => T_FOREACH,
            T_ELSEIF => T_ELSEIF,
            T_SWITCH => T_SWITCH
        );
        if(isset($parenthesis_token[$tokens[$stackPtr]['code']]) && $tokens[($stackPtr + 1)]['content'] !== '(')
        {
            $error = 'No character before parenthesis opener, "%s" found';
            $data  = array(
                strtoupper($tokens[$stackPtr+1]['content'])
            );

            $fix = $phpcsFile->addFixableError($error, $stackPtr, 'NoCharacterBeforeParenthesisOpener', $data);
            if($fix === true)
            {
                $phpcsFile->fixer->replaceToken(($stackPtr + 1), '');
            }
        }

        // New line after nonParenthesis
        $nonParenthesis_token = array(
            T_TRY => T_TRY,
            T_DO => T_DO,
            T_ELSE => T_ELSE
        );
        if(isset($nonParenthesis_token[$tokens[$stackPtr]['code']]) && $tokens[$tokens[$stackPtr]['scope_opener']]["line"] != $tokens[$stackPtr]["line"]+1)
        {
            $error = 'Opener must be on the next line';
            $data  = array();

            $fix = $phpcsFile->addFixableError($error, $stackPtr, 'OpenerNextLine', $data);
            if($fix === true)
            {
                $phpcsFile->fixer->addContent($stackPtr, "\n");
            }
        }

        // Single space after closing parenthesis.
        if(isset($tokens[$stackPtr]['parenthesis_closer']) && isset($tokens[$stackPtr]['scope_opener']))
        {
            $closer  = $tokens[$stackPtr]['parenthesis_closer'];
            $opener  = $tokens[$stackPtr]['scope_opener'];

            if($tokens[$opener]['type'] === 'T_COLON')
            {
                $tokens_between = $opener - $closer - 1;
                $content = $phpcsFile->getTokensAsString($closer + 1, $tokens_between);

                if($content !== '')
                {
                    if($tokens_between === 1 && $tokens[$closer + 1]['type'] === 'T_WHITESPACE')
                    {
                        $error = 'Expected “:” after closing parenthesis; found “%s”';
                        $data = array(str_replace($phpcsFile->eolChar, '\n', $content));

                        if($phpcsFile->addFixableError($error, $closer + 1, 'ColonAfterCloseParenthesis', $data))
                        {
                            $phpcsFile->fixer->replaceToken($closer, '');
                        }
                    }
                    elseif($phpcsFile->addError('Expected “:” right after closing parenthesis', $closer))
                    {
                        $phpcsFile->fixer->addContent($closer, "\n");
                    }
                }
            }
            else
            {
                $content = $phpcsFile->getTokensAsString(($closer + 1), ($opener - $closer - 1));

                if(strpos($content, $phpcsFile->eolChar) !== 0)
                {
                    $error = 'Expected a new line after closing parenthesis; found %s';
                    $found = '"'.str_replace($phpcsFile->eolChar, '\n', $content).'"';

                    if($phpcsFile->addFixableError($error, $closer, 'NewLineAfterCloseParenthesis', array($found)))
                    {
                        $phpcsFile->fixer->addContent($closer, "\n");
                    }
                }
            }
        }

        // Single newline after opening brace.
        if(isset($tokens[$stackPtr]['scope_opener']) === true)
        {
            $opener = $tokens[$stackPtr]['scope_opener'];
            for($next = ($opener + 1); $next < $phpcsFile->numTokens; $next++)
            {
                $code = $tokens[$next]['code'];

                if($code === T_WHITESPACE || ($code === T_INLINE_HTML && trim($tokens[$next]['content']) === ''))
                {
                    continue;
                }

                // Skip all empty tokens on the same line as the opener.
                if($tokens[$next]['line'] === $tokens[$opener]['line']
                        && (isset(Tokens::$emptyTokens[$code]) === true
                        || $code === T_CLOSE_TAG)
                        )
                {
                    continue;
                }

                // We found the first bit of a code, or a comment on the
                // following line.
                break;
            }

            if($tokens[$next]['line'] === $tokens[$opener]['line'])
            {
                $error = 'Newline required after opening brace';
                $fix   = $phpcsFile->addFixableError($error, $opener, 'NewlineAfterOpenBrace');
                if($fix === true)
                {
                    $phpcsFile->fixer->beginChangeset();
                    for($i = ($opener + 1); $i < $next; $i++)
                    {
                        if(trim($tokens[$i]['content']) !== '')
                        {
                            break;
                        }

                        // Remove whitespace.
                        $phpcsFile->fixer->replaceToken($i, '');
                    }

                    $phpcsFile->fixer->addContent($opener, $phpcsFile->eolChar);
                    $phpcsFile->fixer->endChangeset();
                }
            }
        }
        elseif($tokens[$stackPtr]['code'] === T_WHILE)
        {
            // Zero spaces after parenthesis closer.
            $closer = $tokens[$stackPtr]['parenthesis_closer'];
            $found  = 0;
            if($tokens[($closer + 1)]['code'] === T_WHITESPACE)
            {
                if(strpos($tokens[($closer + 1)]['content'], $phpcsFile->eolChar) !== false)
                {
                    $found = 'newline';
                }
                else
                {
                    $found = strlen($tokens[($closer + 1)]['content']);
                }
            }

            if($found !== 0)
            {
                $error = 'Expected 0 spaces before semicolon; %s found';
                $data  = array($found);
                $fix   = $phpcsFile->addFixableError($error, $closer, 'SpaceBeforeSemicolon', $data);
                if($fix === true)
                {
                    $phpcsFile->fixer->replaceToken(($closer + 1), '');
                }
            }
        }

        // Only want to check multi-keyword structures from here on.
        if($tokens[$stackPtr]['code'] === T_DO)
        {
            if(isset($tokens[$stackPtr]['scope_closer']) === false)
            {
                return;
            }

            $closer = $tokens[$stackPtr]['scope_closer'];
        }
        elseif($tokens[$stackPtr]['code'] === T_ELSE ||
            $tokens[$stackPtr]['code'] === T_ELSEIF ||
            $tokens[$stackPtr]['code'] === T_CATCH
        )
        {
            $closer = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);
            if($closer === false || $tokens[$closer]['code'] !== T_CLOSE_CURLY_BRACKET)
            {
                return;
            }
        }
        else
        {
            return;
        }

        // Single space after closing brace.
        $found = 1;
        if($tokens[($closer + 1)]['code'] !== T_WHITESPACE)
        {
            $found = 0;
        }
        elseif($tokens[($closer + 1)]['content'] !== ' ')
        {
            if(strpos($tokens[($closer + 1)]['content'], $phpcsFile->eolChar) !== false)
            {
                $found = 'newline';
            }
            else
            {
                $found = strlen($tokens[($closer + 1)]['content']);
            }
        }

        if($found !== 'newline')
        {
            $error = 'Expected a newline after closing brace; %s found';
            $data  = array($found);
            $fix   = $phpcsFile->addFixableError($error, $closer, 'NewlineAfterCloseBrace', $data);
            if($fix === true)
            {
                $phpcsFile->fixer->addContent($closer, "\n");
            }
        }
    }
}
