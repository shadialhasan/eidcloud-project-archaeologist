<?php

declare(strict_types=1);

namespace EidCloud\ProjectArchaeologist\Core;

/**
 * Recursive filesystem crawler with ignore filtering.
 */
class FileCrawler
{
    /** @var array<string> */
    private array $defaultIgnoredDirs = [
        '.git', '.svn', '.hg', 'node_modules', 'vendor',
        '.idea', '.vscode', 'coverage', '.phpunit.cache', 'tmp', 'cache'
    ];

    /** @var array<string> */
    private array $supportedExtensions = [
        'php', 'inc', 'module', 'install',
        'js', 'jsx', 'ts', 'tsx', 'mjs',
        'sql', 'ddl',
        'css', 'scss', 'sass', 'less',
        'sh', 'bash', 'zsh', 'bat', 'cmd', 'ps1',
        'html', 'htm', 'json', 'xml', 'yaml', 'yml', 'ini', 'env', 'conf'
    ];

    /**
     * @param array<string> $customIgnoredDirs
     */
    public function __construct(private array $customIgnoredDirs = [])
    {
    }

    /**
     * Crawls target directory and returns categorized list of files.
     *
     * @return array<string, array<string, mixed>> Map of relative path => file info
     */
    public function crawl(string $baseDir): array
    {
        $realBase = realpath($baseDir);
        if ($realBase === false || !is_dir($realBase)) {
            throw new \InvalidArgumentException("Target path is not a valid directory: {$baseDir}");
        }

        $files = [];
        $ignored = array_merge($this->defaultIgnoredDirs, $this->customIgnoredDirs);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($realBase, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS),
                function (\SplFileInfo $current) use ($ignored): bool {
                    if ($current->isDir()) {
                        return !in_array($current->getFilename(), $ignored, true);
                    }
                    return true;
                }
            ),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            if (!$item->isFile()) {
                continue;
            }

            $ext = strtolower($item->getExtension());
            $filename = $item->getFilename();

            // Support extensionless scripts like executable shell scripts or special filenames
            if ($ext === '' && (str_starts_with($filename, '.') || is_executable($item->getPathname()))) {
                $category = 'shell';
            } elseif (!in_array($ext, $this->supportedExtensions, true)) {
                continue;
            } else {
                $category = $this->categorizeExtension($ext);
            }

            $fullPath = $item->getPathname();
            $normFull = str_replace('\\', '/', $fullPath);
            $normBase = str_replace('\\', '/', $realBase);
            $relPath = ltrim(substr($normFull, strlen($normBase)), '/');

            $files[$relPath] = [
                'absolute_path' => $fullPath,
                'relative_path' => $relPath,
                'extension' => $ext,
                'filename' => $filename,
                'category' => $category,
                'size' => $item->getSize(),
                'mtime' => $item->getMTime(),
            ];
        }

        ksort($files);
        return $files;
    }

    private function categorizeExtension(string $ext): string
    {
        return match ($ext) {
            'php', 'inc', 'module', 'install' => 'php',
            'js', 'jsx', 'ts', 'tsx', 'mjs' => 'javascript',
            'sql', 'ddl' => 'sql',
            'css', 'scss', 'sass', 'less' => 'css',
            'sh', 'bash', 'zsh', 'bat', 'cmd', 'ps1' => 'shell',
            'json', 'xml', 'yaml', 'yml', 'ini', 'env', 'conf' => 'config',
            default => 'other',
        };
    }
}
