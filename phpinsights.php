<?php

declare(strict_types=1);

use SlevomatCodingStandard\Sniffs\Namespaces\AlphabeticallySortedUsesSniff;
use SlevomatCodingStandard\Sniffs\TypeHints\DeclareStrictTypesSniff;
use SlevomatCodingStandard\Sniffs\TypeHints\DisallowMixedTypeHintSniff;
use SlevomatCodingStandard\Sniffs\TypeHints\ParameterTypeHintSniff;
use SlevomatCodingStandard\Sniffs\TypeHints\PropertyTypeHintSniff;
use SlevomatCodingStandard\Sniffs\TypeHints\ReturnTypeHintSniff;

return [

    'preset' => 'laravel',

    'ide' => null,

    'exclude' => [
        'bootstrap',
        'storage',
    ],

    'add' => [],

    // Livewire/Volt components lean on public properties and framework
    // magic (no explicit strict_types/import ordering discipline), so the
    // Laravel preset's stricter style sniffs are dropped rather than fought.
    'remove' => [
        AlphabeticallySortedUsesSniff::class,
        DeclareStrictTypesSniff::class,
        DisallowMixedTypeHintSniff::class,
        ParameterTypeHintSniff::class,
        PropertyTypeHintSniff::class,
        ReturnTypeHintSniff::class,
    ],

    'config' => [],

    // Baselined slightly below the scores measured at gate creation (Code
    // 94.6, Complexity 95.2, Architecture 82.4, Style 91.5) so the gate
    // passes today but still catches a real regression.
    'requirements' => [
        'min-quality' => 90,
        'min-complexity' => 90,
        'min-architecture' => 75,
        'min-style' => 85,
    ],

    'threads' => null,

    'timeout' => 60,
];
