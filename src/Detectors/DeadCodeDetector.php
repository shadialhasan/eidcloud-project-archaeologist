<?php

declare(strict_types=1);

namespace EidCloud\ProjectArchaeologist\Detectors;

/**
 * Scans for declared classes, interfaces, traits, and functions/methods with zero call-sites across the project.
 */
class DeadCodeDetector
{
    /**
     * Identifies dead classes, functions, and methods across all parsed files.
     *
     * @param array<string, array<string, mixed>> $astPerFile Map of relative path => AST data
     * @param array<string, string> $allFileContents Raw contents of all files for text-level call cross-referencing
     * @return array<string, array<string, mixed>>
     */
    public function detect(array $astPerFile, array $allFileContents): array
    {
        $declaredClasses = [];
        $declaredFunctions = [];
        $declaredMethods = [];

        // Global call indices
        $allCalls = [];
        $allInstantiations = [];

        foreach ($astPerFile as $relPath => $ast) {
            foreach ($ast['classes'] ?? [] as $classKey => $classInfo) {
                $declaredClasses[$classKey] = $classInfo;
            }
            foreach ($ast['functions'] ?? [] as $funcKey => $funcInfo) {
                $declaredFunctions[$funcKey] = $funcInfo;
            }
            foreach ($ast['methods'] ?? [] as $methodKey => $methodInfo) {
                // Ignore magic methods (__construct, __destruct, __toString, etc.)
                if (str_starts_with($methodInfo['name'], '__')) {
                    continue;
                }
                $declaredMethods[$methodKey] = $methodInfo;
            }

            foreach ($ast['calls'] ?? [] as $call) {
                $allCalls[strtolower($call['name'])] = true;
            }
            foreach ($ast['instantiations'] ?? [] as $inst) {
                $allInstantiations[strtolower($inst['class'])] = true;
            }
        }

        // Combine all source text for regex fallback (handling dynamic calls, variable functions, templates)
        $combinedText = '';
        foreach ($allFileContents as $content) {
            $combinedText .= ' ' . $content;
        }

        $deadClasses = [];
        $deadFunctions = [];
        $deadMethods = [];

        // 1. Detect dead classes
        foreach ($declaredClasses as $fqcn => $info) {
            $shortName = $info['name'];
            $lowerShort = strtolower($shortName);
            $lowerFqcn = strtolower($fqcn);

            // Check if instantiated or referenced statically
            $isInstantiated = isset($allInstantiations[$lowerShort]) || isset($allInstantiations[$lowerFqcn]);
            $isReferencedInText = preg_match_all('/\b' . preg_quote($shortName, '/') . '\b/i', $combinedText) > 1;

            if (!$isInstantiated && !$isReferencedInText) {
                $deadClasses[$fqcn] = [
                    'name' => $shortName,
                    'fqcn' => $fqcn,
                    'file' => $info['file'],
                    'line' => $info['line'],
                    'reason' => 'Zero instantiations or static references across codebase',
                ];
            }
        }

        // 2. Detect dead functions
        foreach ($declaredFunctions as $fqfn => $info) {
            $funcName = $info['name'];
            $lowerFunc = strtolower($funcName);

            $isCalled = isset($allCalls[$lowerFunc]);
            $isReferencedInText = preg_match_all('/\b' . preg_quote($funcName, '/') . '\s*\(/i', $combinedText) > 1;

            if (!$isCalled && !$isReferencedInText) {
                $deadFunctions[$fqfn] = [
                    'name' => $funcName,
                    'fqfn' => $fqfn,
                    'file' => $info['file'],
                    'line' => $info['line'],
                    'reason' => 'Zero call-sites detected across all files',
                ];
            }
        }

        // 3. Detect dead methods
        foreach ($declaredMethods as $methodKey => $info) {
            $methodName = $info['name'];
            $lowerMethod = strtolower($methodName);

            $isCalled = isset($allCalls[$lowerMethod]);
            $isReferencedInText = preg_match_all('/(?:->|::)' . preg_quote($methodName, '/') . '\s*\(/i', $combinedText) > 1;

            if (!$isCalled && !$isReferencedInText) {
                $deadMethods[$methodKey] = [
                    'class' => $info['class'],
                    'method' => $methodName,
                    'file' => $info['file'],
                    'line' => $info['line'],
                    'reason' => 'No invoking call-sites found for this method',
                ];
            }
        }

        return [
            'classes' => $deadClasses,
            'functions' => $deadFunctions,
            'methods' => $deadMethods,
        ];
    }
}
