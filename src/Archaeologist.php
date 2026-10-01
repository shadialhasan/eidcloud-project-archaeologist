<?php

declare(strict_types=1);

namespace EidCloud\ProjectArchaeologist;

use EidCloud\ProjectArchaeologist\Core\FileCrawler;
use EidCloud\ProjectArchaeologist\Analyzer\AstParser;
use EidCloud\ProjectArchaeologist\Analyzer\PolyglotAnalyzer;
use EidCloud\ProjectArchaeologist\Detectors\DeadCodeDetector;
use EidCloud\ProjectArchaeologist\Detectors\SecuritySmellDetector;
use EidCloud\ProjectArchaeologist\Generators\DocumentationGenerator;
use EidCloud\ProjectArchaeologist\Model\ExcavationResult;

/**
 * Main Archaeologist Engine orchestrating full codebase excavation.
 */
class Archaeologist
{
    private FileCrawler $crawler;
    private AstParser $astParser;
    private PolyglotAnalyzer $polyglotAnalyzer;
    private DeadCodeDetector $deadCodeDetector;
    private SecuritySmellDetector $smellDetector;
    private DocumentationGenerator $docGenerator;

    public function __construct(array $customIgnoredDirs = [])
    {
        $this->crawler = new FileCrawler($customIgnoredDirs);
        $this->astParser = new AstParser();
        $this->polyglotAnalyzer = new PolyglotAnalyzer();
        $this->deadCodeDetector = new DeadCodeDetector();
        $this->smellDetector = new SecuritySmellDetector();
        $this->docGenerator = new DocumentationGenerator();
    }

    /**
     * Executes deep excavation of target directory.
     */
    public function excavate(string $projectPath): ExcavationResult
    {
        $startTime = microtime(true);
        $files = $this->crawler->crawl($projectPath);

        $astPerFile = [];
        $rawContents = [];
        $allEntryPoints = [];
        $allRoutes = [];
        $allQueries = [];
        $allDependencies = [];
        $allSecuritySmells = [];
        $tables = [];
        $tableColumns = [];
        $langStats = [];

        foreach ($files as $relPath => $fileMeta) {
            $absPath = $fileMeta['absolute_path'];
            $cat = $fileMeta['category'];

            $content = file_get_contents($absPath);
            if ($content === false) {
                continue;
            }
            $rawContents[$relPath] = $content;

            // Stats
            $lines = substr_count($content, "\n") + 1;
            $files[$relPath]['lines'] = $lines;

            if (!isset($langStats[$cat])) {
                $langStats[$cat] = ['files' => 0, 'lines' => 0];
            }
            $langStats[$cat]['files']++;
            $langStats[$cat]['lines'] += $lines;

            // Polyglot analysis
            $analysis = $this->polyglotAnalyzer->analyzeFile($absPath, $cat, $relPath);
            if (!empty($analysis['entry_points'])) {
                $allEntryPoints = array_merge($allEntryPoints, $analysis['entry_points']);
            }
            if (!empty($analysis['routes'])) {
                $allRoutes = array_merge($allRoutes, $analysis['routes']);
            }
            if (!empty($analysis['queries'])) {
                foreach ($analysis['queries'] as $q) {
                    $allQueries[] = $q;
                    if (!empty($q['table']) && $q['table'] !== 'sql_file' && $q['table'] !== 'unknown') {
                        $tables[$q['table']] = true;
                    }
                    if (!empty($q['columns']) && !empty($q['table'])) {
                        $tableColumns[$q['table']] = $q['columns'];
                    }
                }
            }
            if (!empty($analysis['dependencies'])) {
                $allDependencies = array_merge($allDependencies, $analysis['dependencies']);
            }

            // Security smells
            $smells = $this->smellDetector->detect($absPath, $content, $relPath, $cat);
            if (!empty($smells)) {
                $allSecuritySmells = array_merge($allSecuritySmells, $smells);
            }

            // PHP AST parsing for dead code detection
            if ($cat === 'php') {
                $ast = $this->astParser->parseContent($content, $relPath);
                $astPerFile[$relPath] = $ast;
            }
        }

        // Dead Code Detection
        $deadCode = $this->deadCodeDetector->detect($astPerFile, $rawContents);

        // Deduplicate dependencies
        $uniqueDeps = [];
        foreach ($allDependencies as $dep) {
            $key = ($dep['package'] ?? '') . '@' . ($dep['type'] ?? '');
            if (!isset($uniqueDeps[$key])) {
                $uniqueDeps[$key] = $dep;
            }
        }

        $endTime = microtime(true);

        return new ExcavationResult(
            projectPath: realpath($projectPath) ?: $projectPath,
            summary: [
                'total_files' => count($files),
                'languages' => $langStats,
            ],
            files: $files,
            architecture: [
                'entry_points_count' => count($allEntryPoints),
                'routes_count' => count($allRoutes),
            ],
            entryPoints: $allEntryPoints,
            routes: $allRoutes,
            database: [
                'tables' => array_keys($tables),
                'table_columns' => $tableColumns,
                'queries' => $allQueries,
            ],
            dependencies: array_values($uniqueDeps),
            deadCode: $deadCode,
            securitySmells: $allSecuritySmells,
            executionTime: $endTime - $startTime
        );
    }

    /**
     * Synthesizes documentation from excavation result.
     */
    public function generateDocumentation(ExcavationResult $result, string $outputDir): array
    {
        return $this->docGenerator->generateAll($result, $outputDir);
    }

    public function generateMermaid(ExcavationResult $result): string
    {
        return $this->docGenerator->generateMermaidDiagram($result);
    }
}
