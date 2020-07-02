<?php

namespace Neo\Html;

use Neo\Exception\NeoException;

/**
 * 视图模板处理
 */
class Template
{
    /**
     * 用于将变量传递给模版
     *
     * @var array
     */
    private $vars = [];

    /**
     * 模板文件扩展名
     *
     * @var string
     */
    private $fileExtension = '.php';

    /**
     * 把变量传到模版中
     *
     * @param array $elements 变量
     */
    public function setVars(array $elements)
    {
        if (empty($elements)) {
            return;
        }

        if (! is_array($this->vars)) {
            $this->vars = [];
        }

        $this->vars = array_merge($this->vars, $elements);
    }

    /**
     * 设置模板文件的扩展名
     *
     * @param string $ext
     */
    public function setFileExtension(string $ext)
    {
        $this->fileExtension = $ext;
    }

    /**
     * 加载指定模板
     *
     * @param string $slug 模板文件相对路径，不带扩展名，比如：admin/user/profile
     * @param string $name 扩展属性
     *
     * @return string the template filename if one is located
     */
    public function getTemplateFile(string $slug, ?string $name = null)
    {
        $template = $name ? "{$slug}-{$name}" : $slug;

        return $this->loadTemplateFile($this->getTemplatePath($template));
    }

    /**
     * 加载模板
     *
     * @param string $file         模板文件绝对路径
     * @param bool   $require_once 是否加载一次
     */
    public function loadTemplateFile(string $file, bool $require_once = false)
    {
        if (! file_exists($file) || ! is_readable($file)) {
            throw new NeoException(sprintf('模板文件(%s)不存在或者不可读。', $file));
        }

        // 放在Neo中的元素
        $neo = neo();

        if ($neo->getExplainSQL()) {
            return;
        }

        if (is_array($this->vars)) {
            extract($this->vars, EXTR_SKIP);
        }

        if ($require_once) {
            require_once $file;
        } else {
            require $file;
        }
    }

    /**
     * 获取模板路径
     *
     * @param string $slug    模板文件相对路径，不带扩展名，比如：admin/user/profile
     * @param bool   $withDir 返回模板路径时，是否带模板文件夹路径
     *
     * @return string full path to file
     */
    private function getTemplatePath(string $slug, bool $withDir = true)
    {
        $slug = preg_replace('|[^a-z0-9-_/]+|', '', $slug);

        $template = $slug . $this->fileExtension;

        if ($withDir) {
            $template = neo()['templates_dir'] . DIRECTORY_SEPARATOR . $template;
        }

        return $template;
    }
}
