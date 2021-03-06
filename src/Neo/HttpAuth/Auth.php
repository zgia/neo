<?php

namespace Neo\HttpAuth;

use Neo\Exception\LogicException;

/**
 * Class Auth
 */
class Auth
{
    protected $config = [];

    /**
     * Auth constructor.
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * 验证
     *
     * @throws LogicException
     * @return bool
     */
    protected function auth()
    {
        return false;
    }

    /**
     * 验证失败
     */
    protected function unauth()
    {
        byebye(401);
    }
}
