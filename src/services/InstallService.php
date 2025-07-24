<?php

namespace jpwdesigns\wpimporter\services;

use Craft;
use craft\base\Component;
use craft\elements\Entry;
use craft\fields\Assets;
use craft\fields\Categories;
use craft\fields\Tags;
use craft\models\CategoryGroup;
use craft\models\FieldGroup;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\Section;
use craft\models\TagGroup;
use craft\models\EntryType;
use craft\ckeditor\Field as CKEditorField;
use craft\fields\PlainText;
use yii\web\ServerErrorHttpException;

/**
 * Install Service
 */
class InstallService extends Component
{
    public function run(): bool
    {
        try {
            $this->createInstaBlogContent();
            $this->copyTemplates();
            
            Craft::info('InstaBlog installed successfully', __METHOD__);
            return true;
        } catch (\Exception $e) {
            Craft::error('InstaBlog installation failed: ' . $e->getMessage(), __METHOD__);
            throw new ServerErrorHttpException('InstaBlog installation failed: ' . $e->getMessage());
        }
    }

    private function createInstaBlogContent(): void
    {
        $fieldsService = Craft::$app->getFields();
        $entriesService = Craft::$app->getEntries();
        $sectionsService = Craft::$app->getSections();
        $categoriesService = Craft::$app->getCategories();
        $tagsService = Craft::$app->getTags();
        $usersService = Craft::$app->getUsers();
        $sitesService = Craft::$app->getSites();

        // Create InstaBlog field group
        $fieldGroup = new FieldGroup();
        $fieldGroup->name = 'InstaBlog';
        if (!$fieldsService->saveGroup($fieldGroup)) {
            throw new \Exception('Could not save InstaBlog field group');
        }

        // Create tag group
        $tagGroup = new TagGroup();
        $tagGroup->name = 'InstaBlog Tags';
        $tagGroup->handle = 'instaBlogTags';
        if (!$tagsService->saveTagGroup($tagGroup)) {
            throw new \Exception('Could not save InstaBlog tag group');
        }

        // Create fields
        $bodyField = $this->createBodyField($fieldGroup);
        $assetField = $this->createAssetField($fieldGroup);
        $socialFields = $this->createSocialFields($fieldGroup);
        $tagsField = $this->createTagsField($fieldGroup, $tagGroup);

        // Create category group
        $categoryGroup = $this->createCategoryGroup($bodyField);
        $categoriesField = $this->createCategoriesField($fieldGroup, $categoryGroup);

        // Update user field layout with social fields
        $this->updateUserFieldLayout($socialFields);

        // Create InstaBlog section
        $section = $this->createInstaBlogSection();
        
        // Create entry type with field layout
        $this->createEntryTypeLayout($section, [$bodyField, $assetField, $categoriesField, $tagsField]);

        // Create sample entry
        $this->createSampleEntry($section);

        // Create routes
        $this->createRoutes();
    }

