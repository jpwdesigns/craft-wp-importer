<?php

namespace jpwdesigns\wpimporter\models;

use craft\base\Model;

/**
 * WP Importer Settings Model
 */
class Settings extends Model
{
    public string $layout = '';
    public string $googlePlus = '';
    public string $twitter = '';
    public string $facebook = '';
    public string $linkedin = '';
    public string $disqus = '';

    public function defineRules(): array
    {
        return [
            [['layout', 'googlePlus', 'twitter', 'facebook', 'linkedin', 'disqus'], 'string'],
            [['layout', 'googlePlus', 'twitter', 'facebook', 'linkedin', 'disqus'], 'default', 'value' => ''],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'layout' => 'InstaBlog Layout Template',
            'googlePlus' => 'Google+ Profile URL',
            'twitter' => 'Twitter Handle',
            'facebook' => 'Facebook Profile URL', 
            'linkedin' => 'LinkedIn Profile URL',
            'disqus' => 'Disqus Shortname',
        ];
    }
}
