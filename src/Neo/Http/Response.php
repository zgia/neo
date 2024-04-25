<?php

namespace Neo\Http;

use Neo\Exception\NeoException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Html 工具类
 */
class Response extends SymfonyResponse
{
    /**
     * Set HTTP Status Header
     *
     * @param int    $code    the response code
     * @param string $content the response content
     *
     * @return SymfonyResponse
     */
    public function sendData(?int $code = null, ?string $content = null)
    {
        $code || $code = 200;

        // 不暴露：php/VERSION
        header_remove('x-powered-by');

        return $this->prepare(neo()->getRequest())
            ->setStatusCode($code)
            ->setContent($content)
            ->send();
    }

    /**
     * 设置 Access Control Header
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Access-Control-Allow-Origin
     *
     * @param int                $maxage       Seconds
     * @param bool               $credentials  Enable credentials
     * @param string             $origin       An explicit origin or '*', cannot be 'null'
     * @param null|array<string> $allowMethods An array or null, cannot be '*'
     * @param null|array<string> $allowHeaders An array or null, cannot be '*'
     */
    public function sendAccessControlHeaders(int $maxage = 86400, bool $credentials = false, string $origin = '*', array $allowMethods = null, array $allowHeaders = null): void
    {
        if ($origin == null || $origin === 'null') {
            throw new NeoException(__('Origin cannot be null.'));
        }

        if ($credentials && $origin == '*') {
            throw new NeoException(__('Attempting to use the wildcard with credentials for origin.'));
        }

        $access_control_allow_methods = $allowMethods ?: [
            'OPTIONS',
            'GET',
            'POST',
            'PUT',
            'PATCH',
            'DELETE',
        ];
        $access_control_allow_headers = $allowHeaders ?: [
            'Content-Type',
            'Content-Range',
            'Content-Disposition',
            'Authorization',
        ];

        $headers = $this->headers;

        $headers->set('Access-Control-Allow-Origin', $origin);
        $headers->set('Access-Control-Allow-Methods', implode(', ', $access_control_allow_methods));
        $headers->set('Access-Control-Allow-Headers', implode(', ', $access_control_allow_headers));
        // @phpstan-ignore-next-line
        $headers->set('Access-Control-Allow-Credentials', $credentials);
        // @phpstan-ignore-next-line
        $headers->set('Access-Control-Max-Age', $maxage);

        if ($origin != '*') {
            $headers->set('Vary', 'Origin');
        }
    }
}
