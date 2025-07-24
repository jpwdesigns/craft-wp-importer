<?php

namespace jpwdesigns\wpimporter;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use jpwdesigns\wpimporter\models\Settings;
use jpwdesigns\wpimporter\services\InstallService;
use jpwdesigns\wpimporter\services\ImportService;
use jpwdesigns\wpimporter\services\UninstallService;
use jpwdesigns\wpimporter\variables\WpImporterVariable;
use yii\base\Event;

/**
 * WP Importer plugin
 *
 * @method static WpImporter getInstance()
 * @method Settings getSettings()
 * @author JPW Designs <https://jpwdesigns.com>
 * @copyright JPW Designs
 * @license MIT
 * @property-read InstallService $install
 * @property-read ImportService $import
 * @property-read UninstallService $uninstall
 */
class WpImporter extends Plugin
{
    public string $schemaVersion = '5.0.0';
    public bool $hasCpSettings = true;
    public bool $hasCpSection = false;

    public static function config(): array
    {
        return [
            'components' => [
                'install' => InstallService::class,
                'import' => ImportService::class,
                'uninstall' => UninstallService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        // Register services
        $this->setComponents([
            'install' => InstallService::class,
            'import' => ImportService::class,
            'uninstall' => UninstallService::class,
        ]);

        // Register CP routes
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['wp-importer'] = 'wp-importer/settings/index';
                $event->rules['wp-importer/settings'] = 'wp-importer/settings/index';
                $event->rules['wp-importer/settings/import'] = 'wp-importer/settings/import';
                $event->rules['wp-importer/import/confirm'] = 'wp-importer/import/confirm';
                $event->rules['wp-importer/import/start'] = 'wp-importer/import/start';
            }
        );

        // Register template variables
        Craft::$app->view->registerTwigExtension(new WpImporterVariable());

        Craft::info(
            Craft::t(
                'wp-importer',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    public function getSettingsResponse(): mixed
    {
        return Craft::$app->controller->redirect('wp-importer/settings');
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('wp-importer/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }

    public function beforeInstall(): void
    {
        $templatesPath = Craft::$app->path->getSiteTemplatesPath();
        
        if (!is_writable($templatesPath)) {
            throw new \Exception(Craft::t('wp-importer', 
                'Your templates folder is not writable by PHP. InstaBlog needs PHP to have permissions to create template files. Give PHP write permissions to {path} and try installing again.',
                ['path' => $templatesPath]
            ));
        }

        $assetVolumes = Craft::$app->volumes->getAllVolumes();
        
        if (empty($assetVolumes)) {
            throw new \Exception(Craft::t('wp-importer', 
                'You don\'t have any asset volumes set up. InstaBlog needs an asset volume to be defined. Please create an asset volume and try installing again.'
            ));
        }
    }

    public function afterInstall(): void
    {
        $this->install->run();
    }

    public function beforeUninstall(): void
    {
        $this->uninstall->run();
    }
}
