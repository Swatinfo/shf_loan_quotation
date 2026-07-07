<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Guardrail: every @extends/@include and every asset() path inside
 * resources/views/newtheme/** must stay within the newtheme folder
 * (or a genuinely shared root: favicon/, images/, fonts/, manifest.json,
 * offline.html) so the app never depends on old-theme paths.
 */
class NewthemeIsolationTest extends TestCase
{
    /** Asset prefixes that both themes are allowed to share. */
    private const SHARED_ASSET_PREFIXES = [
        'newtheme/',
        'favicon/',
        'images/',
        'fonts/',
        'manifest.json',
        'offline.html',
    ];

    public function test_newtheme_blades_only_reference_newtheme_views(): void
    {
        $leaks = $this->collectLeaks(function (string $contents): array {
            preg_match_all(
                "/@(?:extends|include|includeIf|includeWhen|includeUnless|includeFirst|each)\s*\(\s*['\"]([^'\"]+)['\"]/",
                $contents,
                $matches
            );

            return array_filter(
                $matches[1],
                fn ($view) => ! str_starts_with($view, 'newtheme.')
            );
        });

        $this->assertSame(
            [],
            $leaks,
            "newtheme files must only reference 'newtheme.*' views. Leaks:\n- ".implode("\n- ", $leaks)
        );
    }

    public function test_newtheme_blades_only_reference_newtheme_assets(): void
    {
        $leaks = $this->collectLeaks(function (string $contents): array {
            preg_match_all(
                "/asset\s*\(\s*['\"]([^'\"]+)['\"]/",
                $contents,
                $matches
            );

            return array_filter($matches[1], function ($path) {
                foreach (self::SHARED_ASSET_PREFIXES as $allowed) {
                    if (str_starts_with($path, $allowed)) {
                        return false;
                    }
                }

                return true;
            });
        });

        $this->assertSame(
            [],
            $leaks,
            "newtheme files must only load assets from newtheme/ (plus favicon/images/fonts/manifest). Leaks:\n- ".implode("\n- ", $leaks)
        );
    }

    /**
     * Walks every .blade.php under resources/views/newtheme and applies
     * the supplied extractor to each file's contents. Returns a flat list
     * of "relative/path.blade.php → offending-token" strings.
     *
     * @param  callable(string): iterable<string>  $extractor
     * @return array<int, string>
     */
    private function collectLeaks(callable $extractor): array
    {
        $root = resource_path('views/newtheme');
        $this->assertDirectoryExists($root, 'newtheme folder missing');

        $leaks = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            $relative = str_replace($root.DIRECTORY_SEPARATOR, '', $file->getPathname());

            foreach ($extractor($contents) as $token) {
                $leaks[] = $relative.' → '.$token;
            }
        }

        return $leaks;
    }
}
