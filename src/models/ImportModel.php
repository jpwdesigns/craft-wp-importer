<?php

namespace jpwdesigns\wpimporter\models;

use craft\base\Model;

/**
 * WP Importer Import Model
 */
class ImportModel extends Model
{
    public string $filetype = '';
    public bool $backup = false;
    public int $assetVolumeId = 0;
    public array $importData = [];

    // Supported file types
    public const TYPE_XML = 'text/xml';
    public const TYPE_XML_APP = 'application/xml';

    public function defineRules(): array
    {
        return [
            [['filetype'], 'required'],
            [['filetype'], 'in', 'range' => [self::TYPE_XML, self::TYPE_XML_APP]],
            [['backup'], 'boolean'],
            [['assetVolumeId'], 'integer', 'min' => 1],
            [['importData'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'filetype' => 'File Type',
            'backup' => 'Create Backup',
            'assetVolumeId' => 'Asset Volume',
            'importData' => 'Import Data',
        ];
    }
}
