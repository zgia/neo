<?php

namespace Neo\HttpAuth;

use Neo\Exception\LogicException;

/**
 * Class Auth
 */
class Auth
{
    /**
     * Config
     *
     * @var array<string,mixed>
     */
    protected $config = [];

    /**
     * Auth constructor.
     *
     * @param array<string,mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * 验证
     *
     * @return bool
     *
     * @throws LogicException
     */
    protected function auth()
    {
        return false;
    }

    /**
     * 验证失败
     */
    protected function unauth(): void
    {
        byebye(401);
    }
}
