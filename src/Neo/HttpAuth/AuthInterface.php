<?php

namespace Neo\HttpAuth;

/**
 * Interface AuthInterface
 */
interface AuthInterface
{
    /**
     * 授权验证
     *
     * @param string              $authorization
     * @param array<string,mixed> $params
     *
     * @return bool
     */
    public function authenticate(string $authorization = '', array $params = []);
}
