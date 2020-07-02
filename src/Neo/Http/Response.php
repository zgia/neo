<?php

namespace Neo\Http;

use  Symfony\Component\HttpFoundation\Response as SymfonyResponse;

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

        return $this->prepare(neo()->getRequest())
            ->setStatusCode($code)
            ->setContent($content)
            ->send();
    }

    /**
     * 设置 Access Control Header
     *
     * @param int  $maxage
     * @param bool $credentials
     */
    public function sendAccessControlHeaders(int $maxage = 86400, bool $credentials = false)
    {
        $access_control_allow_methods = [
            'OPTIONS',
            'GET',
            'POST',
            'PUT',
            'PATCH',
            'DELETE',
        ];
        $access_control_allow_headers = [
            'Content-Type',
            'Content-Range',
            'Content-Disposition',
            'Authorization',
            'X-API-Version',
        ];

        $headers = $this->headers;

        $headers->set('Access-Control-Max-Age', $maxage);
        $headers->set('Access-Control-Allow-Origin', '*');
        $headers->set('Access-Control-Allow-Methods', implode(', ', $access_control_allow_methods));
        $headers->set('Access-Control-Allow-Headers', implode(', ', $access_control_allow_headers));
        $headers->set('Access-Control-Allow-Credentials', $credentials);
    }
}
