<?php

namespace App\Services;

use App\Models\Url;
use Embed\Embed;
use Spatie\Url\Url as SpatieUrl;
use Symfony\Component\HttpFoundation\IpUtils;

class UrlService
{
    /**
     * @var \App\Models\Url
     */
    protected $url;

    /**
     * @var \App\Services\KeyService
     */
    protected $keySrvc;

    /**
     * UrlService constructor.
     */
    public function __construct()
    {
        $this->url = new Url;
        $this->keySrvc = new KeyService;
    }

    /**
     * @param  array|string  $request
     * @param  int  $authId
     */
    public function shortenUrl($request, $authId)
    {
        $key = $request['custom_key'] ?? $this->keySrvc->urlKey($request['long_url']);

        return Url::create([
            'user_id'    => $authId,
            'long_url'   => $request['long_url'],
            'meta_title' => $request['long_url'],
            'keyword'    => $key,
            'is_custom'  => $request['custom_key'] ? 1 : 0,
            'ip'         => request()->ip(),
        ]);
    }

    /**
     * @param  array  $request
     * @param  object  $url  \App\Models\Url
     */
    public function update(array $request, object $url)
    {
        $url->long_url = $request['long_url'];
        $url->meta_title = $request['meta_title'];
        $url->save();

        return $url;
    }

    /**
     * @param  object  $url  \App\Models\Url
     */
    public function delete(object $url)
    {
        return $url->delete();
    }

    /**
     * @param  string  $key
     * @param  int  $authId
     */
    public function duplicate($key, $authId)
    {
        $randomKey = $this->keySrvc->randomString();
        $shortenedUrl = Url::whereKeyword($key)->firstOrFail();

        $replicate = $shortenedUrl->replicate()->fill([
            'user_id'   => $authId,
            'keyword'   => $randomKey,
            'is_custom' => 0,
            'clicks'    => 0,
        ]);
        $replicate->save();

        return $replicate;
    }

    /**
     * @param  int  $id
     */
    public function urlCount($id = null)
    {
        return $this->url->whereUserId($id)->count('keyword');
    }

    public function totalUrl()
    {
        return $this->url->count('keyword');
    }

    /**
     * @param  int  $id
     */
    public function clickCount($id = null): int
    {
        return $this->url->whereUserId($id)->sum('clicks');
    }

    public function totalClick(): int
    {
        return $this->url->sum('clicks');
    }

    /**
     * Anonymize an IPv4 or IPv6 address.
     *
     * @param  string  $address
     * @return string
     */
    public static function anonymizeIp($address)
    {
        if (uHub('anonymize_ip_addr') == false) {
            return $address;
        }

        return IPUtils::anonymize($address);
    }

    /**
     * Get Domain from external url.
     *
     * Extract the domain name using the classic parse_url() and then look for
     * a valid domain without any subdomain (www being a subdomain). Won't
     * work on things like 'localhost'.
     *
     * @param  string  $url
     * @return string
     */
    public function getDomain(string $url)
    {
        $url = SpatieUrl::fromString($url);

        return urlSanitize($url->getHost());
    }

    /**
     * This function returns a string: either the page title as defined in
     * HTML, or "{domain_name} - No Title" if not found.
     *
     * @param  string  $url
     * @return string
     */
    public function webTitle(string $url)
    {
        $domain = $this->getDomain($url);

        try {
            $webTitle = (new Embed())->get($url)->title;
        } catch (\Exception $e) {
            $webTitle = $domain.' - No Title';
        }

        if (stripos($webTitle, stristr($domain, '.', true)) === false) {
            return $domain.' | '.$webTitle;
        }

        return $webTitle;
    }
}
