<?php

declare(strict_types=1);

namespace EidCloud\ProjectArchaeologist\Generators;

use EidCloud\ProjectArchaeologist\Model\ExcavationResult;

/**
 * Generates automated high-fidelity Markdown documentation:
 * - PROJECT.md
 * - ARCHITECTURE.md (including Mermaid flowcharts)
 * - DATABASE.md
 * - API.md
 * - REFACTORING_ROADMAP.md
 */
class DocumentationGenerator
{
    /**
     * Synthesizes all documentation files into target directory.
     *
     * @return array<string, string> Map of filename => generated absolute path
     */
    public function generateAll(ExcavationResult $result, string $outputDir): array
    {
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        $docs = [
            'PROJECT.md' => $this->generateProjectOverview($result),
            'ARCHITECTURE.md' => $this->generateArchitectureDoc($result),
            'DATABASE.md' => $this->generateDatabaseDoc($result),
            'API.md' => $this->generateApiDoc($result),
            'REFACTORING_ROADMAP.md' => $this->generateRefactoringRoadmap($result),
        ];

        $generated = [];
        foreach ($docs as $filename => $content) {
            $dest = rtrim($outputDir, '/\\') . DIRECTORY_SEPARATOR . $filename;
            file_put_contents($dest, $content);
            $generated[$filename] = $dest;
        }

        return $generated;
    }

    public function generateMermaidDiagram(ExcavationResult $result): string
    {
        $mermaid = "flowchart TD\n";
        $mermaid .= "    subgraph EntryPoints[\"🌐 System Entry Points\"]\n";
        
        $entryIdx = 0;
        foreach (array_slice($result->entryPoints, 0, 10) as $ep) {
            $entryIdx++;
            $nodeId = "EP_" . $entryIdx;
            $mermaid .= "        {$nodeId}[\"📄 " . htmlspecialchars($ep['file'], ENT_QUOTES) . "\"]\n";
        }
        if ($entryIdx === 0) {
            $mermaid .= "        EP_1[\"📄 index.php (Default Entry)\"]\n";
        }
        $mermaid .= "    end\n\n";

        $mermaid .= "    subgraph CoreLayer[\"⚙️ Core Architecture & Routing\"]\n";
        $mermaid .= "        Router[\"🔀 Route Dispatcher / Request Handler\"]\n";
        $mermaid .= "        Controllers[\"🎮 Modules & Action Handlers\"]\n";
        $mermaid .= "        Router --> Controllers\n";
        $mermaid .= "    end\n\n";

        $mermaid .= "    subgraph DataLayer[\"💾 Database & Data Storage\"]\n";
        $tables = array_slice($result->database['tables'] ?? [], 0, 8);
        if (!empty($tables)) {
            $tIdx = 0;
            foreach ($tables as $t) {
                $tIdx++;
                $mermaid .= "        DB_{$tIdx}[(\"🗄️ " . htmlspecialchars($t, ENT_QUOTES) . "\")]\n";
            }
        } else {
            $mermaid .= "        DB_STORE[(\"🗄️ Database Tables\")]\n";
        }
        $mermaid .= "    end\n\n";

        $mermaid .= "    subgraph ThirdParty[\"📦 External Dependencies\"]\n";
        $deps = array_slice($result->dependencies, 0, 6);
        if (!empty($deps)) {
            $dIdx = 0;
            foreach ($deps as $d) {
                $dIdx++;
                $mermaid .= "        DEP_{$dIdx}[\"📦 " . htmlspecialchars($d['package'], ENT_QUOTES) . "\"]\n";
            }
        } else {
            $mermaid .= "        DEP_1[\"📦 Native PHP runtime\"]\n";
        }
        $mermaid .= "    end\n\n";

        $mermaid .= "    EntryPoints --> Router\n";
        $mermaid .= "    Controllers --> DataLayer\n";
        $mermaid .= "    Controllers -.-> ThirdParty\n";

        return $mermaid;
    }

