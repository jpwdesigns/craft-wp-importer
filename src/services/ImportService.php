<?php

namespace jpwdesigns\wpimporter\services;

use Craft;
use craft\base\Component;
use craft\elements\Entry;
use craft\elements\User;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Tag;
use DOMDocument;
use DOMXPath;

/**
 * Import Service
 */
class ImportService extends Component
{
    public function prepareData(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception('Import file not found');
        }

        $xml = file_get_contents($filePath);
        if (!$xml) {
            throw new \Exception('Could not read import file');
        }

        // Parse WordPress XML
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        
        if (!$dom->loadXML($xml)) {
            $errors = libxml_get_errors();
            $errorMessage = 'Invalid XML: ';
            foreach ($errors as $error) {
                $errorMessage .= $error->message . ' ';
            }
            throw new \Exception($errorMessage);
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('wp', 'http://wordpress.org/export/1.2/');
        $xpath->registerNamespace('content', 'http://purl.org/rss/1.0/modules/content/');

        // Extract data
        $data = [
            'posts' => $this->extractPosts($xpath),
            'authors' => $this->extractAuthors($xpath),
            'categories' => $this->extractCategories($xpath),
            'tags' => $this->extractTags($xpath),
            'attachments' => $this->extractAttachments($xpath),
        ];

        // Filter out empty content
        $data['posts'] = array_filter($data['posts'], function($post) {
            return !empty($post['title']) || !empty($post['content']);
        });

        return $data;
    }

    private function extractPosts(DOMXPath $xpath): array
    {
        $posts = [];
        $items = $xpath->query('//item[wp:post_type="post" and wp:status="publish"]');

        foreach ($items as $item) {
            $postId = $xpath->evaluate('string(wp:post_id)', $item);
            $title = $xpath->evaluate('string(title)', $item);
            $content = $xpath->evaluate('string(content:encoded)', $item);
            $excerpt = $xpath->evaluate('string(excerpt:encoded)', $item);
            $slug = $xpath->evaluate('string(wp:post_name)', $item);
            $date = $xpath->evaluate('string(wp:post_date)', $item);
            $author = $xpath->evaluate('string(dc:creator)', $item);

            // Extract categories and tags
            $categories = [];
            $tags = [];
            $categoryNodes = $xpath->query('category', $item);
            
            foreach ($categoryNodes as $categoryNode) {
                $domain = $categoryNode->getAttribute('domain');
                $nicename = $categoryNode->getAttribute('nicename');
                $name = $categoryNode->textContent;
                
                if ($domain === 'category') {
                    $categories[] = ['name' => $name, 'slug' => $nicename];
                } elseif ($domain === 'post_tag') {
                    $tags[] = ['name' => $name, 'slug' => $nicename];
                }
            }

            // Extract featured image
            $featuredImage = null;
            $metaNodes = $xpath->query('wp:postmeta[wp:meta_key="_thumbnail_id"]', $item);
            if ($metaNodes->length > 0) {
                $featuredImage = $xpath->evaluate('string(wp:meta_value)', $metaNodes->item(0));
            }

            $posts[] = [
                'id' => $postId,
                'title' => $title,
                'content' => $content,
                'excerpt' => $excerpt,
                'slug' => $slug,
                'date' => $date,
                'author' => $author,
                'categories' => $categories,
                'tags' => $tags,
                'featuredImage' => $featuredImage,
            ];
        }

        return $posts;
    }

    private function extractAuthors(DOMXPath $xpath): array
    {
        $authors = [];
        $authorNodes = $xpath->query('//wp:author');

        foreach ($authorNodes as $authorNode) {
            $login = $xpath->evaluate('string(wp:author_login)', $authorNode);
            $email = $xpath->evaluate('string(wp:author_email)', $authorNode);
            $displayName = $xpath->evaluate('string(wp:author_display_name)', $authorNode);
            $firstName = $xpath->evaluate('string(wp:author_first_name)', $authorNode);
            $lastName = $xpath->evaluate('string(wp:author_last_name)', $authorNode);

            $authors[$login] = [
                'login' => $login,
                'email' => $email,
                'displayName' => $displayName,
                'firstName' => $firstName,
                'lastName' => $lastName,
            ];
        }

        return $authors;
    }

    private function extractCategories(DOMXPath $xpath): array
    {
        $categories = [];
        $categoryNodes = $xpath->query('//wp:category');

        foreach ($categoryNodes as $categoryNode) {
            $termId = $xpath->evaluate('string(wp:term_id)', $categoryNode);
            $slug = $xpath->evaluate('string(wp:category_nicename)', $categoryNode);
            $name = $xpath->evaluate('string(wp:cat_name)', $categoryNode);
            $parent = $xpath->evaluate('string(wp:category_parent)', $categoryNode);

            $categories[$slug] = [
                'id' => $termId,
                'name' => $name,
                'slug' => $slug,
                'parent' => $parent,
            ];
        }

        return $categories;
    }

    private function extractTags(DOMXPath $xpath): array
    {
        $tags = [];
        $tagNodes = $xpath->query('//wp:tag');

        foreach ($tagNodes as $tagNode) {
            $termId = $xpath->evaluate('string(wp:term_id)', $tagNode);
            $slug = $xpath->evaluate('string(wp:tag_slug)', $tagNode);
            $name = $xpath->evaluate('string(wp:tag_name)', $tagNode);

            $tags[$slug] = [
                'id' => $termId,
                'name' => $name,
                'slug' => $slug,
            ];
        }

        return $tags;
    }

    private function extractAttachments(DOMXPath $xpath): array
    {
        $attachments = [];
        $items = $xpath->query('//item[wp:post_type="attachment"]');

        foreach ($items as $item) {
            $postId = $xpath->evaluate('string(wp:post_id)', $item);
            $title = $xpath->evaluate('string(title)', $item);
            $url = $xpath->evaluate('string(wp:attachment_url)', $item);
            $file = $xpath->evaluate('string(wp:postmeta[wp:meta_key="_wp_attached_file"]/wp:meta_value)', $item);

            $attachments[$postId] = [
                'id' => $postId,
                'title' => $title,
                'url' => $url,
                'file' => $file,
            ];
        }

        return $attachments;
    }

    public function processContent(string $content, array $attachments = [], array $linkMappings = []): string
    {
        // Process WordPress shortcodes and convert to HTML
        $content = $this->processShortcodes($content);
        
        // Update image URLs to point to Craft assets
        $content = $this->updateImageUrls($content, $attachments);
        
        // Update internal links
        $content = $this->updateInternalLinks($content, $linkMappings);
        
        return $content;
    }

    private function processShortcodes(string $content): string
    {
        // Convert common WordPress shortcodes to HTML
        $patterns = [
            '/\[caption[^\]]*\](.*?)\[\/caption\]/s' => '<figure>$1</figure>',
            '/\[quote[^\]]*\](.*?)\[\/quote\]/s' => '<blockquote>$1</blockquote>',
            '/\[code[^\]]*\](.*?)\[\/code\]/s' => '<code>$1</code>',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }

        return $content;
    }

    private function updateImageUrls(string $content, array $attachments): string
    {
        // This would need to be implemented based on how assets are handled
        // For now, return content as-is
        return $content;
    }

    private function updateInternalLinks(string $content, array $linkMappings): string
    {
        // Update internal WordPress links to point to new Craft entries
        foreach ($linkMappings as $oldUrl => $newUrl) {
            $content = str_replace($oldUrl, $newUrl, $content);
        }

        return $content;
    }
}
