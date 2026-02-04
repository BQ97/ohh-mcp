<?php

namespace App\Mcp\Database;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class DbConnectionResolver
{
    /**
     * 运行时连接名称
     */
    private const RUNTIME_CONNECTION = 'mcp_runtime';

    /**
     * 解析项目标识符并返回数据库连接名称
     *
     * @param string $project 项目标识符
     * @return string 数据库连接名称
     * @throws RuntimeException 当项目不存在时
     */
    public static function resolve(string $project): string
    {
        // 验证 project 参数不为空
        if (empty($project)) {
            $available = implode(', ', array_keys(config('mcp_projects', [])));
            throw new RuntimeException(
                "MCP project parameter is required. Available projects: {$available}"
            );
        }

        $config = config("mcp_projects.{$project}");

        if (!$config) {
            $available = implode(', ', array_keys(config('mcp_projects', [])));
            throw new RuntimeException(
                "Unknown MCP project: '{$project}'. Available projects: {$available}"
            );
        }

        // 动态注入运行时连接配置
        config([
            'database.connections.' . self::RUNTIME_CONNECTION => $config,
        ]);

        // 清除旧连接，确保使用新配置
        DB::purge(self::RUNTIME_CONNECTION);
        DB::reconnect(self::RUNTIME_CONNECTION);

        return self::RUNTIME_CONNECTION;
    }

    /**
     * 获取运行时连接名称
     *
     * @return string
     */
    public static function getRuntimeConnectionName(): string
    {
        return self::RUNTIME_CONNECTION;
    }
}

