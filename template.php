<?php

declare(strict_types=1);

/**
 * Render a lightweight template using values from an array.
 *
 * Supported syntax:
 *   [name]                         Scalar value
 *   [user|name]                   Nested value
 *   [%users%<li>{name}</li>]      Repeat a block for an array of rows
 *
 * Set $escapeHtml to true when values come from an untrusted source.
 */
function template(
    array $data,
    string $template,
    ?string $regexp = null,
    bool $escapeHtml = false,
    ?string $templateRoot = null
): string {
    if (is_file($template)) {
        $path = realpath($template);
        if ($templateRoot !== null) {
            $root = realpath($templateRoot);
            if ($path === false || $root === false || !isWithinDirectory($path, $root)) {
                throw new InvalidArgumentException('Template file is outside the allowed directory.');
            }
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Unable to read template file: {$template}");
        }
        $template = $contents;
    }

    $regexp ??= '/\[.*?\]/s';
    $result = preg_replace_callback(
        $regexp,
        static function (array $match) use ($data, $escapeHtml): string {
            $token = substr($match[0], 1, -1);
            if ($token === '') {
                return $match[0];
            }

            if (str_starts_with($token, '%')) {
                $parts = explode('%', $token, 3);
                if (count($parts) !== 3 || $parts[0] !== '') {
                    throw new InvalidArgumentException("Invalid loop token: {$match[0]}");
                }
                [$unused, $path, $rowTemplate] = $parts;
                $found = false;
                $rows = templateGet($data, $path, $found);
                if (!$found) {
                    return '';
                }
                if (!is_array($rows)) {
                    throw new UnexpectedValueException("Loop source '{$path}' must be an array.");
                }

                $output = '';
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        throw new UnexpectedValueException("Loop item in '{$path}' must be an array.");
                    }
                    $output .= template($row, $rowTemplate, '/\{.*?\}/s', $escapeHtml);
                }
                return $output;
            }

            $found = false;
            $value = templateGet($data, $token, $found);
            if (!$found) {
                return "Error: {$token}";
            }
            if (is_array($value) || is_object($value)) {
                throw new UnexpectedValueException("Template value '{$token}' must be scalar.");
            }
            return $escapeHtml
                ? htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                : (string) $value;
        },
        $template
    );

    if ($result === null) {
        throw new InvalidArgumentException('Invalid template regular expression.');
    }
    return $result;
}

function templateGet(array $data, string $path, bool &$found = false): mixed
{
    $value = $data;
    foreach (explode('|', $path) as $key) {
        if (!is_array($value) || !array_key_exists($key, $value)) {
            $found = false;
            return null;
        }
        $value = $value[$key];
    }
    $found = true;
    return $value;
}

function isWithinDirectory(string $path, string $root): bool
{
    $root = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    return str_starts_with($path, $root) || $path === rtrim($root, DIRECTORY_SEPARATOR);
}
