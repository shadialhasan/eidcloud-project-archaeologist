<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Core/Autoloader.php';
\EidCloud\ProjectArchaeologist\Core\Autoloader::register();

require_once __DIR__ . '/ArchaeologistTest.php';

echo "\n";
echo "======================================================================\n";
echo "🧪 Running EidCloud Project Archaeologist Zero-Dependency Test Suite\n";
echo "======================================================================\n\n";

try {
    $tester = new \EidCloud\ProjectArchaeologist\Tests\ArchaeologistTest();
    $tester->runAll();
    echo "\n🎉 100% of tests passed successfully!\n\n";
    exit(0);
} catch (\Throwable $e) {
    echo "\n❌ TEST FAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " on line " . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n\n";
    exit(1);
}
