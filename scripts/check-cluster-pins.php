<?php

declare(strict_types=1);

// Composer records root require "dev-branch as X.Y.Z" aliases only in vendor/composer/installed.php
// (versions[name].aliases). installed.json keeps the dev branch in version/source.reference only.
$expected = [
    'psr/http-factory' => [
        'alias' => '1.1.0',
        'reference' => 'f9a0862677e7b1b9bd8dc2ed459f48c7e06ff277',
        'version' => 'dev-remote-test-2026-10-07',
    ],
    'psr/http-message' => [
        'alias' => '2.0.0',
        'reference' => 'c941fca6736161fe07e79fa83d85217b39411cef',
        'version' => 'dev-remote-test-2026-10-07',
    ],
];

$packagist_packages = [
    'phpunit/phpunit',
    'psr/log',
];

$root = dirname(__DIR__);
$installedPhp = $root . '/vendor/composer/installed.php';
$installedJson = $root . '/vendor/composer/installed.json';

if (!is_readable($installedPhp) || !is_readable($installedJson)) {
    fwrite(STDERR, "check-cluster-pins: run composer update first (missing vendor/composer metadata).\n");
    exit(1);
}

/**
 * @param array<string, array{alias: string, reference: string, version: string}> $map
 * @param array<string, array{reference?: string, aliases?: list<string>}> $versions
 */
function validate_installed_php(array $map, array $versions, bool $quiet = false): bool
{
    $failed = false;
    foreach ($map as $name => $want) {
        if (!isset($versions[$name])) {
            if (!$quiet) {
                fwrite(STDERR, sprintf("check-cluster-pins: %s is not installed.\n", $name));
            }
            $failed = true;
            continue;
        }
        $pkg = $versions[$name];
        $aliases = $pkg['aliases'] ?? [];
        if (!in_array($want['alias'], $aliases, true)) {
            if (!$quiet) {
                fwrite(STDERR, sprintf(
                    "check-cluster-pins: %s installed.php alias mismatch (expected %s in aliases %s).\n",
                    $name,
                    $want['alias'],
                    json_encode($aliases)
                ));
            }
            $failed = true;
        }
        $reference = $pkg['reference'] ?? null;
        if (!is_string($reference) || $reference === '') {
            if (!$quiet) {
                fwrite(STDERR, sprintf("check-cluster-pins: %s has no reference.\n", $name));
            }
            $failed = true;
            continue;
        }
        if (!hash_equals(strtolower($want['reference']), strtolower($reference))) {
            if (!$quiet) {
                fwrite(STDERR, sprintf(
                    "check-cluster-pins: %s installed.php reference mismatch (expected %s, got %s).\n",
                    $name,
                    $want['reference'],
                    $reference
                ));
            }
            $failed = true;
        }
    }

    return !$failed;
}

/**
 * @param array<string, array{alias: string, reference: string, version: string}> $map
 * @param array<string, array<string, mixed>> $packagesByName
 */
function validate_installed_json(array $map, array $packagesByName, bool $quiet = false): bool
{
    $failed = false;
    foreach ($map as $name => $want) {
        if (!isset($packagesByName[$name])) {
            if (!$quiet) {
                fwrite(STDERR, sprintf("check-cluster-pins: %s missing from installed.json.\n", $name));
            }
            $failed = true;
            continue;
        }
        $package = $packagesByName[$name];
        $reference = $package['source']['reference'] ?? null;
        if (!is_string($reference) || $reference === '') {
            if (!$quiet) {
                fwrite(STDERR, sprintf("check-cluster-pins: %s has no source.reference in installed.json.\n", $name));
            }
            $failed = true;
            continue;
        }
        if (!hash_equals(strtolower($want['reference']), strtolower($reference))) {
            if (!$quiet) {
                fwrite(STDERR, sprintf(
                    "check-cluster-pins: %s installed.json reference mismatch (expected %s, got %s).\n",
                    $name,
                    $want['reference'],
                    $reference
                ));
            }
            $failed = true;
        }
        $version = $package['version'] ?? null;
        if (!is_string($version) || $version !== $want['version']) {
            if (!$quiet) {
                fwrite(STDERR, sprintf(
                    "check-cluster-pins: %s installed.json version mismatch (expected %s, got %s).\n",
                    $name,
                    $want['version'],
                    is_string($version) ? $version : json_encode($version)
                ));
            }
            $failed = true;
        }
    }

    return !$failed;
}

