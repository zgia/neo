<?php

namespace Neo\Base;

use Neo\Http\Request;
use Neo\Http\Response;

/**
 * 控制器基类
 */
class Controller extends NeoBase
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Response
     */
    protected $response;

    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();

        $this->request = $this->neo->getRequest();
        $this->response = $this->neo->getResponse();

        $this->beforeRender();
    }

    /**
     * 预先处理
     */
    public function beforeRender(): void {}

    /**
     * 获取php://input的值
     *
     * @param bool $raw 是否返回原始数据
     *
     * @return array<string,mixed>|string
     */
    public function getPhpInput($raw = false)
    {
        $content = $this->request->getContent();

        return $raw ? $content : jsonDecode($content);
    }
}
