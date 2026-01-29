<?php

namespace App\Mcp\Guards;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class QueryGuard
{
    /**
     * 允许的操作符白名单
     */
    private const ALLOWED_OPERATORS = [
        '=', '!=', '>', '<', '>=', '<=', 'like', 'LIKE',
        '<>', 'in', 'IN', 'not in', 'NOT IN',
        'is null', 'IS NULL', 'is not null', 'IS NOT NULL'
    ];

    /**
     * 最大查询限制
     */
    private const MAX_LIMIT = 100;

    /**
     * 验证查询输入
     *
     * @param array $input
     * @throws InvalidArgumentException
     */
    public static function validate(array $input): void
    {
        // 验证表名
        self::validateTable($input['table'] ?? null);

        // 验证字段
        if (isset($input['select'])) {
            self::validateColumns($input['table'], $input['select']);
        }

        // 验证 WHERE 条件
        if (isset($input['where'])) {
            self::validateWhereConditions($input['table'], $input['where']);
        }

        // 验证 LIMIT
        if (isset($input['limit'])) {
            self::validateLimit($input['limit']);
        }
    }

    /**
     * 验证表名是否存在
     *
     * @param string|null $table
     * @throws InvalidArgumentException
     */
    private static function validateTable(?string $table): void
    {
        if (empty($table)) {
            throw new InvalidArgumentException('Table name is required');
        }

        if (!Schema::hasTable($table)) {
            throw new InvalidArgumentException("Table '{$table}' does not exist");
        }
    }

    /**
     * 验证字段是否存在
     *
     * @param string $table
     * @param array $columns
     * @throws InvalidArgumentException
     */
    private static function validateColumns(string $table, array $columns): void
    {
        if (empty($columns)) {
            throw new InvalidArgumentException('Select columns cannot be empty');
        }

        $tableColumns = Schema::getColumnListing($table);

        foreach ($columns as $column) {
            // 允许 * 通配符
            if ($column === '*') {
                continue;
            }

            // 检查字段是否存在
            if (!in_array($column, $tableColumns, true)) {
                throw new InvalidArgumentException("Column '{$column}' does not exist in table '{$table}'");
            }
        }
    }

    /**
     * 验证 WHERE 条件
     *
     * @param string $table
     * @param array $conditions
     * @throws InvalidArgumentException
     */
    private static function validateWhereConditions(string $table, array $conditions): void
    {
        if (!is_array($conditions)) {
            throw new InvalidArgumentException('WHERE conditions must be an array');
        }

        $tableColumns = Schema::getColumnListing($table);

        foreach ($conditions as $condition) {
            if (!is_array($condition) || count($condition) < 2) {
                throw new InvalidArgumentException('Invalid WHERE condition format');
            }

            $column = $condition[0];
            $operator = $condition[1] ?? '=';
            
            // 如果只有两个元素，则第二个是值，操作符默认为 =
            if (count($condition) === 2) {
                $operator = '=';
            }

            // 验证字段存在
            if (!in_array($column, $tableColumns, true)) {
                throw new InvalidArgumentException("Column '{$column}' does not exist in table '{$table}'");
            }

            // 验证操作符在白名单中
            if (!in_array(strtolower($operator), array_map('strtolower', self::ALLOWED_OPERATORS), true)) {
                throw new InvalidArgumentException("Operator '{$operator}' is not allowed");
            }
        }
    }

    /**
     * 验证并规范化 LIMIT
     *
     * @param mixed $limit
     * @throws InvalidArgumentException
     */
    private static function validateLimit($limit): void
    {
        if (!is_numeric($limit) || $limit < 1) {
            throw new InvalidArgumentException('Limit must be a positive integer');
        }

        if ($limit > self::MAX_LIMIT) {
            throw new InvalidArgumentException("Limit cannot exceed " . self::MAX_LIMIT);
        }
    }

    /**
     * 获取安全的 LIMIT 值
     *
     * @param int|null $limit
     * @return int
     */
    public static function getSafeLimit(?int $limit): int
    {
        if ($limit === null) {
            return 20;
        }

        return min($limit, self::MAX_LIMIT);
    }
}

