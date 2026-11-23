<?php

declare(strict_types=1);

namespace App\Services\Coverage;

use RuntimeException;
use SimpleXMLElement;

class CloverCoverageParser
{
    public function parse(string $cloverPath): float
    {
        if (! is_file($cloverPath)) {
            throw new RuntimeException("Coverage report file does not exist: {$cloverPath}");
        }

        $contents = file_get_contents($cloverPath);

        if ($contents === false) {
            throw new RuntimeException("Coverage report file could not be read: {$cloverPath}");
        }

        $xml = $this->loadXml($contents);

        if ($xml === null) {
            throw new RuntimeException("Coverage report is not valid XML: {$cloverPath}");
        }

        $metrics = $xml->xpath('//file/metrics');

        if (! is_array($metrics) || $metrics === []) {
            throw new RuntimeException("Coverage report contains no file metrics: {$cloverPath}");
        }

        $totalStatements = 0;
        $coveredStatements = 0;

        foreach ($metrics as $metric) {
            $totalStatements += (int) $metric['statements'];
            $coveredStatements += (int) $metric['coveredstatements'];
        }

        if ($totalStatements === 0) {
            throw new RuntimeException("Coverage report has no statements: {$cloverPath}");
        }

        return round($coveredStatements / $totalStatements * 100, 2);
    }

    private function loadXml(string $contents): ?SimpleXMLElement
    {
        $previousSetting = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($contents);
        libxml_clear_errors();
        libxml_use_internal_errors($previousSetting);

        return $xml === false ? null : $xml;
    }
}
