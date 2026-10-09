<?php

declare(strict_types=1);

namespace GaussDb\Compat;

use InvalidArgumentException;
use PDO;

/** Replaces only empty string parameters with a constant, never interpolates data. */
final class EmptyStringParameters
{
    /** @param array<int|string, array> $bindings @return array{0:string,1:array} */
    public static function compile(string $sql, array $bindings): array
    {
        $output = '';
        $parameters = [];
        $used = [];
        $position = 0;
        $style = null;
        $length = strlen($sql);
        for ($i = 0; $i < $length;) {
            $char = $sql[$i];
            // Quoted values/identifiers, including doubled quotes and backslash escapes.
            if ($char === "'" || $char === '"' || $char === '`') {
                $start = $i++;
                while ($i < $length) {
                    if ($sql[$i] === '\\') { $i += 2; continue; }
                    if ($sql[$i++] === $char) {
                        if ($i < $length && $sql[$i] === $char) { ++$i; continue; }
                        break;
                    }
                }
                $output .= substr($sql, $start, $i - $start);
                continue;
            }
            if (substr($sql, $i, 2) === '--') {
                $end = strpos($sql, "\n", $i);
                $end = $end === false ? $length : $end;
                $output .= substr($sql, $i, $end - $i);
                $i = $end;
                continue;
            }
            if (substr($sql, $i, 2) === '/*') {
                $start = $i;
                $i += 2;
                $depth = 1;
                while ($i < $length && $depth > 0) {
                    $pair = substr($sql, $i, 2);
                    if ($pair === '/*') { ++$depth; $i += 2; }
                    elseif ($pair === '*/') { --$depth; $i += 2; }
                    else { ++$i; }
                }
                $output .= substr($sql, $start, $i - $start);
                continue;
            }
            if ($char === '$' && preg_match('/\A\$(?:[a-zA-Z_][a-zA-Z0-9_]*)?\$/', substr($sql, $i), $match)) {
                $end = strpos($sql, $match[0], $i + strlen($match[0]));
                $end = $end === false ? $length : $end + strlen($match[0]);
                $output .= substr($sql, $i, $end - $i);
                $i = $end;
                continue;
            }
            $key = null;
            $tokenLength = 1;
            if ($char === '?') {
                $key = ++$position;
                $currentStyle = 'positional';
            } elseif ($char === ':' && ($i === 0 || $sql[$i - 1] !== ':') &&
                preg_match('/\A:[a-zA-Z_][a-zA-Z0-9_]*/', substr($sql, $i), $match)) {
                $key = $match[0];
                $tokenLength = strlen($key);
                $currentStyle = 'named';
            }
            if ($key === null) { $output .= $char; ++$i; continue; }
            if ($style !== null && $style !== $currentStyle) {
                throw new InvalidArgumentException('Cannot mix named and positional parameters');
            }
            $style = $currentStyle;
            if (!array_key_exists($key, $bindings)) {
                throw new InvalidArgumentException('Missing SQL parameter: ' . $key);
            }
            $used[$key] = true;
            [$value, $type] = $bindings[$key];
            if (self::isEmpty($value, $type)) {
                $output .= "''";
            } else {
                $output .= '?';
                $parameters[count($parameters) + 1] = [$value, $type];
            }
            $i += $tokenLength;
        }
        if (count($used) !== count($bindings)) {
            throw new InvalidArgumentException('Parameter does not match a SQL placeholder');
        }
        return [$output, $parameters];
    }

    /** @param mixed $value */
    public static function isEmpty($value, int $type): bool
    {
        return $value === '' && ($type === PDO::PARAM_STR || $type === PDO::PARAM_LOB);
    }
}