    public function generateProjectOverview(ExcavationResult $result): string
    {
        $sum = $result->summary;
        $totalFiles = count($result->files);
        $totalLines = array_sum(array_column($result->files, 'lines'));
        $langs = $sum['languages'] ?? [];

        $doc = "# 🏛️ Excavated Project Overview\n\n";
        $doc .= "> **Target Path**: `{$result->projectPath}`  \n";
        $doc .= "> **Excavation Date**: " . date('Y-m-d H:i:s') . "  \n";
        $doc .= "> **Engine**: EidCloud Project Archaeologist v1.0.0  \n";
        $doc .= "> **Duration**: " . round($result->executionTime, 4) . " seconds\n\n";

        $doc .= "## 📊 Codebase Vital Statistics\n\n";
        $doc .= "| Metric | Value |\n";
        $doc .= "| :--- | :--- |\n";
        $doc .= "| **Total Scanned Files** | " . number_format($totalFiles) . " |\n";
        $doc .= "| **Total Lines of Code (LOC)** | " . number_format($totalLines) . " |\n";
        $doc .= "| **Entry Points Identified** | " . count($result->entryPoints) . " |\n";
        $doc .= "| **HTTP / CLI Routes** | " . count($result->routes) . " |\n";
        $doc .= "| **Database Tables Discovered** | " . count($result->database['tables'] ?? []) . " |\n";
        $doc .= "| **Dead Code Candidates** | " . (count($result->deadCode['classes'] ?? []) + count($result->deadCode['functions'] ?? []) + count($result->deadCode['methods'] ?? [])) . " |\n";
        $doc .= "| **Security Smells Flagged** | " . count($result->securitySmells) . " |\n\n";

        $doc .= "## 🌐 Multi-Language Breakdown\n\n";
        $doc .= "| Language / Category | Files | Total LOC |\n";
        $doc .= "| :--- | :--- | :--- |\n";
        foreach ($langs as $lang => $stat) {
            $doc .= "| **" . ucfirst($lang) . "** | {$stat['files']} | " . number_format($stat['lines']) . " |\n";
        }
        $doc .= "\n";

        $doc .= "## 📦 External Dependencies & Extensions\n\n";
        if (empty($result->dependencies)) {
            $doc .= "*Zero external vendor packages detected (self-contained legacy code).*\n\n";
        } else {
            $doc .= "| Dependency | Type | Version |\n";
            $doc .= "| :--- | :--- | :--- |\n";
            foreach ($result->dependencies as $dep) {
                $doc .= "| `{$dep['package']}` | `{$dep['type']}` | `{$dep['version']}` |\n";
            }
            $doc .= "\n";
        }

        return $doc;
    }

    public function generateArchitectureDoc(ExcavationResult $result): string
    {
        $doc = "# 🏗️ Architecture Reverse-Engineering Map\n\n";
        $doc .= "Reverse-engineered architectural topology and component interactions.\n\n";

        $doc .= "## 🗺️ Architectural Topology Diagram\n\n";
        $doc .= "```mermaid\n";
        $doc .= $this->generateMermaidDiagram($result);
        $doc .= "```\n\n";

        $doc .= "## 🚪 Ingress & Entry Points\n\n";
        if (empty($result->entryPoints)) {
            $doc .= "*No explicit top-level entry points isolated.*\n\n";
        } else {
            $doc .= "| File | Type | Archaeological Detection Clues |\n";
            $doc .= "| :--- | :--- | :--- |\n";
            foreach ($result->entryPoints as $ep) {
                $reasons = implode(', ', $ep['reasons'] ?? []);
                $doc .= "| `{$ep['file']}` | `{$ep['type']}` | {$reasons} |\n";
            }
            $doc .= "\n";
        }

        $doc .= "## 🔀 Discovered Routes & Dispatch Handlers\n\n";
        if (empty($result->routes)) {
            $doc .= "*Procedural execution flow without centralized routing framework.*\n\n";
        } else {
            $doc .= "| Method | Pattern / Dispatcher | Source File | Type |\n";
            $doc .= "| :--- | :--- | :--- | :--- |\n";
            foreach (array_slice($result->routes, 0, 50) as $route) {
                $doc .= "| `{$route['method']}` | `{$route['pattern']}` | `{$route['file']}` | `{$route['type']}` |\n";
            }
            $doc .= "\n";
        }

        return $doc;
    }

    public function generateDatabaseDoc(ExcavationResult $result): string
    {
        $tables = $result->database['tables'] ?? [];
        $queries = $result->database['queries'] ?? [];

        $doc = "# 💾 Database & Storage Reverse-Engineering\n\n";
        $doc .= "Dissected schema entities, tables, and raw SQL queries embedded across legacy source code.\n\n";

        $doc .= "## 🗄️ Discovered Entities & Tables (" . count($tables) . ")\n\n";
        if (empty($tables)) {
            $doc .= "*No structured database tables detected.*\n\n";
        } else {
            $doc .= "| Table Name | Columns / Inferred Attributes | Origin |\n";
            $doc .= "| :--- | :--- | :--- |\n";
            foreach ($tables as $table) {
                $cols = $result->database['table_columns'][$table] ?? [];
                $colStr = !empty($cols) ? implode(', ', array_slice($cols, 0, 6)) : 'Inferred from queries';
                $doc .= "| `{$table}` | {$colStr} | " . (isset($result->database['table_columns'][$table]) ? 'SQL DDL' : 'PHP Query Reference') . " |\n";
            }
            $doc .= "\n";
        }

        $doc .= "## 🔍 Embedded SQL Queries Sample (" . count($queries) . " total)\n\n";
        if (empty($queries)) {
            $doc .= "*No embedded SQL queries detected.*\n\n";
        } else {
            $doc .= "| Source File | Table Target | Query Fragment |\n";
            $doc .= "| :--- | :--- | :--- |\n";
            foreach (array_slice($queries, 0, 30) as $q) {
                $sanitized = str_replace(["\n", "\r", "|"], [" ", "", "\\|"], $q['query']);
                $doc .= "| `{$q['file']}` | `{$q['table']}` | `{$sanitized}` |\n";
            }
            $doc .= "\n";
        }

        return $doc;
    }

