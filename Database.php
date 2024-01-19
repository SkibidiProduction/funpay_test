<?php

namespace FpDbTest;

use mysqli;
use Exception;
use stdClass;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    /**
     * @param mysqli $mysqli
     */
    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * @param string $query
     * @param array $args
     * @return string
     * @throws Exception
     */
    public function buildQuery(string $query, array $args = []): string
    {
        $resultString = $this->bindParameters($query, $args);
        $resultString = $this->removeUnnecessaryBracketsBlock($resultString);
        $resultString = $this->removeUnnecessaryBrackets($resultString);
        return $this->quoteQueryParameters($resultString);
    }

    /**
     * @return stdClass
     */
    public function skip(): object
    {
        return new stdClass();
    }

    /**
     * Устанавливаем передаваемые параметры вместо плейсхолдеров
     *
     * @param string $query
     * @param array $args
     * @return string
     * @throws Exception
     */
    private function bindParameters(string $query, array $args): string
    {
        $index = 0;
        return preg_replace_callback('/(\?\#|\?d|\?f|\?a|\?)/', function ($match) use ($args, &$index) {
            $this->checkParameterType($match[0], $args[$index]);
            return $this->format($args[$index++]);
        }, $query);
    }

    /**
     * Удаление условных блоков для которых есть параметр со специальным значением.
     *
     * @param string $query
     * @return string
     */
    private function removeUnnecessaryBracketsBlock(string $query): string
    {
        return preg_replace('/{[^}]*%skip:null%[^}]*}/', '', $query);
    }

    /**
     * Удаление узорных скобок для условных блоков без параметров со специальными значениями
     *
     * @param string $query
     * @return string
     */
    private function removeUnnecessaryBrackets(string $query): string
    {
        preg_match_all("/(?<!')\{([^'}]+)(?<!')\}(?!')/", $query);
        return preg_replace_callback("/(?<!')\{([^'}]+)(?<!')\}(?!')/", function ($match) {
            return $match[1];
        }, $query);
    }

    /**
     * Для параметров запроса устанавливаем одинарные кавычки вместо апострофов.
     *
     * @param string $query
     * @return string
     */
    public function quoteQueryParameters(string $query): string
    {
        return preg_replace('/=(\s*)`([^`]+)`/', "=\\1'$2'", $query);
    }

    /**
     * Проверка на совместимость передаваемых типов параметров и плейсхолдеров
     *
     * @param string $match
     * @param mixed $value
     * @return void
     * @throws Exception
     */
    private function checkParameterType(string $match, mixed $value): void
    {
        if ($match === '?' && is_array($value)) {
            throw new Exception('Incompatible parameter type');
        }
        if ($match === '?d' && (!is_int($value) && !is_null($value) && !is_bool($value) && !is_object($value))) {
            throw new Exception('Incompatible parameter type');
        }
        if ($match === '?f' && (!is_float($value) && !is_null($value))) {
            throw new Exception('Incompatible parameter type');
        }
        if ($match === '?a' && !is_array($value)) {
            throw new Exception('Incompatible parameter type');
        }
    }

    /**
     * Преобразование входных параметров к поддерживаемому строковому типу
     *
     * @param mixed $value
     * @return string
     * @throws Exception
     */
    private function format(mixed $value): string
    {
        if (is_string($value)) {
            return "`" . htmlspecialchars($value) . "`";
        }

        if (is_array($value)) {
            if (is_int(key($value))) {
                return implode(', ', array_map(static fn($element) => is_int($element) ? $element : "`$element`", $value));
            }

            return implode(', ', array_map(fn($key, $v) => "`$key` = " . $this->format($v), array_keys($value), $value));
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return (string) $value;
        }

        if (is_null($value)) {
            return 'NULL';
        }

        if (is_object($value)) {
            return '%skip:null%';
        }

        throw new Exception('Undefined type');
    }
}
