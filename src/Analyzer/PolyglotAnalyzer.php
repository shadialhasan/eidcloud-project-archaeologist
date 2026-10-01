<?php

declare(strict_types=1);

namespace EidCloud\ProjectArchaeologist\Analyzer;

/**
 * Multi-language static analyzer covering PHP, JavaScript, SQL, CSS, and Shell scripts.
 */
class PolyglotAnalyzer
{
    public function analyzeFile(string $filePath, string $category, string $relativePath): array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return [];
        }

        $lines = explode("\n", $content);
        $lineCount = count($lines);

        $result = [
            'file' => $relativePath,
            'category' => $category,
            'lines' => $lineCount,
            'size' => filesize($filePath),
            'queries' => [],
            'entry_points' => [],
            'routes' => [],
            'dependencies' => [],
            'security_smells' => [],
        ];

        switch ($category) {
            case 'php':
                $this->analyzePhp($content, $relativePath, $result);
                break;
            case 'javascript':
                $this->analyzeJs($content, $relativePath, $result);
                break;
            case 'sql':
                $this->analyzeSql($content, $relativePath, $result);
                break;
            case 'css':
                $this->analyzeCss($content, $relativePath, $result);
                break;
            case 'shell':
                $this->analyzeShell($content, $relativePath, $result);
                break;
            case 'config':
                $this->analyzeConfig($content, $relativePath, $result);
                break;
        }

        return $result;
    }

    private function analyzePhp(string $content, string $relPath, array &$result): void
    {
        // 1. Detect Entry Points (e.g. index.php, api.php, admin.php, cron.php, public/, webhook.php)
        $isEntryPoint = false;
        $entryReason = [];

        $filename = basename($relPath);
        if (preg_match('/^(index|app|api|server|main|admin|cron|worker|webhook|cli|manage|dispatch)\.php$/i', $filename)) {
            $isEntryPoint = true;
            $entryReason[] = 'Well-known entry point script name';
        }
        if (str_starts_with($relPath, 'public/') || str_starts_with($relPath, 'web/') || str_starts_with($relPath, 'www/')) {
            $isEntryPoint = true;
            $entryReason[] = 'Located in public document root';
        }
        if (str_starts_with($content, '#!/usr/bin/env php') || str_starts_with($content, '#!/usr/bin/php')) {
            $isEntryPoint = true;
            $entryReason[] = 'PHP CLI Shebang executable';
        }

        if ($isEntryPoint) {
            $result['entry_points'][] = [
                'type' => 'HTTP / CLI Entry Point',
                'file' => $relPath,
                'reasons' => $entryReason,
            ];
        }

        // 2. HTTP Routing Patterns (Custom framework, procedural $_GET['action'], Switch router, or Slim/Laravel/Symfony routes)
        if (preg_match_all('/\b(get|post|put|delete|patch|any|match)\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,/i', $content, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $result['routes'][] = [
                    'method' => strtoupper($match[1]),
                    'pattern' => $match[2],
                    'file' => $relPath,
                    'type' => 'Framework Route',
                ];
            }
        }

        // Procedural $_GET['action'] or $_GET['page'] routing
        if (preg_match_all('/\$(?:_GET|_REQUEST)\[[\'"](action|page|cmd|do|op|route|task|view)[\'"]\]/i', $content, $m, PREG_SET_ORDER)) {
            $result['routes'][] = [
                'method' => 'GET/REQUEST',
                'pattern' => '?action=* parameter dispatch',
                'file' => $relPath,
                'type' => 'Legacy Parameter Router',
            ];
        }

        // 3. Raw SQL queries inside PHP
        $sqlPatterns = [
            '/(?:SELECT\s+.+?\s+FROM|INSERT\s+INTO|UPDATE\s+.+?\s+SET|DELETE\s+FROM|CREATE\s+TABLE|ALTER\s+TABLE|DROP\s+TABLE)\s+[`"\'\w]+/is'
        ];

        foreach ($sqlPatterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $queryStr = trim($match[0]);
                    // Extract table name
                    $table = 'unknown';
                    if (preg_match('/(?:FROM|INTO|UPDATE|TABLE)\s+[`"\']?([a-zA-Z0-9_]+)[`"\']?/i', $queryStr, $tm)) {
                        $table = $tm[1];
                    }
                    $result['queries'][] = [
                        'file' => $relPath,
                        'query' => substr($queryStr, 0, 150),
                        'table' => $table,
                        'source' => 'php_embedded',
                    ];
                }
            }
        }

        // 4. Third-party dependencies & Legacy extensions
        if (preg_match('/composer\.json$/i', $relPath)) {
            $data = json_decode($content, true);
            if (is_array($data) && isset($data['require'])) {
                foreach ($data['require'] as $pkg => $ver) {
                    $result['dependencies'][] = ['package' => $pkg, 'version' => $ver, 'type' => 'composer'];
                }
            }
        }
        if (preg_match('/\b(curl_init|mysqli_connect|mysql_connect|pg_connect|sqlite_open|ldap_connect|ftp_connect|redis|memcached)\b/i', $content, $m)) {
            $result['dependencies'][] = ['package' => 'ext-' . strtolower($m[1]), 'version' => 'native/extension', 'type' => 'php_extension'];
        }
    }

    private function analyzeJs(string $content, string $relPath, array &$result): void
    {
        // Check for node/express/react/vue or legacy jQuery
        if (preg_match('/package\.json$/i', $relPath)) {
            $data = json_decode($content, true);
            if (is_array($data) && isset($data['dependencies'])) {
                foreach ($data['dependencies'] as $pkg => $ver) {
                    $result['dependencies'][] = ['package' => $pkg, 'version' => $ver, 'type' => 'npm'];
                }
            }
            return;
        }

        if (preg_match('/\bapp\.(get|post|put|delete)\s*\(\s*[\'"]([^\'"]+)[\'"]/i', $content, $m)) {
            $result['routes'][] = [
                'method' => strtoupper($m[1]),
                'pattern' => $m[2],
                'file' => $relPath,
                'type' => 'Express/NodeJS Route',
            ];
        }

        if (preg_match('/\bfetch\s*\(\s*[\'"]([^\'"]+)[\'"]|\$\.ajax\s*\(\s*\{\s*url\s*:\s*[\'"]([^\'"]+)[\'"]/i', $content, $m)) {
            $url = !empty($m[1]) ? $m[1] : (!empty($m[2]) ? $m[2] : '');
            if ($url !== '') {
                $result['routes'][] = [
                    'method' => 'API_CONSUMER',
                    'pattern' => $url,
                    'file' => $relPath,
                    'type' => 'Frontend AJAX / Fetch Call',
                ];
            }
        }

        // Detect frontend dependencies
        if (str_contains($content, 'jQuery') || str_contains($content, '$(document).ready')) {
            $result['dependencies'][] = ['package' => 'jQuery', 'version' => 'legacy', 'type' => 'frontend_library'];
        }
    }

    private function analyzeSql(string $content, string $relPath, array &$result): void
    {
        // Extract DDL and Schema statements
        if (preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`"\'\s]*([a-zA-Z0-9_]+)[`"\'\s]*\s*\((.+?)\)\s*(?:ENGINE|;)/is', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $tableName = $match[1];
                $schemaDef = $match[2];

                // extract columns
                $columns = [];
                $lines = explode(',', $schemaDef);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (preg_match('/^[`"\'\s]*([a-zA-Z0-9_]+)[`"\'\s]+\s+([a-zA-Z]+(?:\([0-9, ]+\))?)/i', $line, $cm)) {
                        if (!in_array(strtoupper($cm[1]), ['PRIMARY', 'KEY', 'UNIQUE', 'CONSTRAINT', 'FOREIGN'], true)) {
                            $columns[] = $cm[1] . ' (' . $cm[2] . ')';
                        }
                    }
                }

                $result['queries'][] = [
                    'file' => $relPath,
                    'query' => "CREATE TABLE {$tableName}",
                    'table' => $tableName,
                    'columns' => $columns,
                    'source' => 'sql_schema',
                ];
            }
        } else {
            // General SQL queries
            if (preg_match_all('/(INSERT\s+INTO|UPDATE|DELETE\s+FROM|SELECT\s+.+?\s+FROM)\s+[`"\'\w]+/i', $content, $m)) {
                foreach ($m[0] as $q) {
                    $result['queries'][] = [
                        'file' => $relPath,
                        'query' => substr(trim($q), 0, 100),
                        'table' => 'sql_file',
                        'source' => 'sql_file',
                    ];
                }
            }
        }
    }

    private function analyzeCss(string $content, string $relPath, array &$result): void
    {
        // Detect CSS frameworks (Bootstrap, Tailwind, Bulma)
        if (str_contains($content, 'bootstrap') || str_contains($content, '.col-md-') || str_contains($content, '.container-fluid')) {
            $result['dependencies'][] = ['package' => 'Bootstrap CSS', 'version' => 'legacy/embedded', 'type' => 'css_framework'];
        }
        if (str_contains($content, 'tailwindcss') || str_contains($content, '@tailwind')) {
            $result['dependencies'][] = ['package' => 'Tailwind CSS', 'version' => 'utility', 'type' => 'css_framework'];
        }
    }

    private function analyzeShell(string $content, string $relPath, array &$result): void
    {
        $result['entry_points'][] = [
            'type' => 'Shell / Automation Script',
            'file' => $relPath,
            'reasons' => ['Automation, deployment or backup shell script'],
        ];

        // Detect cron schedules or CLI tools invoked
        if (preg_match_all('/\b(mysqldump|mysql|docker|composer|npm|git|rsync|tar|cron)\b/i', $content, $m)) {
            foreach (array_unique($m[1]) as $tool) {
                $result['dependencies'][] = ['package' => 'sys-' . strtolower($tool), 'version' => 'system_cli', 'type' => 'cli_tool'];
            }
        }
    }

    private function analyzeConfig(string $content, string $relPath, array &$result): void
    {
        if (preg_match('/composer\.json$/i', $relPath)) {
            $data = json_decode($content, true);
            if (is_array($data) && isset($data['require'])) {
                foreach ($data['require'] as $pkg => $ver) {
                    $result['dependencies'][] = ['package' => $pkg, 'version' => $ver, 'type' => 'composer'];
                }
            }
        }
    }
}
