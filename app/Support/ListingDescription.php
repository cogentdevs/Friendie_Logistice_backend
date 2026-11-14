<?php

namespace App\Support;

final class ListingDescription
{
    public static function html(?string $value): ?string
    {
        if ($value === null || $value === '') return $value;
        $config = \HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.Allowed', 'p,br,strong,b,em,i,u,s,blockquote,h2,h3,ul,ol,li,a[href]');
        $config->set('URI.AllowedSchemes', ['http'=>true, 'https'=>true]);
        $config->set('HTML.Trusted', false);
        $config->set('HTML.SafeIframe', false);
        // Avoid vendor-directory writes and cross-environment definition caches.
        $config->set('Cache.DefinitionImpl', null);
        $config->getDefinition('URI')->addFilter(new class extends \HTMLPurifier_URIFilter {
            public $name = 'ListingAbsoluteHttp';

            public function filter(&$uri, $config, $context)
            {
                return in_array(strtolower((string)$uri->scheme), ['http','https'], true)
                    && !empty($uri->host) && $uri->userinfo === null;
            }
        }, $config);

        return (new \HTMLPurifier($config))->purify($value);
    }

    public static function preview(?string $value, int $width): string
    {
        $html = self::html($value) ?? '';
        // Decode only after removing real tags; the caller must escape this text.
        $text = preg_replace('~<(?:br\s*/?|/(?:p|h2|h3|li|blockquote))>~i', ' ', $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text));

        return mb_strimwidth($text, 0, $width, '...', 'UTF-8');
    }
}
