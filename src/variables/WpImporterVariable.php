<?php

namespace jpwdesigns\wpimporter\variables;

use Craft;
use jpwdesigns\wpimporter\WpImporter;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * WP Importer Variable
 */
class WpImporterVariable extends AbstractExtension implements GlobalsInterface
{
    public function getGlobals(): array
    {
        return [
            'wpImporter' => $this,
        ];
    }

    public function getSettings(): array
    {
        $plugin = WpImporter::getInstance();
        return $plugin->getSettings()->toArray();
    }

    public function getVolumes(): array
    {
        $volumes = Craft::$app->volumes->getAllVolumes();
        $response = [];

        foreach ($volumes as $volume) {
            $response[$volume->id] = $volume->name;
        }
        
        return $response;
    }

    public function truncate(string $text, int $limit = 100, string $suffix = '...'): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return mb_substr($text, 0, $limit) . $suffix;
    }
}
