<?php

declare(strict_types=1);

namespace EidCloud\ProjectArchaeologist\Detectors;

/**
 * Scans legacy code for security smells, dangerous anti-patterns, raw SQL vulnerabilities, and credential leakage.
 */
class SecuritySmellDetector
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function detect(string $filePath, string $content, string $relativePath, string $category): array
    {
        $smells = [];
        $lines = explode("\n", $content);

        // 1. Hardcoded Credentials & Secrets
        $secretPatterns = [
            'Hardcoded API Key / Token' => '/(?:api[_-]?key|access[_-]?token|secret[_-]?key|auth[_-]?token)\s*=\s*[\'"][a-zA-Z0-9_\-]{16,}[\'"]/i',
            'Hardcoded Database Password' => '/(?:\$password|\$db_pass|\$dbpass|DB_PASSWORD)\s*=\s*[\'"][^\'"]+[\'"]/i',
            'Hardcoded AWS / Cloud Key' => '/(?:AKIA[0-9A-Z]{16}|aws_secret_access_key\s*=\s*[\'"][^\'"]+[\'"])/i',
            'Private Key Embedded' => '/-----BEGIN (?:RSA |EC )?PRIVATE KEY-----/',
        ];

        foreach ($lines as $lineNum => $line) {
            foreach ($secretPatterns as $title => $pattern) {
                if (preg_match($pattern, $line)) {
                    $smells[] = [
                        'type' => 'Hardcoded Credential',
                        'title' => $title,
                        'file' => $relativePath,
                        'line' => $lineNum + 1,
                        'severity' => 'CRITICAL',
                        'snippet' => trim(substr($line, 0, 100)),
                        'remediation' => 'Move sensitive credentials to environment variables or secret manager.',
                    ];
                }
            }
        }

        // 2. PHP Specific Vulnerabilities & Anti-patterns
        if ($category === 'php') {
            foreach ($lines as $lineNum => $line) {
                $lineNo = $lineNum + 1;

                // Raw SQL Concatenation / SQL Injection
                if (preg_match('/(?:SELECT|INSERT|UPDATE|DELETE)\s+.*?\s*[\'"]\s*\.\s*\$(?:_GET|_POST|_REQUEST|id|username|email|param)/i', $line)) {
                    $smells[] = [
                        'type' => 'SQL Injection Vulnerability',
                        'title' => 'Direct concatenation of variables into SQL query',
                        'file' => $relativePath,
                        'line' => $lineNo,
                        'severity' => 'CRITICAL',
                        'snippet' => trim(substr($line, 0, 120)),
                        'remediation' => 'Use PDO prepared statements with parameterized bound parameters.',
                    ];
                }

                // eval(), system(), exec(), passthru(), shell_exec()
                if (preg_match('/\b(eval|exec|passthru|shell_exec|system|popen|proc_open)\s*\(/i', $line, $m)) {
                    $smells[] = [
                        'type' => 'Dangerous Function Execution',
                        'title' => 'Execution of dangerous system function: ' . $m[1] . '()',
                        'file' => $relativePath,
                        'line' => $lineNo,
                        'severity' => ($m[1] === 'eval' ? 'CRITICAL' : 'HIGH'),
                        'snippet' => trim(substr($line, 0, 100)),
                        'remediation' => 'Refactor away from dynamic code execution; sanitize and whitelist arguments.',
                    ];
                }

                // extract($_GET) or extract($_POST)
                if (preg_match('/\bextract\s*\(\s*\$(?:_GET|_POST|_REQUEST)/i', $line)) {
                    $smells[] = [
                        'type' => 'Variable Overwrite Anti-pattern',
                        'title' => 'extract() called directly on superglobal input array',
                        'file' => $relativePath,
                        'line' => $lineNo,
                        'severity' => 'CRITICAL',
                        'snippet' => trim(substr($line, 0, 100)),
                        'remediation' => 'Never use extract() on user inputs; access variables explicitly.',
                    ];
                }

                // Global State Abuse
                if (preg_match('/\bglobal\s+\$[a-zA-Z0-9_]+;/i', $line) || preg_match('/\$GLOBALS\[[\'"][a-zA-Z0-9_]+[\'"]\]/i', $line)) {
                    $smells[] = [
                        'type' => 'Global State Abuse',
                        'title' => 'Pollution or reliance on global variable state',
                        'file' => $relativePath,
                        'line' => $lineNo,
                        'severity' => 'MEDIUM',
                        'snippet' => trim(substr($line, 0, 100)),
                        'remediation' => 'Replace globals with Dependency Injection or structured service containers.',
                    ];
                }

                // Unsanitized echo / XSS
                if (preg_match('/echo\s+\$(?:_GET|_POST|_REQUEST)\[/i', $line)) {
                    $smells[] = [
                        'type' => 'Cross-Site Scripting (XSS)',
                        'title' => 'Direct echo of unsanitized superglobal input',
                        'file' => $relativePath,
                        'line' => $lineNo,
                        'severity' => 'HIGH',
                        'snippet' => trim(substr($line, 0, 100)),
                        'remediation' => 'Escape output using htmlspecialchars($var, ENT_QUOTES, \'UTF-8\').',
                    ];
                }

                // Obsolete deprecated mysql_* extension
                if (preg_match('/\bmysql_(?:connect|query|fetch_array|fetch_assoc|select_db)\s*\(/i', $line, $m)) {
                    $smells[] = [
                        'type' => 'Obsolete Extension',
                        'title' => 'Use of extinct PHP mysql_* extension (' . $m[0] . ')',
                        'file' => $relativePath,
                        'line' => $lineNo,
                        'severity' => 'HIGH',
                        'snippet' => trim(substr($line, 0, 100)),
                        'remediation' => 'Upgrade to PDO or mysqli extension immediately.',
                    ];
                }
            }
        }

        // 3. JavaScript Smells (e.g. innerHTML, document.write)
        if ($category === 'javascript') {
            foreach ($lines as $lineNum => $line) {
                $lineNo = $lineNum + 1;
                if (preg_match('/\b(innerHTML\s*=|document\.write\s*\(|eval\s*\()/i', $line, $m)) {
                    $smells[] = [
                        'type' => 'Frontend DOM XSS / Eval',
                        'title' => 'Insecure DOM manipulation: ' . $m[1],
                        'file' => $relativePath,
                        'line' => $lineNo,
                        'severity' => 'MEDIUM',
                        'snippet' => trim(substr($line, 0, 100)),
                        'remediation' => 'Use textContent or safe DOM creation methods instead of innerHTML/eval.',
                    ];
                }
            }
        }

        return $smells;
    }
}
