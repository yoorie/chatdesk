<?php

namespace App\Services\Cms;

use HTMLPurifier;
use HTMLPurifier_Config;

/** Allow-list HTML sanitizer for CMS content (HTMLPurifier, LGPL-2.1+). */
class HtmlSanitizer
{
    protected static ?HTMLPurifier $purifier = null;

    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        if (! static::$purifier) {
            $config = HTMLPurifier_Config::createDefault();
            $cache = storage_path('framework/cache/purifier');
            if (! is_dir($cache)) {
                @mkdir($cache, 0775, true);
            }
            $config->set('Cache.SerializerPath', is_writable($cache) ? $cache : null);
            if (! is_writable($cache)) {
                $config->set('Cache.DefinitionImpl', null);
            }
            $config->set('HTML.Allowed', 'p[style],br,strong,b,em,i,u,s,h2[style],h3[style],h4[style],ul,ol,li,blockquote,code,pre,hr,a[href|title|target|rel],img[src|alt|width|height],span[style],table,thead,tbody,tr,th,td');
            $config->set('CSS.AllowedProperties', ['text-align']);
            $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);
            $config->set('Attr.AllowedFrameTargets', ['_blank']);
            $config->set('HTML.TargetNoopener', true);
            $config->set('HTML.TargetNoreferrer', true);
            $config->set('AutoFormat.RemoveEmpty', false);
            static::$purifier = new HTMLPurifier($config);
        }

        return static::$purifier->purify($html);
    }

    /** Plain text only (strip all tags). */
    public static function text(?string $value, int $max = 500): string
    {
        return mb_substr(trim(strip_tags((string) $value)), 0, $max);
    }

    /** Allow relative paths, http(s), mailto, tel and #anchors; otherwise '#'. */
    public static function url(?string $value): string
    {
        $v = trim((string) $value);
        if ($v === '') {
            return '';
        }
        if (preg_match('#^(/(?!/)|\#|https?://|mailto:|tel:)#i', $v)) {
            return mb_substr($v, 0, 500);
        }

        return '#';
    }
}
