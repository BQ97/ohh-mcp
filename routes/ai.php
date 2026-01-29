<?php

use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP Server Routes
|--------------------------------------------------------------------------
|
| 这里注册 MCP Server 的 HTTP 路由
| 使用 Mcp::web() 方法可以通过 HTTP 访问 MCP Server
| 使用 Mcp::local() 方法可以通过 stdio 访问 MCP Server（已在 McpServiceProvider 中注册）
|
*/

// 注册数据库 MCP Server 的 HTTP 端点
// 访问地址: POST http://your-domain/mcp/database
Mcp::web('/mcp/database', \App\Mcp\DatabaseServer::class);
