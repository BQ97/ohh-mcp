<?php

namespace App\Mcp\Tools;

use App\Mcp\Database\DbConnectionResolver;
use App\Mcp\Responses\ErrorResponse;
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
    protected string $description = <<<'MARKDOWN'
        Get all tables in the database with their comments.
        
        IMPORTANT: The 'project' parameter must be resolved from .ai/project.json in the business repository.
        Never hardcode or guess project identifiers.
        
        Example: {"project": "housekeep"}
    MARKDOWN;

    /**
     * 输入 Schema
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()
                ->description('Project identifier (housekeep, nyw, or esign). REQUIRED: Must be provided.')
                ->required(),
        ];
    }

    /**
     * 执行 Tool
     */
    public function handle(Request $request): Response
    {
        try {
            // 解析项目并获取数据库连接
            $project = $request->string('project', '');
            
            // 如果 project 为空，返回友好的错误信息
            if (empty($project)) {
                $availableProjects = DbConnectionResolver::getAvailableProjects();
                return ErrorResponse::missingProject($availableProjects);
            }
            
            $connectionName = DbConnectionResolver::resolve($project);
            
        } catch (\InvalidArgumentException $e) {
            // 处理项目不存在的错误
            $availableProjects = DbConnectionResolver::getAvailableProjects();
            
            if (str_contains($e->getMessage(), 'project_missing')) {
                return ErrorResponse::missingProject($availableProjects);
            }
            
            if (str_contains($e->getMessage(), 'project_not_found')) {
                $projectName = str_replace('project_not_found:', '', $e->getMessage());
                return ErrorResponse::projectNotFound($projectName, $availableProjects);
            }
            
            return ErrorResponse::generic($e->getMessage(), 'Error');
        } catch (\Exception $e) {
            $availableProjects = DbConnectionResolver::getAvailableProjects();
            return ErrorResponse::connectionFailed($request->string('project', 'unknown'), $e->getMessage());
        }

        try {
            $connection = DB::connection($connectionName);
            $databaseName = $connection->getDatabaseName();
            $driver = $connection->getDriverName();

            // 获取所有表名（兼容不同数据库）
            $tableNames = $this->getAllTableNames($connectionName, $driver, $databaseName);

            $tables = [];
            foreach ($tableNames as $tableName) {
                // 获取表注释
                $comment = $this->getTableComment($connectionName, $tableName, $driver);

                $tables[] = [
                    'name' => $tableName,
                    'comment' => $comment,
                ];
            }

            $result = [
                'project' => $project,
                'database' => $databaseName,
                'driver' => $driver,
                'tables' => $tables,
                'count' => count($tables),
            ];

            return Response::json($result);
        } catch (\Exception $e) {
            return ErrorResponse::generic(
                "Failed to retrieve tables for project '{$project}': " . $e->getMessage(),
                'Database Error'
            );
        }
    }

    /**
     * 获取所有表名（兼容不同数据库驱动）
     */
    private function getAllTableNames(string $connectionName, string $driver, string $databaseName): array
    {
        $tables = [];

        try {
            if ($driver === 'mysql') {
                $results = DB::connection($connectionName)->select('SHOW TABLES');
                $key = 'Tables_in_' . $databaseName;
                foreach ($results as $result) {
                    $tables[] = $result->$key;
                }
            } elseif ($driver === 'pgsql') {
                $results = DB::connection($connectionName)->select(
                    "SELECT tablename FROM pg_catalog.pg_tables 
                     WHERE schemaname = 'public'"
                );
                foreach ($results as $result) {
                    $tables[] = $result->tablename;
                }
            } elseif ($driver === 'sqlite') {
                $results = DB::connection($connectionName)->select(
                    "SELECT name FROM sqlite_master 
                     WHERE type='table' AND name NOT LIKE 'sqlite_%'"
                );
                foreach ($results as $result) {
                    $tables[] = $result->name;
                }
            } elseif ($driver === 'sqlsrv') {
                $results = DB::connection($connectionName)->select(
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
    private function getTableComment(string $connectionName, string $tableName, string $driver): ?string
    {
        try {
            if ($driver === 'mysql') {
                $result = DB::connection($connectionName)->selectOne(
                    "SELECT TABLE_COMMENT 
                     FROM information_schema.TABLES 
                     WHERE TABLE_SCHEMA = DATABASE() 
                     AND TABLE_NAME = ?",
                    [$tableName]
                );

                return $result->TABLE_COMMENT ?? null;
            }

            if ($driver === 'pgsql') {
                $result = DB::connection($connectionName)->selectOne(
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

