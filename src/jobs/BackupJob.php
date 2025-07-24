<?php

namespace jpwdesigns\wpimporter\jobs;

use Craft;
use craft\queue\BaseJob;

/**
 * Backup Job
 */
class BackupJob extends BaseJob
{
    public array $settings = [];

    public function execute($queue): void
    {
        $this->setProgress($queue, 0.1, 'Creating database backup...');

        try {
            // Create a database backup
            $backupPath = $this->createDatabaseBackup();
            
            $this->setProgress($queue, 0.5, 'Backing up assets...');
            
            // Optionally backup assets
            $this->backupAssets();
            
            $this->setProgress($queue, 1, 'Backup completed successfully!');
            
            Craft::info("Backup created at: {$backupPath}", __METHOD__);
            
        } catch (\Exception $e) {
            Craft::error("Backup failed: " . $e->getMessage(), __METHOD__);
            throw $e;
        }
    }

    protected function defaultDescription(): string
    {
        return Craft::t('wp-importer', 'Creating backup before import');
    }

    private function createDatabaseBackup(): string
    {
        $backupPath = Craft::$app->path->getStoragePath() . '/backups/';
        
        if (!is_dir($backupPath)) {
            mkdir($backupPath, 0755, true);
        }

        $filename = 'wp_importer_backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filePath = $backupPath . $filename;

        // Use Craft's built-in database backup
        $backup = Craft::$app->db->backup();
        file_put_contents($filePath, $backup);

        return $filePath;
    }

    private function backupAssets(): void
    {
        // This is a placeholder for asset backup functionality
        // In a real implementation, you might want to backup the assets directory
        Craft::info('Asset backup functionality not implemented', __METHOD__);
    }
}
