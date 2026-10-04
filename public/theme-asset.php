<?php
require __DIR__ . '/runtime-gate.php';
if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) { http_response_code(405); exit; }
$path = (string)($_GET['path'] ?? '');
$types = ['css' => 'text/css', 'js' => 'application/javascript', 'json' => 'application/json', 'map' => 'application/json',
    'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'otf' => 'font/otf', 'eot' => 'application/vnd.ms-fontobject',
    'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'avif' => 'image/avif',
    'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon', 'mp4' => 'video/mp4', 'webm' => 'video/webm'];
if (!preg_match('~^themes/([a-z0-9_-]+)/(?:assets/[a-zA-Z0-9_./-]+|preview\.(?:png|jpg|jpeg|webp|gif|svg))$~D', $path, $match)
    || array_intersect(explode('/', $path), ['..', '.'])) { http_response_code(404); exit; }
$root = realpath(dirname(__DIR__) . '/themes');
$base = realpath(dirname(__DIR__) . '/themes/' . $match[1]);
$file = realpath(dirname(__DIR__) . '/' . $path);
$assets = realpath(dirname(__DIR__) . '/themes/' . $match[1] . '/assets');
$isAsset = str_starts_with($path, 'themes/' . $match[1] . '/assets/');
$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
if ($root === false || $base === false || $file === false || !str_starts_with($base, $root . '/')
    || ($isAsset && ($assets === false || !str_starts_with($assets, $base . '/') || !str_starts_with($file, $assets . '/')))
    || (!$isAsset && dirname($file) !== $base)
    || !str_starts_with($file, $base . '/') || !is_file($file) || !isset($types[$extension])) { http_response_code(404); exit; }
header('Content-Type: ' . $types[$extension]);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=3600');
header('Content-Length: ' . filesize($file));
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') readfile($file);
