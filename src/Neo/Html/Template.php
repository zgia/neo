<?php

namespace Neo\Html;

use Neo\Exception\ResourceNotFoundException;

/**
 * 视图模板处理
 */
class Template
{
    /**
     * 用于将变量传递给模版
     *
     * @var array<string,mixed>
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
     * @param array<string,mixed> $elements 变量
     */
    public function setVars(array $elements): void
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
    public function setFileExtension(string $ext): void
    {
        $this->fileExtension = $ext;
    }

    /**
     * 加载指定模板
     *
     * @param string $slug 模板文件相对路径，不带扩展名，比如：admin/user/profile
     * @param string $name 扩展属性
     */
    public function getTemplateFile(string $slug, ?string $name = null): void
    {
        $template = $name ? "{$slug}-{$name}" : $slug;

        $this->loadTemplateFile($this->getTemplatePath($template));
    }

    /**
     * 加载模板
     *
     * @param string $file         模板文件绝对路径
     * @param bool   $require_once 是否加载一次
     */
    public function loadTemplateFile(string $file, bool $require_once = false): void
    {
        // 放在Neo中的元素
        $neo = neo();

        if (! file_exists($file) || ! is_readable($file)) {
            throw new ResourceNotFoundException(__f('Template file(%s) is not exist or readable.', $file));
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
