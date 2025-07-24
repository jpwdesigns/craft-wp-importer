<?php

namespace jpwdesigns\wpimporter\services;

use Craft;
use craft\base\Component;
use craft\elements\Entry;
use craft\elements\Category;
use craft\elements\Tag;

/**
 * Uninstall Service
 */
class UninstallService extends Component
{
    public function run(): bool
    {
        try {
            $this->removeInstaBlogContent();
            $this->removeTemplates();
            
            Craft::info('InstaBlog uninstalled successfully', __METHOD__);
            return true;
        } catch (\Exception $e) {
            Craft::error('InstaBlog uninstallation failed: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    private function removeInstaBlogContent(): void
    {
        $fieldsService = Craft::$app->fields;
        $sectionsService = Craft::$app->sections;
        $categoriesService = Craft::$app->categories;
        $tagsService = Craft::$app->tags;

        // Remove InstaBlog section and entries
        $section = $sectionsService->getSectionByHandle('instaBlog');
        if ($section) {
            // Delete all entries in the section
            $entries = Entry::find()->sectionId($section->id)->all();
            foreach ($entries as $entry) {
                Craft::$app->elements->deleteElement($entry);
            }
            
            // Delete the section
            $sectionsService->deleteSection($section);
        }

        // Remove category group and categories
        $categoryGroup = $categoriesService->getGroupByHandle('instaBlogCategories');
        if ($categoryGroup) {
            $categoriesService->deleteGroup($categoryGroup);
        }

        // Remove tag group and tags
        $tagGroup = $tagsService->getTagGroupByHandle('instaBlogTags');
        if ($tagGroup) {
            $tagsService->deleteTagGroup($tagGroup);
        }

        // Remove InstaBlog field group and fields
        $fieldGroup = $fieldsService->getGroupByName('InstaBlog');
        if ($fieldGroup) {
            // Get all fields in the group
            $fields = $fieldsService->getFieldsByGroupId($fieldGroup->id);
            
            // Delete each field
            foreach ($fields as $field) {
                $fieldsService->deleteField($field);
            }
            
            // Delete the field group
            $fieldsService->deleteGroup($fieldGroup);
        }

        // Remove routes
        $this->removeRoutes();
    }

    private function removeRoutes(): void
    {
        $routesService = Craft::$app->routes;
        
        // Remove tag route
        $routes = $routesService->getRoutes();
        foreach ($routes as $route) {
            if (isset($route['template']) && ($route['template'] === 'blog/tag' || $route['template'] === 'blog/author')) {
                $routesService->deleteRouteById($route['id']);
            }
        }
    }

    private function removeTemplates(): void
    {
        $templatesPath = Craft::$app->path->getSiteTemplatesPath();
        $blogPath = $templatesPath . DIRECTORY_SEPARATOR . 'blog';
        
        if (is_dir($blogPath)) {
            $this->removeDirectory($blogPath);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($dir);
    }
}
