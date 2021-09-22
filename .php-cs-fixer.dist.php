<?php

/*
 * This file is part of the Viterbit pdfimages-extractor package.
 *
 * (c) Viterbit <contact@viterbit.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$header = 'This file is part of the Viterbit vbApp package.

(c) Viterbit <contact@viterbit.com>

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.';

return PhpCsFixer\Config::create()
    ->setRiskyAllowed(false)
    ->setUsingCache(false)
    ->setRules(
        [
            '@Symfony' => true,
            '@PHP71Migration' => true,
            'array_syntax' => ['syntax' => 'short'],
            'single_quote' => true,
            'linebreak_after_opening_tag' => true,
            'ordered_class_elements' => true,
            'ordered_imports' => true,
            'ternary_to_null_coalescing' => true,
            'phpdoc_add_missing_param_annotation' => ['only_untyped' => false],
            'header_comment' => ['header' => $header],
        ]
    )
    ->setCacheFile(__DIR__.'/.php_cs.cache')
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in(__DIR__)
            ->exclude(['config', 'var', 'bin', 'public'])
    );
