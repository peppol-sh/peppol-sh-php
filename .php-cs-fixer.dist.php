<?php

declare(strict_types=1);

// The SDK supports PHP 7.4, so no rule here may write syntax that needs a
// newer PHP (for example a trailing comma in a parameter list).

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/config', __DIR__ . '/src', __DIR__ . '/tests'])
    ->append([__FILE__]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setUnsupportedPhpVersionAllowed(true)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'single_quote' => true,
        'no_extra_blank_lines' => true,
        'no_trailing_comma_in_singleline' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays']],
        'phpdoc_trim' => true,
        'phpdoc_indent' => true,
        'no_empty_phpdoc' => true,
    ])
    ->setFinder($finder);
