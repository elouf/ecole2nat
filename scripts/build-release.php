<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$entrypoint = $root . '/ecole2nat.php';
$contents = file_get_contents($entrypoint);

if ($contents === false || !preg_match("/define\('E2N_VERSION',\s*'([^']+)'\)/", $contents, $matches)) {
    fwrite(STDERR, "Impossible de déterminer la version du plugin.\n");
    exit(1);
}

$version = $matches[1];
$buildDir = $root . '/build';
$packageDir = $buildDir . '/ecole2nat';
$zipPath = $buildDir . '/ecole2nat-' . $version . '.zip';

if (!class_exists(ZipArchive::class)) {
    fwrite(STDERR, "L’extension PHP zip est requise pour construire l’archive.\n");
    exit(1);
}

function removeTree(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    if (is_link($path) || is_file($path)) {
        if (!unlink($path)) {
            throw new RuntimeException('Impossible de supprimer ' . $path);
        }
        return;
    }
    $items = scandir($path);
    if ($items === false) {
        throw new RuntimeException('Impossible de lire ' . $path);
    }
    foreach ($items as $item) {
        if ($item !== '.' && $item !== '..') {
            removeTree($path . DIRECTORY_SEPARATOR . $item);
        }
    }
    if (!rmdir($path)) {
        throw new RuntimeException('Impossible de supprimer ' . $path);
    }
}

function copyTree(string $source, string $destination): void
{
    if (is_link($source)) {
        throw new RuntimeException('Lien symbolique interdit dans le paquet : ' . $source);
    }
    if (is_file($source)) {
        $parent = dirname($destination);
        if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
            throw new RuntimeException('Impossible de créer ' . $parent);
        }
        if (!copy($source, $destination)) {
            throw new RuntimeException('Impossible de copier ' . $source);
        }
        return;
    }
    if (!is_dir($source)) {
        throw new RuntimeException('Élément requis introuvable : ' . $source);
    }
    if (!is_dir($destination) && !mkdir($destination, 0775, true) && !is_dir($destination)) {
        throw new RuntimeException('Impossible de créer ' . $destination);
    }
    $items = scandir($source);
    if ($items === false) {
        throw new RuntimeException('Impossible de lire ' . $source);
    }
    foreach ($items as $item) {
        if ($item !== '.' && $item !== '..' && $item !== '.DS_Store' && !str_starts_with($item, '.git')) {
            copyTree($source . DIRECTORY_SEPARATOR . $item, $destination . DIRECTORY_SEPARATOR . $item);
        }
    }
}

function pruneProductionTree(string $path): void
{
    $items = scandir($path);
    if ($items === false) {
        throw new RuntimeException('Impossible de lire ' . $path);
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $itemPath = $path . DIRECTORY_SEPARATOR . $item;
        $remove = $item === '.DS_Store'
            || str_starts_with($item, '.git')
            || (is_dir($itemPath) && in_array($item, ['test', 'tests', 'docs', 'examples'], true))
            || (is_file($itemPath) && preg_match('/^(README|CHANGELOG|CONTRIBUTING)(\..+)?$/i', $item) === 1);
        if ($remove) {
            removeTree($itemPath);
        } elseif (is_dir($itemPath) && !is_link($itemPath)) {
            pruneProductionTree($itemPath);
        }
    }
}

try {
    if (!is_dir($buildDir) && !mkdir($buildDir, 0775, true) && !is_dir($buildDir)) {
        throw new RuntimeException('Impossible de créer le dossier build/.');
    }
    removeTree($packageDir);
    if (is_file($zipPath) && !unlink($zipPath)) {
        throw new RuntimeException('Impossible de remplacer ' . $zipPath);
    }

    foreach (['assets', 'src', 'templates', 'ecole2nat.php', 'uninstall.php', 'LICENSE.md', 'composer.json', 'composer.lock'] as $relativePath) {
        copyTree($root . '/' . $relativePath, $packageDir . '/' . $relativePath);
    }

    $composer = getenv('COMPOSER_BINARY') ?: 'composer';
    $command = escapeshellarg($composer)
        . ' install --working-dir=' . escapeshellarg($packageDir)
        . ' --no-dev --prefer-dist --optimize-autoloader --no-interaction';
    passthru($command, $composerExitCode);
    if ($composerExitCode !== 0 || !is_file($packageDir . '/vendor/autoload.php')) {
        throw new RuntimeException('L’installation des dépendances de production a échoué.');
    }
    pruneProductionTree($packageDir . '/vendor');
    if (!unlink($packageDir . '/composer.json') || !unlink($packageDir . '/composer.lock')) {
        throw new RuntimeException('Impossible de nettoyer les fichiers Composer du paquet.');
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Impossible de créer ' . $zipPath);
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($packageDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $relativePath = substr($file->getPathname(), strlen($buildDir) + 1);
            if (!$zip->addFile($file->getPathname(), $relativePath)) {
                $zip->close();
                throw new RuntimeException('Impossible d’ajouter ' . $relativePath . ' à l’archive.');
            }
        }
    }
    if (!$zip->close()) {
        throw new RuntimeException('Impossible de finaliser ' . $zipPath);
    }
    removeTree($packageDir);
    fwrite(STDOUT, "Archive créée : build/ecole2nat-{$version}.zip\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
