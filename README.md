# WP Importer for Craft CMS 5

A plugin to quickly set up a blog on Craft CMS websites and migrate content from WordPress.

## Features

- **Complete Blog Setup**: Creates sections, fields, categories, and tags automatically
- **WordPress Import**: Import posts, categories, tags, authors, and images from WordPress XML exports
- **Modern Templates**: Responsive, SEO-friendly blog templates 
- **Social Integration**: Built-in social sharing and OpenGraph meta tags
- **Disqus Comments**: Easy integration with Disqus commenting system
- **CKEditor Integration**: Rich text editing with CKEditor
- **Search & Navigation**: Tag pages, category pages, author pages, and search functionality

## Requirements

- Craft CMS 5.0+
- PHP 8.2+
- Writable templates directory for installation

## Installation

1. Install via Composer:
   ```bash
   composer require jpwdesigns/craft-wp-importer
   ```

2. Install and enable the plugin in the Craft Control Panel under Settings > Plugins

3. Configure your blog settings at Settings > Plugins > WP Importer

## What Gets Created

During installation, WP Importer creates:

- **InstaBlog Section**: Channel for blog posts with URL format `blog/{slug}`
- **Fields**: Body (CKEditor), Featured Image (Assets), Categories, Tags, Social fields
- **Category Group**: InstaBlog Categories with URL format `blog/category/{slug}`
- **Tag Group**: InstaBlog Tags
- **Templates**: Complete set of blog templates in `templates/blog/`
- **Routes**: Tag and author page routing
- **User Fields**: Social media fields added to user profiles

## Templates

The plugin creates a complete set of blog templates:

- `blog/index.twig` - Blog listing page
- `blog/_entry.twig` - Individual blog post template  
- `blog/category.twig` - Category archive pages
- `blog/tag.twig` - Tag archive pages
- `blog/author.twig` - Author archive pages
- `blog/search.twig` - Search results

## WordPress Import

1. Export your WordPress content via WP Admin > Tools > Export
2. Go to Settings > Plugins > WP Importer in Craft CP
3. Click "Import from WordPress"
4. Upload your XML file and follow the prompts

The import includes:
- Posts with content, categories, tags, and featured images
- Authors (creates Craft users)
- Categories and tags
- Image downloads and asset creation
- Internal link conversion

## Configuration

### Layout Template
Specify a custom layout template for blog templates to extend. Default is `_layout`.

### Social Settings
Configure social media profiles for OpenGraph meta tags and social sharing:
- Facebook Profile URL
- Twitter Handle  
- LinkedIn Profile URL

### Disqus Comments
Enter your Disqus shortname to enable comments on blog posts.

## Template Variables

Access WP Importer settings and functions in your templates:

```twig
{# Get plugin settings #}
{% set settings = wpImporter.getSettings() %}

{# Truncate text #}
{{ wpImporter.truncate(entry.instaBlogBody|striptags, 300) }}

{# Get available asset volumes #}
{% set volumes = wpImporter.getVolumes() %}
```

## Customization

The blog templates are designed to be customized for your site. After installation, you can modify:

- Template files in `templates/blog/`
- CSS styling (no CSS is included by default)
- Field layouts and content structure
- URL formats and routing

## Uninstallation

**Warning**: Uninstalling WP Importer will remove ALL blog content, fields, and templates. Create a backup first.

The uninstall process removes:
- All InstaBlog entries
- InstaBlog section and entry types
- All InstaBlog fields and field groups
- Category and tag groups
- Blog templates folder
- Routes

## Support

- [Documentation](https://github.com/jpwdesigns/craft-wp-importer)
- [Issues](https://github.com/jpwdesigns/craft-wp-importer/issues)

## License

MIT License. See LICENSE file for details.

## Changelog

### 5.0.0
- Complete rewrite for Craft CMS 5
- Modern job queue system replacing old tasks
- CKEditor integration instead of RichText
- Improved WordPress import with better content processing
- Modern template structure with better SEO
- Enhanced social media integration
- Improved asset handling
