<?php

declare(strict_types=1);

namespace EidCloud\ProjectArchaeologist\Model;

/**
 * Encapsulates the complete findings of a codebase excavation.
 */
class ExcavationResult
{
    public function __construct(
        public string $projectPath,
        public array $summary = [],
        public array $files = [],
        public array $architecture = [],
        public array $entryPoints = [],
        public array $routes = [],
        public array $database = [],
        public array $dependencies = [],
        public array $deadCode = [],
        public array $securitySmells = [],
        public array $refactoringRoadmap = [],
        public float $executionTime = 0.0
    ) {
    }

    public function toArray(): array
    {
        return [
            'project_path' => $this->projectPath,
            'execution_time_seconds' => $this->executionTime,
            'summary' => $this->summary,
            'entry_points_count' => count($this->entryPoints),
            'routes_count' => count($this->routes),
            'queries_count' => count($this->database['queries'] ?? []),
            'tables_count' => count($this->database['tables'] ?? []),
            'dead_code' => [
                'classes' => count($this->deadCode['classes'] ?? []),
                'methods' => count($this->deadCode['methods'] ?? []),
                'functions' => count($this->deadCode['functions'] ?? []),
            ],
            'security_smells_count' => count($this->securitySmells),
            'dependencies' => $this->dependencies,
        ];
    }
}