    private function createBodyField(FieldGroup $fieldGroup): CKEditorField
    {
        $field = new CKEditorField();
        $field->groupId = $fieldGroup->id;
        $field->name = 'InstaBlog Body';
        $field->handle = 'instaBlogBody';
        $field->translationMethod = 'site';
        
        // CKEditor configuration
        $field->configs = ['toolbar' => ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote']];
        
        if (!Craft::$app->getFields()->saveField($field)) {
            throw new \Exception('Could not save InstaBlog Body field');
        }
        
        return $field;
    }

    private function createAssetField(FieldGroup $fieldGroup): Assets
    {
        $field = new Assets();
        $field->groupId = $fieldGroup->id;
        $field->name = 'Featured Image';
        $field->handle = 'instaBlogImage';
        $field->allowedKinds = ['image'];
        $field->limit = 1;
        
        if (!Craft::$app->getFields()->saveField($field)) {
            throw new \Exception('Could not save Featured Image field');
        }
        
        return $field;
    }

    private function createSocialFields(FieldGroup $fieldGroup): array
    {
        $socialFields = [];
        $socialNetworks = [
            'Facebook' => 'instaBlogFacebook',
            'Twitter' => 'instaBlogTwitter', 
            'Google+' => 'instaBlogGooglePlus',
            'LinkedIn' => 'instaBlogLinkedin'
        ];

        foreach ($socialNetworks as $name => $handle) {
            $field = new PlainText();
            $field->groupId = $fieldGroup->id;
            $field->name = $name;
            $field->handle = $handle;
            
            if (!Craft::$app->getFields()->saveField($field)) {
                throw new \Exception("Could not save {$name} field");
            }
            
            $socialFields[] = $field;
        }

        return $socialFields;
    }

    private function createTagsField(FieldGroup $fieldGroup, TagGroup $tagGroup): Tags
    {
        $field = new Tags();
        $field->groupId = $fieldGroup->id;
        $field->name = 'InstaBlog Tags';
        $field->handle = 'instaBlogTags';
        $field->source = "taggroup:{$tagGroup->uid}";
        
        if (!Craft::$app->getFields()->saveField($field)) {
            throw new \Exception('Could not save InstaBlog Tags field');
        }
        
        return $field;
    }

    private function createCategoryGroup($bodyField): CategoryGroup
    {
        $categoryGroup = new CategoryGroup();
        $categoryGroup->name = 'InstaBlog Categories';
        $categoryGroup->handle = 'instaBlogCategories';
        $categoryGroup->maxLevels = 1;

        // Set up site settings
        $siteSettings = [];
        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $siteSettings[$site->id] = [
                'siteId' => $site->id,
                'hasUrls' => true,
                'uriFormat' => 'blog/category/{slug}',
                'template' => 'blog/category',
            ];
        }
        $categoryGroup->setSiteSettings($siteSettings);

        // Create field layout
        $fieldLayout = new FieldLayout();
        $fieldLayout->type = CategoryGroup::class;
        
        $tab = new FieldLayoutTab();
        $tab->name = 'Content';
        $tab->elements = [
            [
                'type' => 'craft\\fieldlayoutelements\\CustomField',
                'fieldUid' => $bodyField->uid,
            ],
        ];
        
        $fieldLayout->setTabs([$tab]);
        $categoryGroup->setFieldLayout($fieldLayout);

        if (!Craft::$app->getCategories()->saveGroup($categoryGroup)) {
            throw new \Exception('Could not save InstaBlog category group');
        }

        return $categoryGroup;
    }

    private function createCategoriesField(FieldGroup $fieldGroup, CategoryGroup $categoryGroup): Categories
    {
        $field = new Categories();
        $field->groupId = $fieldGroup->id;
        $field->name = 'InstaBlog Categories';
        $field->handle = 'instaBlogCategories';
        $field->source = "group:{$categoryGroup->uid}";
        
        if (!Craft::$app->getFields()->saveField($field)) {
            throw new \Exception('Could not save InstaBlog Categories field');
        }
        
        return $field;
    }

    private function updateUserFieldLayout(array $socialFields): void
    {
        $fieldsService = Craft::$app->getFields();
        
        // Get current user field layout
        $layout = $fieldsService->getLayoutByType(\craft\elements\User::class);
        
        if (!$layout) {
            $layout = new FieldLayout(['type' => \craft\elements\User::class]);
        }

        // Add social fields to layout
        $elements = [];
        foreach ($socialFields as $field) {
            $elements[] = [
                'type' => 'craft\\fieldlayoutelements\\CustomField',
                'fieldUid' => $field->uid,
            ];
        }

        $tab = new FieldLayoutTab();
        $tab->name = 'Social';
        $tab->elements = $elements;
        
        $tabs = $layout->getTabs();
        $tabs[] = $tab;
        $layout->setTabs($tabs);

        if (!$fieldsService->saveLayout($layout)) {
            throw new \Exception('Could not update user field layout');
        }
    }

