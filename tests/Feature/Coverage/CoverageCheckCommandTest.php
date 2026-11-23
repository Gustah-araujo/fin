<?php

declare(strict_types=1);

namespace Tests\Feature\Coverage;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CoverageCheckCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir().'/fin-coverage-check-'.uniqid('', true);
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    public function test_passes_when_coverage_equals_baseline(): void
    {
        $cloverPath = $this->createFile('clover.xml', $this->makeClover(100, 80));
        $baselinePath = $this->createFile('baseline.json', json_encode(['lines_percent' => 80.0]));

        $this->artisan('coverage:check', [
            '--file' => $cloverPath,
            '--baseline' => $baselinePath,
        ])->assertSuccessful();
    }

    public function test_passes_when_coverage_increases(): void
    {
        $cloverPath = $this->createFile('clover.xml', $this->makeClover(100, 85));
        $baselinePath = $this->createFile('baseline.json', json_encode(['lines_percent' => 80.0]));

        $this->artisan('coverage:check', [
            '--file' => $cloverPath,
            '--baseline' => $baselinePath,
        ])->expectsOutputToContain('85%')->assertSuccessful();
    }

    public function test_fails_when_coverage_decreases(): void
    {
        $cloverPath = $this->createFile('clover.xml', $this->makeClover(100, 75));
        $baselinePath = $this->createFile('baseline.json', json_encode(['lines_percent' => 80.0]));

        $exitCode = Artisan::call('coverage:check', [
            '--file' => $cloverPath,
            '--baseline' => $baselinePath,
        ]);

        $this->assertSame(1, $exitCode);

        $output = Artisan::output();

        $this->assertStringContainsString('75%', $output);
        $this->assertStringContainsString('80%', $output);
    }

    public function test_fails_gracefully_on_malformed_xml(): void
    {
        $cloverPath = $this->createFile('clover.xml', 'not xml at all');
        $baselinePath = $this->createFile('baseline.json', json_encode(['lines_percent' => 80.0]));

        $this->artisan('coverage:check', [
            '--file' => $cloverPath,
            '--baseline' => $baselinePath,
        ])->expectsOutputToContain('Coverage report')->assertFailed();
    }

    public function test_fails_when_baseline_file_is_missing(): void
    {
        $cloverPath = $this->createFile('clover.xml', $this->makeClover(100, 80));

        $this->artisan('coverage:check', [
            '--file' => $cloverPath,
            '--baseline' => $this->tempDir.'/does-not-exist.json',
        ])->expectsOutputToContain('missing or invalid')->assertFailed();
    }

    public function test_update_flag_writes_new_baseline(): void
    {
        $cloverPath = $this->createFile('clover.xml', $this->makeClover(8, 3));
        $baselinePath = $this->tempDir.'/baseline.json';

        $this->artisan('coverage:check', [
            '--file' => $cloverPath,
            '--baseline' => $baselinePath,
            '--update' => true,
        ])->assertSuccessful();

        $this->assertFileExists($baselinePath);

        $decoded = json_decode((string) file_get_contents($baselinePath), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(37.5, $decoded['lines_percent']);
    }

    private function makeClover(int $statements, int $covered): string
    {
        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <coverage generated="1700000000">
              <project name="Fin" timestamp="1700000000">
                <file name="/app/Models/Example.php">
                  <metrics statements="{$statements}" coveredstatements="{$covered}" conditionals="0" coveredconditionals="0" methods="0" coveredmethods="0" elements="{$statements}" coveredelements="{$covered}"/>
                </file>
              </project>
            </coverage>
            XML;
    }

    private function createFile(string $name, string $content): string
    {
        $path = $this->tempDir.'/'.$name;

        file_put_contents($path, $content);

        return $path;
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (glob($directory.'/*') ?: [] as $item) {
            if (is_dir($item)) {
                $this->deleteDirectory($item);

                continue;
            }

            unlink($item);
        }

        rmdir($directory);
    }
}
