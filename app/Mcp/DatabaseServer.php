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
    protected string $name = 'Laravel Database MCP Server (Project-Aware)';

    /**
     * Server 版本
     */
    protected string $version = '2.0.0';

    /**
     * Server 说明
     */
    protected string $instructions = <<<'MARKDOWN'
        This MCP server provides read-only access to Laravel application databases with project-aware context switching.
        
        ## Project-Aware Architecture
        
        This server supports multiple projects/databases without restarting. Each tool call requires a `project` parameter
        that dynamically resolves to the appropriate database connection.
        
        ### How to Use
        
        1. **Always read `.ai/project.json`** to get the current project identifier
        2. **Pass `project` parameter** in every tool call
        3. **Never hardcode** project identifiers
        
        Example:
        ```json
        {
          "project": "demo_project",
          "table": "users",
          "select": ["id", "name"],
          "limit": 10
        }
        ```
        
        ## Available Tools
        
        ### 1. db_schema_tables
        List all database tables with their comments.
        - Required: `project`
        
        ### 2. db_schema_table_detail
        Get detailed schema information for a specific table (columns, indexes, foreign keys).
        - Required: `project`, `table`
        
        ### 3. db_select_query
        Execute structured SELECT queries (read-only, no raw SQL).
        - Required: `project`, `table`
        - Optional: `select`, `where`, `order_by`, `limit`, `offset`
        
        ## Security Restrictions
        
        - ✅ Only SELECT operations allowed
        - ❌ No write operations (INSERT, UPDATE, DELETE, DROP, etc.)
        - ❌ No raw SQL strings accepted
        - ✅ Structured queries only with operator whitelist
        - ✅ Maximum query limit: 100 rows
        - ✅ Automatic table/column validation
        
        ## Multi-Project Support
        
        Projects are configured in `config/mcp_projects.php`. Each project maps to a complete database configuration.
        The server dynamically switches connections at runtime based on the `project` parameter.
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

