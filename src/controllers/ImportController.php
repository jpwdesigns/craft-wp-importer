<?php

namespace jpwdesigns\wpimporter\controllers;

use Craft;
use craft\web\Controller;
use craft\web\UploadedFile;
use jpwdesigns\wpimporter\WpImporter;
use jpwdesigns\wpimporter\models\ImportModel;
use jpwdesigns\wpimporter\jobs\ImportWordpressJob;
use jpwdesigns\wpimporter\jobs\BackupJob;

/**
 * Import Controller
 */
class ImportController extends Controller
{
    public function actionConfirm(): \yii\web\Response
    {
        $this->requirePostRequest();
        $this->requireAdmin();

        $importSettings = Craft::$app->request->getBodyParam('import', []);
        $uploadedFile = UploadedFile::getInstanceByName('file');

        if (!$uploadedFile) {
            Craft::$app->session->setError(Craft::t('wp-importer', 'Please select a WordPress XML export file to upload.'));
            return $this->redirect('wp-importer/settings/import');
        }

        // Validate file type
        $model = new ImportModel();
        $model->filetype = $uploadedFile->type;

        if (!$model->validate()) {
            Craft::$app->session->setError(Craft::t('wp-importer', 'Invalid file type. Expected XML but got: {type}', [
                'type' => $model->filetype
            ]));
            return $this->redirect('wp-importer/settings/import');
        }

        // Save uploaded file
        $storagePath = Craft::$app->path->getStoragePath() . '/wp-importer/';
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        $filePath = $storagePath . $uploadedFile->name;
        if (!$uploadedFile->saveAs($filePath)) {
            Craft::$app->session->setError(Craft::t('wp-importer', 'Could not upload file. Check upload_max_filesize or other PHP settings.'));
            return $this->redirect('wp-importer/settings/import');
        }

        try {
            // Parse and prepare import data
            $importService = WpImporter::getInstance()->import;
            $preparedData = $importService->prepareData($filePath);

            return $this->renderTemplate('wp-importer/_settings/confirm.twig', [
                'importSettings' => $importSettings,
                'filePath' => $filePath,
                'preparedData' => $preparedData,
                'volumes' => $this->getVolumeOptions(),
            ]);

        } catch (\Exception $e) {
            @unlink($filePath);
            Craft::$app->session->setError(Craft::t('wp-importer', 'Error parsing WordPress file: {error}', [
                'error' => $e->getMessage()
            ]));
            return $this->redirect('wp-importer/settings/import');
        }
    }

    public function actionStart(): \yii\web\Response
    {
        $this->requirePostRequest();
        $this->requireAdmin();

        $filePath = Craft::$app->request->getBodyParam('filePath');
        $backup = (bool)Craft::$app->request->getBodyParam('backup', false);
        $assetVolumeId = (int)Craft::$app->request->getBodyParam('assetVolumeId');
        $importSettings = Craft::$app->request->getBodyParam('importSettings', []);

        $jobSettings = [
            'filePath' => $filePath,
            'backup' => $backup,
            'assetVolumeId' => $assetVolumeId,
            'importSettings' => $importSettings,
        ];

        $queue = Craft::$app->queue;

        // Create backup job if requested
        if ($backup) {
            $queue->push(new BackupJob($jobSettings));
        }

        // Create import job
        $job = new ImportWordpressJob($jobSettings);
        $jobId = $queue->push($job);

        Craft::$app->session->setNotice(Craft::t('wp-importer', 'Import process started.'));
        
        return $this->redirect('wp-importer/settings/import?job=' . $jobId);
    }

    private function getVolumeOptions(): array
    {
        $volumes = Craft::$app->volumes->getAllVolumes();
        $options = [];
        
        foreach ($volumes as $volume) {
            $options[$volume->id] = $volume->name;
        }
        
        return $options;
    }
}
