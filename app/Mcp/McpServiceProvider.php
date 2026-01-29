<?php

namespace App\Mcp;

use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;

class McpServiceProvider extends ServiceProvider
{
    /**
     * Register MCP services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap MCP services.
     */
    public function boot(): void
    {
        // 注册 MCP Server
        // 使用 local() 方法注册本地 stdio 服务器
        Mcp::local('database', DatabaseServer::class);
        
        // 或者使用 web() 方法注册 HTTP 服务器
        // Mcp::web('/mcp/database', DatabaseServer::class);
    }
}

