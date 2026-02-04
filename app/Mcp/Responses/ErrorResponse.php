<?php

namespace App\Mcp\Responses;

use Laravel\Mcp\Response;

/**
 * MCP 错误响应处理类
 * 
 * 将各种异常统一转换为友好的 JSON 错误响应
 */
class ErrorResponse
{
    /**
     * 项目不存在错误
     */
    public static function projectNotFound(string $project, array $availableProjects): Response
    {
        return Response::json([
            'error' => 'Project Not Found',
            'message' => "The project '{$project}' does not exist.",
            'available_projects' => $availableProjects,
            'hint' => 'Make sure the project identifier is correct and is configured in config/mcp_projects.php',
        ]);
    }

    /**
     * 缺少项目参数
     */
    public static function missingProject(array $availableProjects): Response
    {
        return Response::json([
            'error' => 'Missing Required Parameter',
            'message' => "The 'project' parameter is required.",
            'available_projects' => $availableProjects,
            'example' => [
                'project' => !empty($availableProjects) ? $availableProjects[0] : 'project_name',
            ],
        ]);
    }

    /**
     * 表不存在错误
     */
    public static function tableNotFound(string $table, string $project): Response
    {
        return Response::json([
            'error' => 'Table Not Found',
            'message' => "The table '{$table}' does not exist in project '{$project}'.",
            'table' => $table,
            'project' => $project,
            'hint' => 'Use the db_schema_tables tool to list all available tables in this project.',
        ]);
    }

    /**
     * 列不存在错误
     */
    public static function columnNotFound(string $column, string $table, string $project): Response
    {
        return Response::json([
            'error' => 'Column Not Found',
            'message' => "The column '{$column}' does not exist in table '{$table}' (project: '{$project}').",
            'column' => $column,
            'table' => $table,
            'project' => $project,
            'hint' => 'Use the db_schema_table_detail tool to see all available columns in this table.',
        ]);
    }

    /**
     * 无效的操作符
     */
    public static function invalidOperator(string $operator): Response
    {
        return Response::json([
            'error' => 'Invalid Operator',
            'message' => "The operator '{$operator}' is not allowed.",
            'operator' => $operator,
            'allowed_operators' => ['=', '!=', '>', '<', '>=', '<=', 'like', '<>', 'in', 'not in', 'is null', 'is not null'],
            'hint' => 'Use only the allowed operators listed above.',
        ]);
    }

    /**
     * LIMIT 超过限制
     */
    public static function limitExceeded(int $limit, int $maxLimit): Response
    {
        return Response::json([
            'error' => 'Limit Exceeded',
            'message' => "The limit value ({$limit}) exceeds the maximum allowed value ({$maxLimit}).",
            'provided_limit' => $limit,
            'max_limit' => $maxLimit,
            'hint' => 'Reduce the limit value or use pagination with offset.',
        ]);
    }

    /**
     * 数据库连接失败
     */
    public static function connectionFailed(string $project, string $reason): Response
    {
        return Response::json([
            'error' => 'Database Connection Failed',
            'message' => "Failed to connect to the database for project '{$project}'.",
            'project' => $project,
            'details' => $reason,
            'hint' => 'Check the database configuration in config/mcp_projects.php and ensure the database server is running.',
        ]);
    }

    /**
     * 通用错误响应
     */
    public static function generic(string $message, string $errorType = 'Error'): Response
    {
        return Response::json([
            'error' => $errorType,
            'message' => $message,
        ]);
    }

    /**
     * 从异常获取友好的错误响应
     */
    public static function fromException(\Exception $exception, array $context = []): Response
    {
        $message = $exception->getMessage();

        // 处理表不存在的异常
        if (str_contains($message, 'does not exist') && isset($context['table'])) {
            return self::tableNotFound($context['table'], $context['project'] ?? 'unknown');
        }

        // 处理列不存在的异常
        if (str_contains($message, 'does not exist in table') && isset($context['column']) && isset($context['table'])) {
            return self::columnNotFound($context['column'], $context['table'], $context['project'] ?? 'unknown');
        }

        // 处理项目不存在的异常
        if (str_contains($message, 'Unknown MCP project')) {
            $availableProjects = array_keys(config('mcp_projects', []));
            return self::projectNotFound($context['project'] ?? 'unknown', $availableProjects);
        }

        // 处理无效操作符
        if (str_contains($message, 'is not allowed')) {
            return self::generic($message, 'Validation Error');
        }

        // 默认错误处理
        return self::generic($message, 'Error');
    }
}
