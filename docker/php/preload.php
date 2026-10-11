<?php

$basePath = dirname(__DIR__, 2);

require $basePath.'/vendor/autoload.php';

$classMap = require $basePath.'/vendor/composer/autoload_classmap.php';

$hotNamespaces = [
    'Illuminate\\Contracts\\',
    'Illuminate\\Support\\',
    'Illuminate\\Collections\\',
    'Illuminate\\Container\\',
    'Illuminate\\Pipeline\\',
    'Illuminate\\Http\\',
    'Illuminate\\Routing\\',
    'Illuminate\\Session\\',
    'Illuminate\\Cookie\\',
    'Illuminate\\Events\\',
    'Illuminate\\Translation\\',
    'Illuminate\\Validation\\',
    'Illuminate\\Database\\Eloquent\\',
    'Illuminate\\Database\\Query\\',
    'Illuminate\\Cache\\',
    'Illuminate\\View\\',
    'Symfony\\Component\\HttpFoundation\\',
    'Inertia\\',
    'App\\',
];

$excludedFragments = ['\\Test\\', '\\Testing\\', 'IdentityMarshaller'];

$shouldPreload = static function (string $class) use ($hotNamespaces, $excludedFragments): bool {
    foreach ($excludedFragments as $fragment) {
        if (str_contains($class, $fragment)) {
            return false;
        }
    }

    foreach ($hotNamespaces as $namespace) {
        if (str_starts_with($class, $namespace)) {
            return true;
        }
    }

    return false;
};

foreach (array_keys($classMap) as $class) {
    if (! $shouldPreload($class)) {
        continue;
    }

    try {
        class_exists($class) || interface_exists($class) || trait_exists($class) || enum_exists($class);
    } catch (Throwable) {
    }
}
