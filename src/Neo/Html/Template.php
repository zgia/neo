<?php

namespace Neo\Html;

/**
 * 视图模板处理
 */
class Template
{
    /**
     * 模板文件扩展名
     *
     * @var string
     */
    public static $TEMPLATE_FILE_EXTENSION = '.php';

    /**
     * 加载指定模板
     *
     * @param string $slug the name of the specialised $template
     * @param string $name the name of the specialised
     *
     * @return string the template filename if one is located
     */
    public static function getTemplate(string $slug, ?string $name = null)
    {
        $template = $name ? "{$slug}-{$name}" : $slug;

        return static::loadTemplate(self::getTemplatePath($template, true));
    }

    /**
     * 获取模板路径
     *
     * @param string $type     filename without extension
     * @param bool   $withPath Add full path to template
     *
     * @return string full path to file
     */
    public static function getTemplatePath(string $type, bool $withPath = false)
    {
        $type = preg_replace('|[^a-z0-9-_/]+|', '', $type);

        $template = $type . static::$TEMPLATE_FILE_EXTENSION;

        if ($withPath) {
            $template = neo()['templates_dir'] . DIRECTORY_SEPARATOR . $template;
        }

        return static::loadTemplate($template, false);
    }

    /**
     * 引入模板
     *
     * @param string $template     path to template file
     * @param bool   $require_once Whether to require_once or require. Default true.
     */
    private static function requireTemplate(string $template, bool $require_once = true)
    {
        $neo = neo();

        if ($neo['explain_sql']) {
            return;
        }

        if (is_array($neo->templateVars)) {
            extract($neo->templateVars, EXTR_SKIP);
        }

        if ($require_once) {
            require_once $template;
        } else {
            require $template;
        }
    }

    /**
     * 加载模板
     *
     * @param string $template     template file to search for
     * @param bool   $load         if true the template file will be loaded if it is found
     * @param bool   $require_once Whether to require_once or require. Default false. Has no effect if $load is false.
     *
     * @return string the template filename if one is located
     */
    public static function loadTemplate(string $template, bool $load = true, bool $require_once = false)
    {
        $located = file_exists($template) ? $template : '';

        if ($load && $located != '') {
            static::requireTemplate($located, $require_once);
        }

        return $located;
    }
}
