<?php

namespace App\Mcp;

use App\Mcp\Tools\DbSchemaTables;
use App\Mcp\Tools\DbSchemaTableDetail;
use App\Mcp\Tools\DbSelectQuery;
use Laravel\Mcp\Server;

class DatabaseServer extends Server
{
    /**
     * Server 名称
     */
    protected string $name = 'Laravel Database MCP Server';

    /**
     * Server 版本
     */
    protected string $version = '1.0.0';

    /**
     * Server 说明
     */
    protected string $instructions = <<<'MARKDOWN'
        This MCP server provides read-only access to the Laravel application database.
        
        Available capabilities:
        - List all database tables with their comments
        - Get detailed schema information for specific tables (columns, indexes, foreign keys)
        - Execute structured SELECT queries (read-only, no raw SQL)
        
        Security restrictions:
        - Only SELECT operations are allowed
        - No write operations (INSERT, UPDATE, DELETE, DROP, etc.)
        - No raw SQL strings accepted
        - Structured queries only with operator whitelist
        - Maximum query limit: 100 rows
    MARKDOWN;

    /**
     * 注册 MCP Tools
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        DbSchemaTables::class,
        DbSchemaTableDetail::class,
        DbSelectQuery::class,
    ];
}

