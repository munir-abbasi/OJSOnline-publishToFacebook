<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$versionXmlPath = $root . '/version.xml';
$installXmlPath = $root . '/install.xml';
$upgradeXmlPath = $root . '/upgrade.xml';
$schemaPath = $root . '/schema/log.json';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

$loadXml = static function (string $path) use ($fail): SimpleXMLElement {
    $xml = simplexml_load_file($path);

    if ($xml === false) {
        $fail('Failed to parse XML file: ' . basename($path));
    }

    return $xml;
};

foreach ([$versionXmlPath, $installXmlPath, $upgradeXmlPath, $schemaPath] as $requiredPath) {
    if (!file_exists($requiredPath)) {
        $fail('Required file is missing: ' . basename($requiredPath));
    }
}

$versionXml = $loadXml($versionXmlPath);
$installXml = $loadXml($installXmlPath);
$upgradeXml = $loadXml($upgradeXmlPath);

$schema = json_decode((string) file_get_contents($schemaPath), true);
if (!is_array($schema)) {
    $fail('Failed to decode schema/log.json');
}

$releaseVersion = trim((string) $versionXml->release);
if ($releaseVersion === '') {
    $fail('version.xml does not contain an application release value');
}

if ((string) $installXml['version'] !== $releaseVersion) {
    $fail('install.xml version does not match version.xml release');
}

if ((string) $upgradeXml['version'] !== $releaseVersion) {
    $fail('upgrade.xml version does not match version.xml release');
}

$installMigrations = $installXml->xpath('/install/migration');
if ($installMigrations === false || count($installMigrations) !== 1) {
    $fail('install.xml must declare exactly one top-level migration');
}

$upgradeBlocks = $upgradeXml->xpath('/install/upgrade');
if ($upgradeBlocks === false || count($upgradeBlocks) === 0) {
    $fail('upgrade.xml must declare at least one upgrade block');
}

$migrationClassToPath = static function (string $class) use ($root): string {
    $prefix = 'APP\\plugins\\generic\\publishToFacebook\\';
    if (!str_starts_with($class, $prefix)) {
        return '';
    }

    return $root . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
};

$installMigrationClass = (string) $installMigrations[0]['class'];
$installMigrationPath = $migrationClassToPath($installMigrationClass);
if ($installMigrationPath === '' || !file_exists($installMigrationPath)) {
    $fail('install.xml references a missing migration class file: ' . $installMigrationClass);
}

$upgradeMigrationNodes = $upgradeXml->xpath('/install/upgrade/migration');
if ($upgradeMigrationNodes === false || count($upgradeMigrationNodes) === 0) {
    $fail('upgrade.xml must declare at least one migration');
}

foreach ($upgradeMigrationNodes as $node) {
    $migrationClass = (string) $node['class'];
    $migrationPath = $migrationClassToPath($migrationClass);
    if ($migrationPath === '' || !file_exists($migrationPath)) {
        $fail('upgrade.xml references a missing migration class file: ' . $migrationClass);
    }
}

$issueIdProperty = $schema['properties']['issueId'] ?? null;
if (!is_array($issueIdProperty)) {
    $fail('schema/log.json is missing the issueId property');
}

if (($issueIdProperty['type'] ?? null) !== 'integer') {
    $fail('schema/log.json issueId property must be typed as integer');
}

$issueIdValidation = $issueIdProperty['validation'] ?? null;
if (!is_array($issueIdValidation) || !in_array('nullable', $issueIdValidation, true)) {
    $fail('schema/log.json issueId property must declare nullable validation');
}

fwrite(STDOUT, 'Plugin metadata validation passed.' . PHP_EOL);
