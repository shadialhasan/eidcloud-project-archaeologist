<?php

declare(strict_types=1);

namespace EidCloud\ProjectArchaeologist\Analyzer;

/**
 * High-performance PHP token-based AST and structural parser.
 * Extracts namespaces, classes, methods, functions, calls, includes, globals, and raw SQL queries.
 */
class AstParser
{
    /**
     * Parses a PHP source string or file and returns structural symbols.
     */
    public function parseFile(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return [];
        }

        return $this->parseContent($content, $filePath);
    }

    public function parseContent(string $content, string $filePath = ''): array
    {
        $tokens = token_get_all($content);
        $count = count($tokens);

        $currentNamespace = '';
        $currentClass = null;
        $classes = [];
        $functions = [];
        $methods = [];
        $calls = [];
        $instantiations = [];
        $includes = [];
        $globalUsages = [];
        $superglobalUsages = [];
        $constants = [];

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (!is_array($token)) {
                // Braces or simple characters
                continue;
            }

            $id = $token[0];
            $text = $token[1];
            $line = $token[2];

            // 1. Namespace
            if ($id === T_NAMESPACE) {
                $ns = '';
                $i++;
                while ($i < $count && ($tokens[$i] !== ';' && $tokens[$i] !== '{')) {
                    if (is_array($tokens[$i])) {
                        if ($tokens[$i][0] === T_STRING || (defined('T_NAME_QUALIFIED') && $tokens[$i][0] === T_NAME_QUALIFIED)) {
                            $ns .= $tokens[$i][1];
                        }
                    }
                    $i++;
                }
                $currentNamespace = trim($ns);
                continue;
            }

            // 2. Class / Interface / Trait
            if ($id === T_CLASS || $id === T_INTERFACE || $id === T_TRAIT || (defined('T_ENUM') && $id === T_ENUM)) {
                // Skip ::class constant resolution
                $prev = $this->getPrevNonWhitespace($tokens, $i);
                if ($prev && is_array($prev) && $prev[0] === T_DOUBLE_COLON) {
                    continue;
                }

                $next = $this->getNextNonWhitespace($tokens, $i);
                if ($next && is_array($next) && $next[0] === T_STRING) {
                    $className = $next[1];
                    $fullClass = $currentNamespace !== '' ? $currentNamespace . '\\' . $className : $className;
                    $currentClass = $fullClass;

                    $classes[$fullClass] = [
                        'name' => $className,
                        'fqcn' => $fullClass,
                        'type' => token_name($id),
                        'line' => $line,
                        'file' => $filePath,
                    ];
                }
                continue;
            }

            // 3. Function / Method declaration
            if ($id === T_FUNCTION) {
                $next = $this->getNextNonWhitespace($tokens, $i);
                if ($next && is_array($next) && $next[0] === T_STRING) {
                    $funcName = $next[1];

                    // Check if inside class
                    if ($currentClass !== null) {
                        $methodKey = $currentClass . '::' . $funcName;
                        $methods[$methodKey] = [
                            'class' => $currentClass,
                            'name' => $funcName,
                            'line' => $line,
                            'file' => $filePath,
                        ];
                    } else {
                        $fqfn = $currentNamespace !== '' ? $currentNamespace . '\\' . $funcName : $funcName;
                        $functions[$fqfn] = [
                            'name' => $funcName,
                            'fqfn' => $fqfn,
                            'line' => $line,
                            'file' => $filePath,
                        ];
                    }
                }
                continue;
            }

            // 4. Function call or Method call
            if ($id === T_STRING) {
                $next = $this->getNextNonWhitespace($tokens, $i);
                $prev = $this->getPrevNonWhitespace($tokens, $i);

                if ($next === '(') {
                    // Function or method call
                    if ($prev && is_array($prev) && ($prev[0] === T_OBJECT_OPERATOR || (defined('T_NULLSAFE_OBJECT_OPERATOR') && $prev[0] === T_NULLSAFE_OBJECT_OPERATOR))) {
                        // ->methodName()
                        $calls[] = [
                            'type' => 'method',
                            'name' => $text,
                            'line' => $line,
                            'file' => $filePath,
                        ];
                    } elseif ($prev && is_array($prev) && $prev[0] === T_DOUBLE_COLON) {
                        // Class::methodName()
                        $calls[] = [
                            'type' => 'static_method',
                            'name' => $text,
                            'line' => $line,
                            'file' => $filePath,
                        ];
                    } elseif (!$prev || !is_array($prev) || ($prev[0] !== T_FUNCTION && $prev[0] !== T_NEW)) {
                        // standalone function call
                        $calls[] = [
                            'type' => 'function',
                            'name' => $text,
                            'line' => $line,
                            'file' => $filePath,
                        ];
                    }
                }
                continue;
            }

            // 5. Instantiations (new ClassName)
            if ($id === T_NEW) {
                $next = $this->getNextNonWhitespace($tokens, $i);
                if ($next && is_array($next) && ($next[0] === T_STRING || (defined('T_NAME_QUALIFIED') && $next[0] === T_NAME_QUALIFIED))) {
                    $instantiations[] = [
                        'class' => $next[1],
                        'line' => $line,
                        'file' => $filePath,
                    ];
                }
                continue;
            }

            // 6. Includes / Requires
            if ($id === T_INCLUDE || $id === T_INCLUDE_ONCE || $id === T_REQUIRE || $id === T_REQUIRE_ONCE) {
                $target = '';
                $j = $i + 1;
                while ($j < $count && $tokens[$j] !== ';') {
                    if (is_array($tokens[$j])) {
                        $target .= $tokens[$j][1];
                    } else {
                        $target .= $tokens[$j];
                    }
                    $j++;
                }
                $includes[] = [
                    'type' => token_name($id),
                    'target' => trim($target, " \t\n\r\0\x0B()"),
                    'line' => $line,
                    'file' => $filePath,
                ];
                continue;
            }

            // 7. Global keyword usage
            if ($id === T_GLOBAL) {
                $globalUsages[] = [
                    'line' => $line,
                    'file' => $filePath,
                ];
                continue;
            }

            // 8. Superglobals ($GLOBALS, $_GET, $_POST, $_REQUEST, $_SESSION, etc.)
            if ($id === T_VARIABLE && in_array($text, ['$_GET', '$_POST', '$_REQUEST', '$_COOKIE', '$_FILES', '$_SERVER', '$GLOBALS', '$_ENV'], true)) {
                $superglobalUsages[] = [
                    'variable' => $text,
                    'line' => $line,
                    'file' => $filePath,
                ];
                continue;
            }
        }

        return [
            'file' => $filePath,
            'namespace' => $currentNamespace,
            'classes' => $classes,
            'methods' => $methods,
            'functions' => $functions,
            'calls' => $calls,
            'instantiations' => $instantiations,
            'includes' => $includes,
            'global_usages' => $globalUsages,
            'superglobal_usages' => $superglobalUsages,
        ];
    }

    private function getNextNonWhitespace(array &$tokens, int $currentIndex): array|string|null
    {
        $count = count($tokens);
        for ($i = $currentIndex + 1; $i < $count; $i++) {
            $t = $tokens[$i];
            if (is_array($t) && ($t[0] === T_WHITESPACE || $t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT)) {
                continue;
            }
            return $t;
        }
        return null;
    }

    private function getPrevNonWhitespace(array &$tokens, int $currentIndex): array|string|null
    {
        for ($i = $currentIndex - 1; $i >= 0; $i--) {
            $t = $tokens[$i];
            if (is_array($t) && ($t[0] === T_WHITESPACE || $t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT)) {
                continue;
            }
            return $t;
        }
        return null;
    }
}
