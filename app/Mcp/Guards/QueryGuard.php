<?php

namespace App\Mcp\Guards;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
     * @param string|null $connectionName 数据库连接名称
     * @return array|null 返回错误信息数组，如果验证成功返回 null
     */
    public static function validate(array $input, ?string $connectionName = null): ?array
    {
        // 验证表名
        $tableError = self::validateTable($input['table'] ?? null, $connectionName);
        if ($tableError) {
            return $tableError;
        }

        // 验证字段
        if (isset($input['select'])) {
            $columnError = self::validateColumns($input['table'], $input['select'], $connectionName);
            if ($columnError) {
                return $columnError;
            }
        }

        // 验证 WHERE 条件
        if (isset($input['where'])) {
            $whereError = self::validateWhereConditions($input['table'], $input['where'], $connectionName);
            if ($whereError) {
                return $whereError;
            }
        }

        // 验证 LIMIT
        if (isset($input['limit'])) {
            $limitError = self::validateLimit($input['limit']);
            if ($limitError) {
                return $limitError;
            }
        }

        return null;
    }

    /**
     * 验证表名是否存在
     *
     * @param string|null $table
     * @param string|null $connectionName
     * @return array|null 返回错误信息数组，如果验证成功返回 null
     */
    private static function validateTable(?string $table, ?string $connectionName = null): ?array
    {
        if (empty($table)) {
            return [
                'error' => 'Missing Parameter',
                'message' => 'Table name is required',
                'parameter' => 'table'
            ];
        }

        try {
            $hasTable = $connectionName 
                ? Schema::connection($connectionName)->hasTable($table)
                : Schema::hasTable($table);
            
            if (!$hasTable) {
                return [
                    'error' => 'Table Not Found',
                    'message' => "Table '{$table}' does not exist",
                    'table' => $table,
                    'type' => 'table_not_found'
                ];
            }
        } catch (\Exception $e) {
            return [
                'error' => 'Database Error',
                'message' => 'Failed to verify table existence',
                'table' => $table,
                'details' => $e->getMessage()
            ];
        }

        return null;
    }

    /**
     * 验证字段是否存在
     *
     * @param string $table
     * @param array $columns
     * @param string|null $connectionName
     * @return array|null 返回错误信息数组，如果验证成功返回 null
     */
    private static function validateColumns(string $table, array $columns, ?string $connectionName = null): ?array
    {
        if (empty($columns)) {
            return [
                'error' => 'Validation Error',
                'message' => 'Select columns cannot be empty',
                'parameter' => 'select'
            ];
        }

        try {
            $tableColumns = $connectionName 
                ? Schema::connection($connectionName)->getColumnListing($table)
                : Schema::getColumnListing($table);

            foreach ($columns as $column) {
                // 允许 * 通配符
                if ($column === '*') {
                    continue;
                }

                // 检查字段是否存在
                if (!in_array($column, $tableColumns, true)) {
                    return [
                        'error' => 'Column Not Found',
                        'message' => "Column '{$column}' does not exist in table '{$table}'",
                        'column' => $column,
                        'table' => $table,
                        'type' => 'column_not_found',
                        'available_columns' => array_slice($tableColumns, 0, 10) // 返回前 10 列
                    ];
                }
            }
        } catch (\Exception $e) {
            return [
                'error' => 'Database Error',
                'message' => 'Failed to verify column existence',
                'table' => $table,
                'details' => $e->getMessage()
            ];
        }

        return null;
    }

    /**
     * 验证 WHERE 条件
     *
     * @param string $table
     * @param array $conditions
     * @param string|null $connectionName
     * @return array|null 返回错误信息数组，如果验证成功返回 null
     */
    private static function validateWhereConditions(string $table, array $conditions, ?string $connectionName = null): ?array
    {
        if (!is_array($conditions)) {
            return [
                'error' => 'Validation Error',
                'message' => 'WHERE conditions must be an array',
                'parameter' => 'where',
                'type' => 'invalid_format'
            ];
        }

        try {
            $tableColumns = $connectionName 
                ? Schema::connection($connectionName)->getColumnListing($table)
                : Schema::getColumnListing($table);

            foreach ($conditions as $idx => $condition) {
                if (!is_array($condition) || count($condition) < 2) {
                    return [
                        'error' => 'Validation Error',
                        'message' => 'Invalid WHERE condition format',
                        'parameter' => 'where',
                        'condition_index' => $idx,
                        'expected_format' => '[column, value] or [column, operator, value]',
                        'type' => 'invalid_format'
                    ];
                }

                $column = $condition[0];
                
                // 如果只有两个元素，则第二个是值，操作符默认为 =
                if (count($condition) === 2) {
                    $operator = '=';
                } else {
                    $operator = $condition[1];
                }

                // 验证字段存在
                if (!in_array($column, $tableColumns, true)) {
                    return [
                        'error' => 'Column Not Found',
                        'message' => "Column '{$column}' does not exist in table '{$table}'",
                        'column' => $column,
                        'table' => $table,
                        'condition_index' => $idx,
                        'type' => 'column_not_found'
                    ];
                }

                // 验证操作符在白名单中
                if (!in_array(strtolower($operator), array_map('strtolower', self::ALLOWED_OPERATORS), true)) {
                    return [
                        'error' => 'Invalid Operator',
                        'message' => "Operator '{$operator}' is not allowed",
                        'operator' => $operator,
                        'condition_index' => $idx,
                        'allowed_operators' => self::ALLOWED_OPERATORS,
                        'type' => 'invalid_operator'
                    ];
                }
            }
        } catch (\Exception $e) {
            return [
                'error' => 'Database Error',
                'message' => 'Failed to verify WHERE conditions',
                'table' => $table,
                'details' => $e->getMessage()
            ];
        }

        return null;
    }

    /**
     * 验证并规范化 LIMIT
     *
     * @param mixed $limit
     * @return array|null 返回错误信息数组，如果验证成功返回 null
     */
    private static function validateLimit($limit): ?array
    {
        if (!is_numeric($limit) || $limit < 1) {
            return [
                'error' => 'Validation Error',
                'message' => 'Limit must be a positive integer',
                'provided_value' => $limit,
                'parameter' => 'limit',
                'type' => 'invalid_value'
            ];
        }

        if ($limit > self::MAX_LIMIT) {
            return [
                'error' => 'Limit Exceeded',
                'message' => "Limit cannot exceed " . self::MAX_LIMIT,
                'provided_limit' => $limit,
                'max_limit' => self::MAX_LIMIT,
                'parameter' => 'limit',
                'type' => 'limit_exceeded'
            ];
        }

        return null;
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