    public function generateApiDoc(ExcavationResult $result): string
    {
        $doc = "# 📡 API Surface & External Interfaces\n\n";
        $doc .= "Exposed REST endpoints, AJAX services, webhooks, and interface contracts.\n\n";

        $apiRoutes = array_filter($result->routes, function ($r) {
            return str_contains(strtolower($r['file']), 'api') ||
                   str_contains(strtolower($r['pattern']), 'api') ||
                   $r['type'] === 'Express/NodeJS Route' ||
                   $r['type'] === 'Frontend AJAX / Fetch Call';
        });

        if (empty($apiRoutes)) {
            $doc .= "### Summary\n";
            $doc .= "No dedicated REST API routes detected. Legacy systems often rely on server-rendered procedural views with direct form POSTs.\n\n";
        } else {
            $doc .= "| Interface Type | HTTP Method | Route Endpoint | Source Location |\n";
            $doc .= "| :--- | :--- | :--- | :--- |\n";
            foreach ($apiRoutes as $ar) {
                $doc .= "| `{$ar['type']}` | `{$ar['method']}` | `{$ar['pattern']}` | `{$ar['file']}` |\n";
            }
            $doc .= "\n";
        }

        return $doc;
    }

    public function generateRefactoringRoadmap(ExcavationResult $result): string
    {
        $deadClasses = $result->deadCode['classes'] ?? [];
        $deadFunctions = $result->deadCode['functions'] ?? [];
        $deadMethods = $result->deadCode['methods'] ?? [];
        $smells = $result->securitySmells;

        $doc = "# 🗺️ Modernization & Refactoring Roadmap\n\n";
        $doc .= "Prioritized archaeological action items for modernizing and securing the excavated legacy codebase.\n\n";

        $doc .= "## 🚨 Phase 1: Critical Security Remediations (Immediate Action)\n\n";
        $criticalSmells = array_filter($smells, fn($s) => ($s['severity'] ?? '') === 'CRITICAL');
        if (empty($criticalSmells)) {
            $doc .= "✅ *No critical security vulnerabilities flagged.* Great baseline!\n\n";
        } else {
            $doc .= "| Location | Vulnerability | Remediation |\n";
            $doc .= "| :--- | :--- | :--- |\n";
            foreach ($criticalSmells as $cs) {
                $doc .= "| `{$cs['file']}:{$cs['line']}` | **{$cs['title']}** | {$cs['remediation']} |\n";
            }
            $doc .= "\n";
        }

        $doc .= "## 🧹 Phase 2: Dead Code Excavation & Elimination\n\n";
        $doc .= "The following components were identified as unreferenced with zero call-sites:\n\n";
        
        if (empty($deadClasses) && empty($deadFunctions) && empty($deadMethods)) {
            $doc .= "✅ *No abandoned dead code identified.*\n\n";
        } else {
            $doc .= "### Dead Classes (" . count($deadClasses) . ")\n\n";
            foreach (array_slice($deadClasses, 0, 15) as $dc) {
                $doc .= "- [ ] **Class** `{$dc['fqcn']}` (`{$dc['file']}:{$dc['line']}`): {$dc['reason']}\n";
            }
            $doc .= "\n### Dead Functions & Methods (" . (count($deadFunctions) + count($deadMethods)) . ")\n\n";
            foreach (array_slice($deadFunctions, 0, 15) as $df) {
                $doc .= "- [ ] **Function** `{$df['fqfn']}` (`{$df['file']}:{$df['line']}`): {$df['reason']}\n";
            }
            foreach (array_slice($deadMethods, 0, 15) as $dm) {
                $doc .= "- [ ] **Method** `{$dm['class']}::{$dm['method']}` (`{$dm['file']}:{$dm['line']}`): {$dm['reason']}\n";
            }
            $doc .= "\n";
        }

        $doc .= "## ⚡ Phase 3: Architectural Modernization Steps\n\n";
        $doc .= "1. **Adopt PSR Standards**: Convert global procedural scripts into PSR-4 autoloaded domain classes.\n";
        $doc .= "2. **Abstract Database Access**: Replace raw embedded SQL statements with PDO or an ORM/Query Builder with parameterized statements.\n";
        $doc .= "3. **Unified Front Controller**: Funnel all scattered entry points into a single `public/index.php` front-controller with modern middleware.\n";
        $doc .= "4. **Automated Testing**: Introduce regression test coverage before refactoring legacy business logic.\n";

        return $doc;
    }
}