    private function createInstaBlogSection(): Section
    {
        $section = new Section();
        $section->name = 'InstaBlog';
        $section->handle = 'instaBlog';
        $section->type = Section::TYPE_CHANNEL;
        $section->enableVersioning = true;

        // Set up site settings  
        $siteSettings = [];
        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $siteSettings[$site->id] = [
                'siteId' => $site->id,
                'enabledByDefault' => true,
                'hasUrls' => true,
                'uriFormat' => 'blog/{slug}',
                'template' => 'blog/_entry',
            ];
        }
        $section->setSiteSettings($siteSettings);

        if (!Craft::$app->getSections()->saveSection($section)) {
            throw new \Exception('Could not save InstaBlog section');
        }

        return $section;
    }

    private function createEntryTypeLayout(Section $section, array $fields): void
    {
        $entryTypes = $section->getEntryTypes();
        $entryType = $entryTypes[0] ?? new EntryType();
        
        // Create field layout
        $fieldLayout = new FieldLayout();
        $fieldLayout->type = Entry::class;
        
        $elements = [];
        foreach ($fields as $field) {
            $elements[] = [
                'type' => 'craft\\fieldlayoutelements\\CustomField',
                'fieldUid' => $field->uid,
            ];
        }

        $tab = new FieldLayoutTab();
        $tab->name = 'Content';
        $tab->elements = $elements;
        
        $fieldLayout->setTabs([$tab]);
        $entryType->setFieldLayout($fieldLayout);

        if (!Craft::$app->getSections()->saveEntryType($entryType)) {
            throw new \Exception('Could not save InstaBlog entry type');
        }
    }

    private function createSampleEntry(Section $section): void
    {
        $entryTypes = $section->getEntryTypes();
        $entryType = $entryTypes[0];
        
        $entry = new Entry();
        $entry->sectionId = $section->id;
        $entry->typeId = $entryType->id;
        $entry->authorId = Craft::$app->user->getId();
        $entry->title = 'We just installed InstaBlog!';
        $entry->setFieldValue('instaBlogBody', 
            '<p>Collaboratively administrate empowered markets via plug-and-play networks. Dynamically procrastinate B2C users after installed base benefits. Dramatically visualize customer directed convergence without revolutionary ROI.</p>' .
            '<p>Efficiently unleash cross-media information without cross-media value. Quickly maximize timely deliverables for real-time schemas. Dramatically maintain clicks-and-mortar solutions without functional solutions.</p>' .
            '<p>Completely synergize resource taxing relationships via premier niche markets. Professionally cultivate one-to-one customer service with robust ideas. Dynamically innovate resource-leveling customer service for state of the art customer service.</p>'
        );

        if (!Craft::$app->getElements()->saveElement($entry)) {
            throw new \Exception('Could not save sample InstaBlog entry');
        }
    }

    private function createRoutes(): void
    {
        $routesService = Craft::$app->getRoutes();
        
        // Tag route
        $routesService->saveRoute([
            'uriParts' => ['blog/tag', '*'],
            'uriPattern' => 'blog/tag/*',
            'template' => 'blog/tag',
            'siteId' => null,
        ]);

        // Author route  
        $routesService->saveRoute([
            'uriParts' => ['blog/author', '*'],
            'uriPattern' => 'blog/author/*',
            'template' => 'blog/author', 
            'siteId' => null,
        ]);
    }

    private function copyTemplates(): void
    {
        $templatesPath = Craft::$app->path->getSiteTemplatesPath();
        $blogPath = $templatesPath . DIRECTORY_SEPARATOR . 'blog';
        
        if (!is_dir($blogPath)) {
            if (!mkdir($blogPath, 0755, true)) {
                throw new \Exception('Could not create blog templates directory');
            }
        }

        // Copy template files from plugin resources
        $pluginTemplatesPath = dirname(__DIR__) . '/templates/blog';
        if (is_dir($pluginTemplatesPath)) {
            $this->copyDirectory($pluginTemplatesPath, $blogPath);
        }
    }

    private function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $target = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                copy($item->getPathname(), $target);
            }
        }
    }
}
