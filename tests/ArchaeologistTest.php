<?php

declare(strict_types=1);

namespace EidCloud\ProjectArchaeologist\Tests;

use EidCloud\ProjectArchaeologist\Archaeologist;
use EidCloud\ProjectArchaeologist\Analyzer\AstParser;
use EidCloud\ProjectArchaeologist\Analyzer\PolyglotAnalyzer;
use EidCloud\ProjectArchaeologist\Core\FileCrawler;
use EidCloud\ProjectArchaeologist\Detectors\DeadCodeDetector;
use EidCloud\ProjectArchaeologist\Detectors\SecuritySmellDetector;
use EidCloud\ProjectArchaeologist\Generators\DocumentationGenerator;

class ArchaeologistTest
{
    private string $fixtureDir;

    public function __construct()
    {
        $this->fixtureDir = __DIR__ . '/Fixtures/legacy_project';
    }

    public function runAll(): void
    {
        $this->testFileCrawler();
        $this->testAstParser();
        $this->testPolyglotAnalyzer();
        $this->testSecuritySmellDetector();
        $this->testDeadCodeDetector();
        $this->testArchaeologistExcavation();
        $this->testDocumentationGenerator();
    }

    private function testFileCrawler(): void
    {
        echo "Testing FileCrawler... ";
        $crawler = new FileCrawler();
        $files = $crawler->crawl($this->fixtureDir);

        assert(count($files) >= 5, "Expected at least 5 files discovered");
        assert(isset($files['index.php']), "Expected index.php found");
        assert(isset($files['database/schema.sql']), "Expected database/schema.sql found");
        echo "✅ PASSED\n";
    }

    private function testAstParser(): void
    {
        echo "Testing AstParser... ";
        $parser = new AstParser();
        $activeUserPath = $this->fixtureDir . '/src/ActiveUser.php';
        $res = $parser->parseFile($activeUserPath);

        assert(!empty($res['classes']['ActiveUser']), "Expected ActiveUser class parsed");
        assert(!empty($res['methods']['ActiveUser::renderDashboard']), "Expected renderDashboard method parsed");
        assert(!empty($res['methods']['ActiveUser::abandonedMethodOnly']), "Expected abandonedMethodOnly method parsed");
        echo "✅ PASSED\n";
    }

    private function testPolyglotAnalyzer(): void
    {
        echo "Testing PolyglotAnalyzer... ";
        $analyzer = new PolyglotAnalyzer();

        // Test PHP
        $phpRes = $analyzer->analyzeFile($this->fixtureDir . '/index.php', 'php', 'index.php');
        assert(!empty($phpRes['entry_points']), "Expected index.php recognized as entry point");
        assert(!empty($phpRes['routes']), "Expected parameter routing detected");

        // Test SQL
        $sqlRes = $analyzer->analyzeFile($this->fixtureDir . '/database/schema.sql', 'sql', 'database/schema.sql');
        assert(count($sqlRes['queries']) >= 2, "Expected CREATE TABLE statements extracted");

        // Test Shell
        $shRes = $analyzer->analyzeFile($this->fixtureDir . '/scripts/backup.sh', 'shell', 'scripts/backup.sh');
        assert(!empty($shRes['dependencies']), "Expected mysqldump dependency detected");
        echo "✅ PASSED\n";
    }

    private function testSecuritySmellDetector(): void
    {
        echo "Testing SecuritySmellDetector... ";
        $detector = new SecuritySmellDetector();
        
        $configContent = file_get_contents($this->fixtureDir . '/config.php');
        $smells = $detector->detect($this->fixtureDir . '/config.php', $configContent, 'config.php', 'php');
        
        assert(!empty($smells), "Expected security smells found in config.php");
        $hasSecret = false;
        foreach ($smells as $s) {
            if ($s['type'] === 'Hardcoded Credential') {
                $hasSecret = true;
                break;
            }
        }
        assert($hasSecret, "Expected hardcoded credential detected in config.php");

        $userContent = file_get_contents($this->fixtureDir . '/src/ActiveUser.php');
        $userSmells = $detector->detect($this->fixtureDir . '/src/ActiveUser.php', $userContent, 'src/ActiveUser.php', 'php');
        $hasSqlInjection = false;
        foreach ($userSmells as $s) {
            if ($s['type'] === 'SQL Injection Vulnerability') {
                $hasSqlInjection = true;
                break;
            }
        }
        assert($hasSqlInjection, "Expected SQL injection detected in ActiveUser.php");
        echo "✅ PASSED\n";
    }

    private function testDeadCodeDetector(): void
    {
        echo "Testing DeadCodeDetector... ";
        $archaeologist = new Archaeologist();
        $result = $archaeologist->excavate($this->fixtureDir);

        $dead = $result->deadCode;
        assert(isset($dead['classes']['ForgottenLegacyHelper']), "Expected ForgottenLegacyHelper identified as dead class");
        assert(isset($dead['methods']['ActiveUser::abandonedMethodOnly']), "Expected abandonedMethodOnly identified as dead method");
        assert(!isset($dead['methods']['ActiveUser::renderDashboard']), "renderDashboard must NOT be dead code");
        echo "✅ PASSED\n";
    }

    private function testArchaeologistExcavation(): void
    {
        echo "Testing Archaeologist Excavation Engine... ";
        $archaeologist = new Archaeologist();
        $result = $archaeologist->excavate($this->fixtureDir);

        assert($result->executionTime >= 0, "Execution time measured");
        assert(count($result->files) >= 5, "Files scanned");
        assert(count($result->entryPoints) >= 1, "Entry points discovered");
        assert(count($result->database['tables']) >= 2, "Tables discovered");
        assert(in_array('users', $result->database['tables'], true), "users table discovered");
        echo "✅ PASSED\n";
    }

    private function testDocumentationGenerator(): void
    {
        echo "Testing DocumentationGenerator... ";
        $archaeologist = new Archaeologist();
        $result = $archaeologist->excavate($this->fixtureDir);

        $tmpOut = sys_get_temp_dir() . '/eidcloud_test_docs_' . uniqid();
        $docs = $archaeologist->generateDocumentation($result, $tmpOut);

        assert(file_exists($docs['PROJECT.md']), "PROJECT.md generated");
        assert(file_exists($docs['ARCHITECTURE.md']), "ARCHITECTURE.md generated");
        assert(file_exists($docs['DATABASE.md']), "DATABASE.md generated");
        assert(file_exists($docs['API.md']), "API.md generated");
        assert(file_exists($docs['REFACTORING_ROADMAP.md']), "REFACTORING_ROADMAP.md generated");

        $archDoc = file_get_contents($docs['ARCHITECTURE.md']);
        assert(str_contains($archDoc, 'flowchart TD'), "Mermaid flowchart included in ARCHITECTURE.md");

        // Cleanup
        foreach ($docs as $f) {
            @unlink($f);
        }
        @rmdir($tmpOut);
        echo "✅ PASSED\n";
    }
}
