<?php

namespace Neo;

/**
 * 国际化
 */
class I18n
{
    /**
     * 语言短语
     *
     * @var string[]
     */
    private static $phrases = [];

    /**
     * 获取缺省语言
     *
     * @param string $languages_dir 语言目录
     * @param string $locale        本地化语言
     */
    public static function loadDefaultLanguage(?string $languages_dir = null, string $locale = ''): void
    {
        if (! $languages_dir) {
            return;
        }

        $locale || $locale = Neo::language();

        static::loadLanguage("{$languages_dir}/{$locale}.php");
    }

    /**
     * 加载语言文件
     *
     * @param string $lanFile 语言文件
     *
     * @return bool True on success, false on failure
     */
    public static function loadLanguage(string $lanFile)
    {
        if (! is_readable($lanFile)) {
            return false;
        }

        self::$phrases = include $lanFile;

        return true;
    }

    /**
     * 翻译短语
     *
     * @param string $text 待翻译的短语
     *
     * @return string 已经翻译的短语
     */
    public static function translate(string $text)
    {
        return self::$phrases[$text] ?? $text;
    }

    /**
     * 返回翻译后的短语
     *
     * @param string $text 待翻译的短语
     *
     * @return string 已经翻译的短语
     */
    public static function __(string $text)
    {
        return static::translate($text);
    }
}
