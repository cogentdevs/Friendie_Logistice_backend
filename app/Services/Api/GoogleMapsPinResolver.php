<?php

namespace App\Services\Api;

use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/** Exact pins only: no address search, viewport inference, HTML scraping or geocoding. */
class GoogleMapsPinResolver
{
    public function resolve(string $value): array
    {
        $value=trim($value);
        if ($pair=$this->pair($value,7)) return $pair;
        $deadline=microtime(true)+10;
        $seen=[];
        for ($hop=0;$hop<=3;$hop++) {
            $parts=$this->url($value);
            if (isset($seen[$value])) throw new InvalidArgumentException('Unresolvable pin.');
            $seen[$value]=true;
            if ($pair=$this->exact($parts)) return $pair;
            if ($hop===3 || !extension_loaded('curl')) throw new InvalidArgumentException('Unresolvable pin.');
            $addresses=$this->addresses($parts['host']);
            if (!$addresses) throw new InvalidArgumentException('Unresolvable pin.');
            foreach ($addresses as $address) {
                if (!filter_var($address,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4|FILTER_FLAG_GLOBAL_RANGE)) {
                    throw new InvalidArgumentException('Unsafe destination.');
                }
            }
            $remaining=$deadline-microtime(true);
            if ($remaining<=0) throw new InvalidArgumentException('Pin lookup timed out.');
            // Pin the validated IP to the TLS hostname: no DNS re-resolution or proxy bypass.
            // HEAD avoids downloading Google pages or unbounded response bodies.
            $client=Http::withOptions([
                'allow_redirects'=>false,'cookies'=>false,'proxy'=>'','verify'=>true,
                'curl'=>[CURLOPT_RESOLVE=>[$parts['host'].':443:'.$addresses[0]],
                    CURLOPT_PROXY=>'',CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4,
                    CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS],
            ])->connectTimeout(min(2,$remaining))->timeout(min(4,$remaining));
            $client->setHandler(new CurlHandler());
            $response=$client->head($value);
            if (!in_array($response->status(),[301,302,303,307,308],true)) throw new InvalidArgumentException('No exact pin.');
            $location=$response->header('Location');
            if (!$location || strlen($location)>4096 || preg_match('/[\x00-\x20\x7f\\\\]/',$location)) throw new InvalidArgumentException('Unsafe redirect.');
            $value=(string)UriResolver::resolve(new Uri($value),new Uri($location));
            // Every resolved redirect is checked again before parsing or connecting.
        }
        throw new InvalidArgumentException('Unresolvable pin.');
    }

    protected function addresses(string $host): array
    {
        return gethostbynamel($host) ?: [];
    }

    private function url(string $value): array
    {
        if (strlen($value)>4096 || preg_match('/[\x00-\x20\x7f\\\\]/',$value)
            || preg_match('/[\x00-\x1f\x7f\\\\]/',rawurldecode($value))) throw new InvalidArgumentException('Invalid Maps URL.');
        $p=parse_url($value);
        if (!$p || ($p['scheme']??null)!=='https' || isset($p['user']) || isset($p['pass'])
            || isset($p['fragment']) || (isset($p['port']) && $p['port']!==443)) throw new InvalidArgumentException('Invalid Maps URL.');
        $p['host']=strtolower($p['host']??'');
        $path=$p['path']??'/';
        $allowed=match ($p['host']) {
            'www.google.com','google.com'=>preg_match('~^/maps(?:/|$)~',$path),
            'maps.google.com'=>true,
            'maps.app.goo.gl'=>preg_match('~^/[A-Za-z0-9_-]+/?$~',$path),
            'goo.gl'=>preg_match('~^/maps/[A-Za-z0-9_-]+/?$~',$path),
            default=>false,
        };
        if (!$allowed) throw new InvalidArgumentException('Unsupported Maps host or path.');
        return $p;
    }

    private function pair(string $value, int $precision=15): ?array
    {
        if (!preg_match('/\A(-?\d{1,3}(?:\.\d{1,'.$precision.'})?)\s*,\s*(-?\d{1,3}(?:\.\d{1,'.$precision.'})?)\z/D',$value,$m)
            || abs((float)$m[1])>90 || abs((float)$m[2])>180) return null;
        return [number_format((float)$m[1],7,'.',''),number_format((float)$m[2],7,'.','')];
    }

    private function exact(array $parts): ?array
    {
        // Short-link query parameters are not authoritative until Google redirects.
        if (in_array($parts['host'],['maps.app.goo.gl','goo.gl'],true)) return null;
        parse_str($parts['query']??'',$query);
        $path=rawurldecode($parts['path']??'');
        if (preg_match('~(?:^|/)dir(?:/|$)~',$path) || array_intersect(['origin','destination','saddr','daddr'],array_keys($query))) {
            throw new InvalidArgumentException('A route is not a single pin.');
        }
        $pairs=[];
        foreach (['q','query'] as $key) {
            if (isset($query[$key]) && is_string($query[$key])) {
                $text=preg_replace('/^loc:/i','',trim($query[$key]));
                if ($pair=$this->pair($text)) $pairs[implode(',',$pair)]=$pair;
            }
        }
        // !3d/!4d identifies a place pin; @lat,lng is only a camera/viewport, never used.
        $data=$path.' '.(is_string($query['data']??null)?$query['data']:'');
        preg_match_all('/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)(?=!|\/|\s|$)/',$data,$matches,PREG_SET_ORDER);
        foreach ($matches as $match) {
            $pair=$this->pair($match[1].','.$match[2]);
            if (!$pair) throw new InvalidArgumentException('Invalid pin.');
            $pairs[implode(',',$pair)]=$pair;
        }
        if (count($pairs)>1) throw new InvalidArgumentException('Ambiguous pins.');
        return $pairs ? array_values($pairs)[0] : null;
    }
}
