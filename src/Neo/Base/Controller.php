<?php

namespace Neo\Base;

use Neo\Http\Request as NeoRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 控制器基类
 */
class Controller extends NeoBase
{
    /**
     * @var array
     */
    protected $helpers = [];

    /**
     * @var array
     */
    protected $services = [];

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Response
     */
    protected $response;

    /**
     * 当前控制器是否需要登录验证，默认需要登录
     *
     * @var bool
     */
    protected $needSignin = true;

    /**
     * 允许未登录访问方法
     * @var array
     */
    protected $allowNoLoginMethods = [];

    /**
     * 用户信息有强制输入项，如果没有输入，则只能停在当前页
     *
     * @var bool
     */
    protected $needMandatoryInfo = true;

    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();

        $this->beforeRender();

        $this->request = $this->neo->request;
        $this->response = $this->neo->response;

        switch (NeoRequest::method()) {
            case 'GET':
            case 'DELETE':
            case 'PUT':
            case 'POST':
                break;
            case 'OPTIONS':
                $this->options();
                break;
            default:
                $this->forbidden(405);
                break;
        }

        foreach ($this->helpers as $helper) {
            $class = $this->getClassName($helper);

            $this->{$class} = loadHelper($helper);
        }

        foreach ($this->services as $service) {
            $class = $this->getClassName($service);

            $this->{$class} = loadService($service);
        }
    }

    /**
     * 获取类的变量的名称
     *
     * User\Address AS Address   => Address
     * User\Address              => Address
     * Address                   => Address
     *
     * @param string $class
     *
     * @return string
     */
    protected function getClassName(string &$class)
    {
        if (stripos($class, ' AS ') !== false) {
            [$class, $clazz] = explode(' AS ', $class);
        } elseif (stripos($class, '\\') !== false) {
            $pieces = explode('\\', $class);
            $clazz = end($pieces);
        } else {
            $clazz = $class;
        }

        return $clazz;
    }

    /**
     * 添加助手类
     *
     * @param mixed ...$helpers
     */
    protected function addHelpers(...$helpers)
    {
        $this->_merge($this->helpers, $helpers);
    }

    /**
     * 添加业务类
     *
     * @param mixed ...$services
     */
    protected function addServices(...$services)
    {
        $this->_merge($this->services, $services);
    }

    /**
     * 合并2个数组,并去重
     *
     * @param array $classes
     * @param array $args
     */
    private function _merge(array &$classes, array $args)
    {
        $classes = array_unique(array_merge($classes, $args));
    }

    /**
     * 预先处理
     */
    protected function beforeRender()
    {
    }

    /**
     * 获取php://input的值
     *
     * @param bool $raw 是否返回原始数据
     *
     * @return array|string
     */
    public function getPhpInput($raw = false)
    {
        $content = $this->request->getContent();

        return  $raw ? $content : json_decode($content, true, 512, JSON_BIGINT_AS_STRING);
    }

    /**
     * 403 Forbidden
     *
     * @param int $code
     */
    protected function forbidden(int $code = 403)
    {
        byebye($code);
    }

    /**
     * HTTP Header: OPTIONS
     */
    protected function options()
    {
        byebye(204);
    }
}
