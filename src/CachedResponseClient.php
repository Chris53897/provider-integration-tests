<?php

/*
 * This file is part of the Geocoder package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Geocoder\IntegrationTest;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\Stream;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Serve responses from local file cache.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class CachedResponseClient implements ClientInterface
{
    public function __construct(private ClientInterface $delegate,
                                private string          $cacheDir,
                                private ?string         $apiKey = null,
                                private ?string          $appCode = null)
    {}

    /**
     * {@inheritdoc}
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $host = (string) $request->getUri()->getHost();
        $cacheKey = (string) $request->getUri();
        if ('POST' === $request->getMethod()) {
            $cacheKey .= $request->getBody();
        }
        if (!empty($this->apiKey)) {
            $cacheKey = str_replace($this->apiKey, '[apikey]', $cacheKey);
        }
        if (!empty($this->appCode)) {
            $cacheKey = str_replace($this->appCode, '[appCode]', $cacheKey);
        }

        $file = sprintf('%s/%s_%s', $this->cacheDir, $host, sha1($cacheKey));
        if (is_file($file) && is_readable($file) && ($content = file_get_contents($file)) !== false) {
            return new Response(200, [], Stream::create(unserialize($content)));
        }

        $response = $this->delegate->sendRequest($request);
        file_put_contents($file, serialize($response->getBody()->getContents()));

        return $response;
    }
}