/**
 * @return array{array<string, array<string, mixed>>, array<string, string>, array<string, string>}
 */
function load_installed_json(string $installedJson): array
{
    $raw = file_get_contents($installedJson);
    if ($raw === false) {
        throw new RuntimeException('Could not read installed.json');
    }
    /** @var array{packages?: list<array<string, mixed>>, 'packages-dev'?: list<array<string, mixed>>}|null $data */
    $data = json_decode($raw, true, 512);
    if (!is_array($data)) {
        throw new RuntimeException('Could not decode installed.json');
    }
    $packages = array_merge($data['packages'] ?? [], $data['packages-dev'] ?? []);
    $byName = [];
    $references = [];
    $urls = [];
    foreach ($packages as $package) {
        if (!isset($package['name'])) {
            continue;
        }
        $name = (string) $package['name'];
        $byName[$name] = $package;
        if (isset($package['source']['reference'])) {
            $references[$name] = strtolower((string) $package['source']['reference']);
        }
        if (isset($package['source']['url'])) {
            $urls[$name] = strtolower((string) $package['source']['url']);
        }
    }

    return [$byName, $references, $urls];
}

/**
 * @param array<string, array<string, mixed>> $pkg
 */
function installed_php_source_url(array $pkg): string
{
    if (isset($pkg['url']) && is_string($pkg['url'])) {
        return strtolower($pkg['url']);
    }
    if (isset($pkg['source']['url']) && is_string($pkg['source']['url'])) {
        return strtolower($pkg['source']['url']);
    }

    return '';
}

/**
 * @param list<string> $names
 * @param array<string, array<string, mixed>> $versions
 * @param array<string, string> $jsonReferences
 * @param array<string, string> $jsonUrls
 * @param list<string> $clusterRefs
 */
function validate_packagist_packages(
    array $names,
    array $versions,
    array $jsonReferences,
    array $jsonUrls,
    array $clusterRefs
): bool {
    $failed = false;
    foreach ($names as $name) {
        if (!isset($versions[$name])) {
            fwrite(STDERR, sprintf("check-cluster-pins: %s missing from installed.php.\n", $name));
            $failed = true;
            continue;
        }
        $phpPkg = $versions[$name];
        $phpRef = strtolower((string) ($phpPkg['reference'] ?? ''));
        foreach ($clusterRefs as $clusterRef) {
            if ($phpRef !== '' && hash_equals(strtolower($clusterRef), $phpRef)) {
                fwrite(STDERR, sprintf("check-cluster-pins: %s installed.php reference is index SHA %s.\n", $name, $phpRef));
                $failed = true;
            }
        }
        $phpUrl = installed_php_source_url($phpPkg);
        if ($phpUrl !== '' && strpos($phpUrl, 'training-datasmith') !== false) {
            fwrite(STDERR, sprintf("check-cluster-pins: %s installed.php URL %s is not Packagist.\n", $name, $phpUrl));
            $failed = true;
        }

        if (!isset($jsonReferences[$name])) {
            fwrite(STDERR, sprintf("check-cluster-pins: %s missing from installed.json.\n", $name));
            $failed = true;
            continue;
        }
        $jsonRef = $jsonReferences[$name];
        foreach ($clusterRefs as $clusterRef) {
            if (hash_equals(strtolower($clusterRef), $jsonRef)) {
                fwrite(STDERR, sprintf("check-cluster-pins: %s installed.json reference is index SHA %s.\n", $name, $jsonRef));
                $failed = true;
            }
        }
        if ($phpRef !== '' && !hash_equals($phpRef, $jsonRef)) {
            fwrite(STDERR, sprintf("check-cluster-pins: %s installed.php and installed.json reference differ (%s vs %s).\n", $name, $phpRef, $jsonRef));
            $failed = true;
        }
        $url = $jsonUrls[$name] ?? '';
        if ($url !== '' && strpos($url, 'training-datasmith') !== false) {
            fwrite(STDERR, sprintf("check-cluster-pins: %s installed.json source URL %s is not Packagist.\n", $name, $url));
            $failed = true;
        }
    }

    return !$failed;
}

/** @var array{versions?: array<string, array<string, mixed>>} $installed */
$installed = require $installedPhp;
$versions = $installed['versions'] ?? [];

[$packagesByName, $references, $urls] = load_installed_json($installedJson);

