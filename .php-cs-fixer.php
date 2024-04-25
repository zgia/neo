<?php

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER' => true,
        '@PSR2' => true,
        '@PSR12' => true,
        '@Symfony' => true,
        '@PhpCsFixer' => true,
        // array(1,2);
        // [1, 2];
        'array_syntax' => [
            'syntax' => 'short',
        ],
        // $foo = 'bar'   .   3   .    'bazqux';
        // $foo = 'bar' . 3 . 'bazqux';
        'concat_space' => [
            'spacing' => 'one',
        ],
        // true, false, null 使用小写
        'constant_case' => [
            'case' => 'lower',
        ],
        // 是否添加：declare(strict_types=1);
        'declare_strict_types' => false,
        // list($sample) = $array;
        // [$sample] = $array;
        'list_syntax' => [
            'syntax' => 'short',
        ],
        // <?php 之后添加一个换行
        'linebreak_after_opening_tag' => true,
        // self, static, parent 使用小写
        'lowercase_static_reference' => true,
        // 多行注释
        'multiline_comment_opening_closing' => true,
        // 分号前的空行处理
        'multiline_whitespace_before_semicolons' => [
            'strategy' => 'no_multi_line',
        ],
        // 移除未使用的import
        'no_unused_imports' => true,
        // if (!$bar) {} ====> if (! $bar) {}
        'not_operator_with_successor_space' => true,
        'not_operator_with_space' => false,
        // 类、方法注释，保留有非详细信息的注释
        'no_superfluous_phpdoc_tags' => false,
        // import导入排序
        'ordered_imports' => [
            'imports_order' => [
                'const',
                'class',
                'function',
            ],
            // 'alpha', 'length', 'none'
            'sort_algorithm' => 'alpha',
        ],
        // classes/interfaces/traits/enums 中的元素排序，比如把 protected 挪到 public的下方
        'ordered_class_elements' => false,
        // php unit
        'php_unit_strict' => false,
        // phpdoc（类、方法注释）对齐方式
        'phpdoc_align' => [
            'align' => 'vertical',
        ],
        // 补完phpdoc（类、方法注释）的参数
        'phpdoc_add_missing_param_annotation' => [
            'only_untyped' => false,
        ],
        'phpdoc_summary' => false,
        // 单引号
        'single_quote' => true,
        // $a = $b <> $c;
        // $a = $b != $c;
        'standardize_not_equals' => true,
        // 是否强制使用类似 1 == $a 来替换 $a == 1
        'yoda_style' => [
            'always_move_variable' => false,
            'equal' => false,
            'identical' => false,
        ],
    ])
    ->setFinder(PhpCsFixer\Finder::create()
        ->exclude('vendor')
        ->notPath([''])
        ->in(__DIR__))
    ->setUsingCache(false);
