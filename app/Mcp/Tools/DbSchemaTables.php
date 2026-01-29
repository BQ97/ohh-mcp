<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class DbSchemaTables extends Tool
{
    /**
     * Tool 描述
     */
    protected string $description = 'Get all tables in the database with their comments';

    /**
     * 输入 Schema
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    /**
     * 执行 Tool
     */
    public function handle(Request $request): Response
    {
        $connection = DB::connection();
        $databaseName = $connection->getDatabaseName();
        $driver = $connection->getDriverName();

        // 获取所有表名（兼容不同数据库）
        $tableNames = $this->getAllTableNames($driver, $databaseName);

        $tables = [];
        foreach ($tableNames as $tableName) {
            // 获取表注释
            $comment = $this->getTableComment($tableName, $driver);

            $tables[] = [
                'name' => $tableName,
                'comment' => $comment,
            ];
        }

        $result = [
            'database' => $databaseName,
            'driver' => $driver,
            'tables' => $tables,
            'count' => count($tables),
        ];

        return Response::json($result);
    }

    /**
     * 获取所有表名（兼容不同数据库驱动）
     */
    private function getAllTableNames(string $driver, string $databaseName): array
    {
        $tables = [];

        try {
            if ($driver === 'mysql') {
                $results = DB::select('SHOW TABLES');
                $key = 'Tables_in_' . $databaseName;
                foreach ($results as $result) {
                    $tables[] = $result->$key;
                }
            } elseif ($driver === 'pgsql') {
                $results = DB::select(
                    "SELECT tablename FROM pg_catalog.pg_tables 
                     WHERE schemaname = 'public'"
                );
                foreach ($results as $result) {
                    $tables[] = $result->tablename;
                }
            } elseif ($driver === 'sqlite') {
                $results = DB::select(
                    "SELECT name FROM sqlite_master 
                     WHERE type='table' AND name NOT LIKE 'sqlite_%'"
                );
                foreach ($results as $result) {
                    $tables[] = $result->name;
                }
            } elseif ($driver === 'sqlsrv') {
                $results = DB::select(
                    "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES 
                     WHERE TABLE_TYPE = 'BASE TABLE'"
                );
                foreach ($results as $result) {
                    $tables[] = $result->TABLE_NAME;
                }
            }
        } catch (\Exception $e) {
            // 如果查询失败，返回空数组
        }

        return $tables;
    }

    /**
     * 获取表注释
     */
    private function getTableComment(string $tableName, string $driver): ?string
    {
        try {
            if ($driver === 'mysql') {
                $result = DB::selectOne(
                    "SELECT TABLE_COMMENT 
                     FROM information_schema.TABLES 
                     WHERE TABLE_SCHEMA = DATABASE() 
                     AND TABLE_NAME = ?",
                    [$tableName]
                );

                return $result->TABLE_COMMENT ?? null;
            }

            if ($driver === 'pgsql') {
                $result = DB::selectOne(
                    "SELECT obj_description(oid) as comment 
                     FROM pg_class 
                     WHERE relname = ? AND relkind = 'r'",
                    [$tableName]
                );

                return $result->comment ?? null;
            }

            // SQLite 和其他数据库不支持表注释
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
}