/** @var array<string, array<string, mixed>> $demoVersions */
$demoVersions = json_decode(json_encode($versions), true);
$demoVersions['psr/http-factory']['reference'] = str_repeat('0', 40);
if (validate_installed_php($expected, $demoVersions, true)) {
    fwrite(STDERR, "check-cluster-pins: wrong-SHA demo for installed.php did not fail.\n");
    exit(1);
}
if (!validate_installed_json($expected, $packagesByName)) {
    fwrite(STDERR, "check-cluster-pins: installed.json should still match during installed.php wrong-SHA demo.\n");
    exit(1);
}
fwrite(STDOUT, "check-cluster-pins: wrong-SHA demo failed for installed.php.\n");
fwrite(STDOUT, "check-cluster-pins: installed.json still matched during the installed.php wrong-SHA demo.\n");

$demoVersions = json_decode(json_encode($versions), true);
$demoVersions['psr/http-factory']['aliases'] = ['9.9.9'];
if (validate_installed_php($expected, $demoVersions, true)) {
    fwrite(STDERR, "check-cluster-pins: wrong-alias demo for installed.php did not fail.\n");
    exit(1);
}
if (!validate_installed_json($expected, $packagesByName)) {
    fwrite(STDERR, "check-cluster-pins: installed.json should still match during installed.php wrong-alias demo.\n");
    exit(1);
}
fwrite(STDOUT, "check-cluster-pins: wrong-alias demo failed for installed.php.\n");
fwrite(STDOUT, "check-cluster-pins: installed.json still matched during the installed.php wrong-alias demo.\n");

$demoPackages = $packagesByName;
$demoPackages['psr/http-message']['source']['reference'] = str_repeat('1', 40);
if (validate_installed_json($expected, $demoPackages, true)) {
    fwrite(STDERR, "check-cluster-pins: wrong-SHA demo for installed.json did not fail.\n");
    exit(1);
}
if (!validate_installed_php($expected, $versions)) {
    fwrite(STDERR, "check-cluster-pins: installed.php should still match during installed.json wrong-SHA demo.\n");
    exit(1);
}
fwrite(STDOUT, "check-cluster-pins: wrong-SHA demo failed for installed.json.\n");
fwrite(STDOUT, "check-cluster-pins: installed.php still matched during the installed.json wrong-SHA demo.\n");

$demoPackages = $packagesByName;
$demoPackages['psr/http-factory']['version'] = 'dev-wrong-branch';
if (validate_installed_json($expected, $demoPackages, true)) {
    fwrite(STDERR, "check-cluster-pins: wrong-version demo for installed.json did not fail.\n");
    exit(1);
}
if (!validate_installed_php($expected, $versions)) {
    fwrite(STDERR, "check-cluster-pins: installed.php should still match during installed.json wrong-version demo.\n");
    exit(1);
}
fwrite(STDOUT, "check-cluster-pins: wrong-version demo failed for installed.json.\n");
fwrite(STDOUT, "check-cluster-pins: installed.php still matched during the installed.json wrong-version demo.\n");

$cluster_refs = array_column($expected, 'reference');
$demoVersions = json_decode(json_encode($versions), true);
$demoVersions['phpunit/phpunit']['reference'] = $expected['psr/http-factory']['reference'];
if (validate_packagist_packages($packagist_packages, $demoVersions, $references, $urls, $cluster_refs)) {
    fwrite(STDERR, "check-cluster-pins: wrong installed.php Packagist SHA demo did not fail.\n");
    exit(1);
}
fwrite(STDOUT, "check-cluster-pins: wrong installed.php reference demo failed for phpunit/phpunit.\n");
fwrite(STDOUT, "check-cluster-pins: installed.json still matched during installed.php Packagist demo.\n");

if (!validate_installed_php($expected, $versions) || !validate_installed_json($expected, $packagesByName)) {
    exit(1);
}

if (!validate_packagist_packages($packagist_packages, $versions, $references, $urls, $cluster_refs)) {
    exit(1);
}

fwrite(STDOUT, "check-cluster-pins: phpunit/phpunit and psr/log resolve from Packagist in installed.php and installed.json.\n");
fwrite(STDOUT, "check-cluster-pins: cluster aliases checked in installed.php only; installed.json uses dev version + source.reference.\n");
fwrite(STDOUT, "check-cluster-pins: all cluster packages match expected SHAs and aliases.\n");
exit(0);
