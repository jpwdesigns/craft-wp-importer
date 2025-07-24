<?php

namespace jpwdesigns\wpimporter\controllers;

use Craft;
use craft\web\Controller;
use jpwdesigns\wpimporter\WpImporter;

/**
 * Settings Controller
 */
class SettingsController extends Controller
{
    public function actionIndex(): \yii\web\Response
    {
        $plugin = WpImporter::getInstance();
        $settings = $plugin->getSettings();

        return $this->renderTemplate('wp-importer/_settings/index.twig', [
            'plugin' => $plugin,
            'settings' => $settings,
        ]);
    }

    public function actionImport(): \yii\web\Response
    {
        $volumes = Craft::$app->volumes->getAllVolumes();
        $volumeOptions = [];
        
        foreach ($volumes as $volume) {
            $volumeOptions[$volume->id] = $volume->name;
        }

        return $this->renderTemplate('wp-importer/_settings/import.twig', [
            'volumeOptions' => $volumeOptions,
        ]);
    }

    public function actionSaveSettings(): ?\yii\web\Response
    {
        $this->requirePostRequest();
        
        $plugin = WpImporter::getInstance();
        $settings = $plugin->getSettings();
        
        $settings->setAttributes(Craft::$app->request->getBodyParam('settings', []), false);
        
        if (!$plugin->saveSettings($settings)) {
            Craft::$app->session->setError(Craft::t('wp-importer', 'Couldn\'t save settings.'));
            return $this->renderTemplate('wp-importer/_settings/index.twig', [
                'plugin' => $plugin,
                'settings' => $settings,
            ]);
        }

        Craft::$app->session->setNotice(Craft::t('wp-importer', 'Settings saved.'));
        return $this->redirectToPostedUrl();
    }
}
