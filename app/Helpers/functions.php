<?php

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function format_number(mixed $value, int $decimals = 2): string
{
    if ($value === null || $value === '' || !is_numeric($value)) {
        return (string) $value;
    }

    return rtrim(rtrim(number_format((float) $value, $decimals, '.', ''), '0'), '.');
}

function url(string $path = ''): string
{
    return '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $path=ltrim($path,'/');
    $file=dirname(__DIR__,2).'/public/'.$path;
    $version=is_file($file)?(string)filemtime($file):'1';
    return '/'.$path.'?v='.rawurlencode($version);
}

function route(string $name,array $parameters=[]): string
{
    return \App\Core\Router::urlFor($name,$parameters);
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['_old'][$key] ?? $default;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(\App\Core\Csrf::token()) . '">';
}

function flash(string $key): ?string
{
    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $value;
}
