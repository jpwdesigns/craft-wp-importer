<?php

namespace jpwdesigns\wpimporter\jobs;

use Craft;
use craft\queue\BaseJob;
use craft\elements\Entry;
use craft\elements\User;
use craft\elements\Category;
use craft\elements\Tag;
use craft\elements\Asset;
use jpwdesigns\wpimporter\WpImporter;

/**
 * Import WordPress Job
 */
class ImportWordpressJob extends BaseJob
{
    public string $filePath;
    public bool $backup = false;
    public int $assetVolumeId;
    public array $importSettings = [];

    private array $userMappings = [];
    private array $categoryMappings = [];
    private array $tagMappings = [];
    private array $assetMappings = [];
    private array $entryMappings = [];

    public function execute($queue): void
    {
        $importService = WpImporter::getInstance()->import;
        
        // Parse the WordPress export
        $this->setProgress($queue, 0.1, 'Parsing WordPress export file...');
        $data = $importService->prepareData($this->filePath);

        $totalSteps = count($data['authors']) + count($data['categories']) + count($data['tags']) + count($data['attachments']) + count($data['posts']);
        $currentStep = 0;

        // Import authors
        foreach ($data['authors'] as $authorData) {
            $this->importAuthor($authorData);
            $currentStep++;
            $this->setProgress($queue, 0.1 + (0.8 * $currentStep / $totalSteps), "Importing author: {$authorData['displayName']}");
        }

        // Import categories
        foreach ($data['categories'] as $categoryData) {
            $this->importCategory($categoryData);
            $currentStep++;
            $this->setProgress($queue, 0.1 + (0.8 * $currentStep / $totalSteps), "Importing category: {$categoryData['name']}");
        }

        // Import tags
        foreach ($data['tags'] as $tagData) {
            $this->importTag($tagData);
            $currentStep++;
            $this->setProgress($queue, 0.1 + (0.8 * $currentStep / $totalSteps), "Importing tag: {$tagData['name']}");
        }

        // Import attachments/assets
        if ($this->assetVolumeId) {
            foreach ($data['attachments'] as $attachmentData) {
                $this->importAsset($attachmentData);
                $currentStep++;
                $this->setProgress($queue, 0.1 + (0.8 * $currentStep / $totalSteps), "Importing asset: {$attachmentData['title']}");
            }
        }

        // Import posts
        foreach ($data['posts'] as $postData) {
            $this->importPost($postData, $importService);
            $currentStep++;
            $this->setProgress($queue, 0.1 + (0.8 * $currentStep / $totalSteps), "Importing post: {$postData['title']}");
        }

        $this->setProgress($queue, 1, 'Import completed successfully!');

        // Clean up
        @unlink($this->filePath);
    }

    protected function defaultDescription(): string
    {
        return Craft::t('wp-importer', 'Importing WordPress content');
    }

    private function importAuthor(array $authorData): void
    {
        // Check if user already exists
        $user = User::find()->email($authorData['email'])->one();
        
        if (!$user) {
            $user = new User();
            $user->username = $authorData['login'];
            $user->email = $authorData['email'];
            $user->firstName = $authorData['firstName'];
            $user->lastName = $authorData['lastName'];
            
            if (!Craft::$app->elements->saveElement($user)) {
                Craft::warning("Could not save user: {$authorData['login']}", __METHOD__);
                return;
            }
        }

        $this->userMappings[$authorData['login']] = $user->id;
    }

    private function importCategory(array $categoryData): void
    {
        // Find the InstaBlog category group
        $categoryGroup = Craft::$app->categories->getGroupByHandle('instaBlogCategories');
        if (!$categoryGroup) {
            Craft::warning('InstaBlog category group not found', __METHOD__);
            return;
        }

        // Check if category already exists
        $category = Category::find()
            ->group($categoryGroup)
            ->slug($categoryData['slug'])
            ->one();

        if (!$category) {
            $category = new Category();
            $category->groupId = $categoryGroup->id;
            $category->title = $categoryData['name'];
            $category->slug = $categoryData['slug'];

            if (!Craft::$app->elements->saveElement($category)) {
                Craft::warning("Could not save category: {$categoryData['name']}", __METHOD__);
                return;
            }
        }

        $this->categoryMappings[$categoryData['slug']] = $category->id;
    }

    private function importTag(array $tagData): void
    {
        // Find the InstaBlog tag group
        $tagGroup = Craft::$app->tags->getTagGroupByHandle('instaBlogTags');
        if (!$tagGroup) {
            Craft::warning('InstaBlog tag group not found', __METHOD__);
            return;
        }

        // Check if tag already exists
        $tag = Tag::find()
            ->group($tagGroup)
            ->title($tagData['name'])
            ->one();

        if (!$tag) {
            $tag = new Tag();
            $tag->groupId = $tagGroup->id;
            $tag->title = $tagData['name'];

            if (!Craft::$app->elements->saveElement($tag)) {
                Craft::warning("Could not save tag: {$tagData['name']}", __METHOD__);
                return;
            }
        }

        $this->tagMappings[$tagData['slug']] = $tag->id;
    }

    private function importAsset(array $attachmentData): void
    {
        // This is a simplified version - in practice you'd want to download the image
        // and properly import it as a Craft asset
        $volume = Craft::$app->volumes->getVolumeById($this->assetVolumeId);
        if (!$volume) {
            return;
        }

        // For now, just store the mapping
        $this->assetMappings[$attachmentData['id']] = $attachmentData['url'];
    }

    private function importPost(array $postData, $importService): void
    {
        // Find the InstaBlog section
        $section = Craft::$app->sections->getSectionByHandle('instaBlog');
        if (!$section) {
            Craft::warning('InstaBlog section not found', __METHOD__);
            return;
        }

        $entryType = $section->getEntryTypes()[0];

        // Create new entry
        $entry = new Entry();
        $entry->sectionId = $section->id;
        $entry->typeId = $entryType->id;
        $entry->title = $postData['title'];
        $entry->slug = $postData['slug'];

        // Set author
        if (isset($this->userMappings[$postData['author']])) {
            $entry->authorId = $this->userMappings[$postData['author']];
        }

        // Set post date
        if ($postData['date']) {
            $entry->postDate = new \DateTime($postData['date']);
        }

        // Process content
        $processedContent = $importService->processContent(
            $postData['content'],
            $this->assetMappings,
            $this->entryMappings
        );
        
        $entry->setFieldValue('instaBlogBody', $processedContent);

        // Set categories
        $categoryIds = [];
        foreach ($postData['categories'] as $categoryData) {
            if (isset($this->categoryMappings[$categoryData['slug']])) {
                $categoryIds[] = $this->categoryMappings[$categoryData['slug']];
            }
        }
        if (!empty($categoryIds)) {
            $entry->setFieldValue('instaBlogCategories', $categoryIds);
        }

        // Set tags
        $tagIds = [];
        foreach ($postData['tags'] as $tagData) {
            if (isset($this->tagMappings[$tagData['slug']])) {
                $tagIds[] = $this->tagMappings[$tagData['slug']];
            }
        }
        if (!empty($tagIds)) {
            $entry->setFieldValue('instaBlogTags', $tagIds);
        }

        if (!Craft::$app->elements->saveElement($entry)) {
            Craft::warning("Could not save entry: {$postData['title']}", __METHOD__);
            return;
        }

        // Store mapping for link updates
        $this->entryMappings[$postData['slug']] = $entry->url;
    }
}
