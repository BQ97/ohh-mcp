<?php

namespace App\Mcp\Tools;

use App\Mcp\Database\DbConnectionResolver;
use App\Mcp\Guards\QueryGuard;
use App\Mcp\Responses\ErrorResponse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class DbSchemaTableDetail extends Tool
{
    /**
     * Tool 描述
     */
    protected string $description = <<<'MARKDOWN'
        Get detailed schema information for a specific table including columns, indexes, and foreign keys.
        
        IMPORTANT: The 'project' parameter must be resolved from .ai/project.json in the business repository.
        Never hardcode or guess project identifiers.
        
        Example: {"project": "housekeep", "table": "users"}
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
            'table' => $schema->string()
                ->description('The name of the table to get details for')
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
            $tableName = $request->string('table', '');

            // 验证表是否存在
            $validationError = QueryGuard::validate(['table' => $tableName], $connectionName);
            if ($validationError) {
                // 检查是否是表不存在的错误
                if ($validationError['type'] === 'table_not_found') {
                    return ErrorResponse::tableNotFound($tableName, $project);
                }
                return Response::json($validationError);
            }

            $connection = DB::connection($connectionName);
            $driver = $connection->getDriverName();

            $result = [
                'project' => $project,
                'table' => $tableName,
                'columns' => $this->getColumns($connectionName, $tableName, $driver),
                'indexes' => $this->getIndexes($connectionName, $tableName, $driver),
                'foreign_keys' => $this->getForeignKeys($connectionName, $tableName, $driver),
            ];

            return Response::json($result);
        } catch (\Exception $e) {
            return ErrorResponse::generic(
                "Failed to retrieve table details for '{$tableName}': " . $e->getMessage(),
                'Database Error'
            );
        }
    }

    /**
     * 获取表的所有字段信息
     */
    private function getColumns(string $connectionName, string $tableName, string $driver): array
    {
        $columns = [];

        if ($driver === 'mysql') {
            $results = DB::connection($connectionName)->select(
                "SELECT 
                    COLUMN_NAME as name,
                    COLUMN_TYPE as type,
                    IS_NULLABLE as nullable,
                    COLUMN_DEFAULT as default_value,
                    COLUMN_KEY as key,
                    EXTRA as extra,
                    COLUMN_COMMENT as comment
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                ORDER BY ORDINAL_POSITION",
                [$tableName]
            );

            foreach ($results as $column) {
                $columns[] = [
                    'name' => $column->name,
                    'type' => $column->type,
                    'nullable' => $column->nullable === 'YES',
                    'default' => $column->default_value,
                    'key' => $column->key,
                    'extra' => $column->extra,
                    'comment' => $column->comment,
                ];
            }
        } elseif ($driver === 'pgsql') {
            $results = DB::connection($connectionName)->select(
                "SELECT 
                    column_name as name,
                    data_type as type,
                    is_nullable as nullable,
                    column_default as default_value
                FROM information_schema.columns
                WHERE table_name = ?
                ORDER BY ordinal_position",
                [$tableName]
            );

            foreach ($results as $column) {
                $columns[] = [
                    'name' => $column->name,
                    'type' => $column->type,
                    'nullable' => $column->nullable === 'YES',
                    'default' => $column->default_value,
                    'key' => '',
                    'extra' => '',
                    'comment' => null,
                ];
            }
        } elseif ($driver === 'sqlite') {
            $results = DB::connection($connectionName)->select("PRAGMA table_info({$tableName})");

            foreach ($results as $column) {
                $columns[] = [
                    'name' => $column->name,
                    'type' => $column->type,
                    'nullable' => $column->notnull == 0,
                    'default' => $column->dflt_value,
                    'key' => $column->pk ? 'PRI' : '',
                    'extra' => '',
                    'comment' => null,
                ];
            }
        }

        return $columns;
    }

    /**
     * 获取表的索引信息
     */
    private function getIndexes(string $connectionName, string $tableName, string $driver): array
    {
        $indexes = [];

        try {
            if ($driver === 'mysql') {
                $results = DB::connection($connectionName)->select("SHOW INDEX FROM {$tableName}");

                $indexGroups = [];
                foreach ($results as $index) {
                    $indexGroups[$index->Key_name][] = $index;
                }

                foreach ($indexGroups as $indexName => $indexColumns) {
                    $indexes[] = [
                        'name' => $indexName,
                        'columns' => array_map(fn($col) => $col->Column_name, $indexColumns),
                        'unique' => $indexColumns[0]->Non_unique == 0,
                        'type' => $indexColumns[0]->Index_type,
                    ];
                }
            } elseif ($driver === 'pgsql') {
                $results = DB::connection($connectionName)->select(
                    "SELECT
                        i.relname as index_name,
                        a.attname as column_name,
                        ix.indisunique as is_unique
                    FROM pg_class t
                    JOIN pg_index ix ON t.oid = ix.indrelid
                    JOIN pg_class i ON i.oid = ix.indexrelid
                    JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY(ix.indkey)
                    WHERE t.relname = ?
                    ORDER BY i.relname, a.attnum",
                    [$tableName]
                );

                $indexGroups = [];
                foreach ($results as $index) {
                    $indexGroups[$index->index_name][] = $index;
                }

                foreach ($indexGroups as $indexName => $indexColumns) {
                    $indexes[] = [
                        'name' => $indexName,
                        'columns' => array_map(fn($col) => $col->column_name, $indexColumns),
                        'unique' => (bool) $indexColumns[0]->is_unique,
                        'type' => 'BTREE',
                    ];
                }
            } elseif ($driver === 'sqlite') {
                $results = DB::connection($connectionName)->select("PRAGMA index_list({$tableName})");

                foreach ($results as $index) {
                    $indexInfo = DB::connection($connectionName)->select("PRAGMA index_info({$index->name})");
                    $columns = array_map(fn($col) => $col->name, $indexInfo);

                    $indexes[] = [
                        'name' => $index->name,
                        'columns' => $columns,
                        'unique' => (bool) $index->unique,
                        'type' => 'BTREE',
                    ];
                }
            }
        } catch (\Exception $e) {
            // 如果获取索引失败，返回空数组
        }

        return $indexes;
    }

    /**
     * 获取表的外键信息
     */
    private function getForeignKeys(string $connectionName, string $tableName, string $driver): array
    {
        $foreignKeys = [];

        try {
            if ($driver === 'mysql') {
                $results = DB::connection($connectionName)->select(
                    "SELECT
                        CONSTRAINT_NAME as name,
                        COLUMN_NAME as column,
                        REFERENCED_TABLE_NAME as referenced_table,
                        REFERENCED_COLUMN_NAME as referenced_column
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = ?
                    AND REFERENCED_TABLE_NAME IS NOT NULL",
                    [$tableName]
                );

                foreach ($results as $fk) {
                    $foreignKeys[] = [
                        'name' => $fk->name,
                        'column' => $fk->column,
                        'referenced_table' => $fk->referenced_table,
                        'referenced_column' => $fk->referenced_column,
                    ];
                }
            } elseif ($driver === 'pgsql') {
                $results = DB::connection($connectionName)->select(
                    "SELECT
                        tc.constraint_name as name,
                        kcu.column_name as column,
                        ccu.table_name as referenced_table,
                        ccu.column_name as referenced_column
                    FROM information_schema.table_constraints tc
                    JOIN information_schema.key_column_usage kcu
                        ON tc.constraint_name = kcu.constraint_name
                    JOIN information_schema.constraint_column_usage ccu
                        ON ccu.constraint_name = tc.constraint_name
                    WHERE tc.constraint_type = 'FOREIGN KEY'
                    AND tc.table_name = ?",
                    [$tableName]
                );

                foreach ($results as $fk) {
                    $foreignKeys[] = [
                        'name' => $fk->name,
                        'column' => $fk->column,
                        'referenced_table' => $fk->referenced_table,
                        'referenced_column' => $fk->referenced_column,
                    ];
                }
            } elseif ($driver === 'sqlite') {
                $results = DB::connection($connectionName)->select("PRAGMA foreign_key_list({$tableName})");

                foreach ($results as $fk) {
                    $foreignKeys[] = [
                        'name' => "fk_{$tableName}_{$fk->from}",
                        'column' => $fk->from,
                        'referenced_table' => $fk->table,
                        'referenced_column' => $fk->to,
                    ];
                }
            }
        } catch (\Exception $e) {
            // 如果获取外键失败，返回空数组
        }

        return $foreignKeys;
    }
}

