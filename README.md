# Laravel MCP Server (Project-Aware) 🚀

[![Laravel](https://img.shields.io/badge/Laravel-11.x-FF2D20?style=flat&logo=laravel)](https://laravel.com)
[![MCP](https://img.shields.io/badge/MCP-2.0.0-blue?style=flat)](https://modelcontextprotocol.io)
[![License](https://img.shields.io/badge/license-MIT-green?style=flat)](LICENSE)

A **project-aware Model Context Protocol (MCP) server** built on Laravel that provides read-only database access with dynamic context switching. Perfect for AI assistants like Claude, Cursor, and GitHub Copilot.

## ✨ Features

- 🔄 **Dynamic Database Switching** - Switch between multiple databases without restarting
- 🔒 **Read-Only by Design** - Only SELECT operations allowed, no write access
- 🎯 **Project-Aware** - Single server instance serves multiple projects/databases
- 🛡️ **Security First** - Structured queries only, no raw SQL, operator whitelist
- 🤖 **AI-Native** - Built specifically for AI assistant integration
- ⚡ **Zero Downtime** - Add/switch projects without server restart

## 🏗️ Architecture

```
┌─────────────────┐
│   AI Assistant  │ (Claude, Cursor, Copilot)
└────────┬────────┘
         │ MCP Protocol
         ▼
┌─────────────────────────────────────┐
│   Laravel MCP Server (v2.0.0)       │
│   ┌─────────────────────────────┐   │
│   │  DbConnectionResolver       │   │
│   │  (Runtime Connection)       │   │
│   └─────────────────────────────┘   │
│            │                         │
│   ┌────────┴────────┐               │
│   │  Project Config │               │
│   │  mcp_projects   │               │
│   └────────┬────────┘               │
└────────────┼─────────────────────────┘
             │
    ┌────────┴────────┐
    ▼                 ▼
┌─────────┐      ┌─────────┐
│  DB #1  │      │  DB #2  │
│ (MySQL) │      │ (SQLite)│
└─────────┘      └─────────┘
```

## 🚀 Quick Start

### Installation

```bash
# Clone the repository
git clone <your-repo-url>
cd ohh-mcp

# Install dependencies
composer install

# Configure your first project
cp .env.example .env
php artisan key:generate
```

### Configuration

1. **Add project database configurations** in `config/mcp_projects.php`:

```php
return [
    'demo_project' => [
        'driver' => 'sqlite',
        'database' => database_path('database.sqlite'),
        'prefix' => '',
        'foreign_key_constraints' => true,
    ],
    
    'production_project' => [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'production_db',
        'username' => 'mcp_reader',
        'password' => 'readonly_password',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],
];
```

2. **Set the active project** in `.ai/project.json`:

```json
{
  "mcp_project": "demo_project"
}
```

3. **Start the MCP server**:

```bash
php artisan mcp:serve
```

## 📚 Available Tools

### 1. `db_schema_tables`
List all database tables with their comments.

```json
{
  "project": "demo_project"
}
```

### 2. `db_schema_table_detail`
Get detailed schema information for a specific table.

```json
{
  "project": "demo_project",
  "table": "users"
}
```

### 3. `db_select_query`
Execute structured SELECT queries.

```json
{
  "project": "demo_project",
  "table": "users",
  "select": ["id", "name", "email"],
  "where": [["status", "=", "active"]],
  "limit": 10
}
```

## 🔒 Security Features

- ✅ **Read-Only Operations** - Only SELECT queries allowed
- ✅ **No Raw SQL** - Structured queries only
- ✅ **Operator Whitelist** - Only safe operators (`=`, `>`, `<`, `LIKE`, etc.)
- ✅ **Table/Column Validation** - Automatic validation against schema
- ✅ **Query Limits** - Maximum 100 rows per query
- ✅ **Connection Isolation** - Each project uses separate connections

## 🎯 Use Cases

### Multi-Project Development
```bash
# Switch between projects without restart
# Just update .ai/project.json
```

### Multi-Tenant SaaS
```php
// Each tenant gets their own project config
'tenant_123' => [...],
'tenant_456' => [...],
```

### Environment Separation
```php
'dev_project' => [...],
'staging_project' => [...],
'production_project' => [...],
```

## 🤖 AI Assistant Integration

### For Claude Desktop

Add to your `claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "laravel-db": {
      "command": "php",
      "args": ["artisan", "mcp:serve"],
      "cwd": "/path/to/ohh-mcp"
    }
  }
}
```

### For Cursor

Add to your MCP settings:

```json
{
  "mcp": {
    "servers": {
      "laravel-db": {
        "command": "php artisan mcp:serve",
        "cwd": "/path/to/ohh-mcp"
      }
    }
  }
}
```

## 📖 Documentation

- [Upgrade Guide](UPGRADE_GUIDE.md) - Migration from v1.0.0 to v2.0.0
- [AI Context Configuration](.ai/README.md) - How AI assistants should use this server
- [Laravel MCP Documentation](https://laravel.com/docs/mcp) - Official Laravel MCP docs

## 🛠️ Development

### Running Tests

```bash
php artisan test
```

### Adding a New Tool

1. Create tool class in `app/Mcp/Tools/`
2. Extend `Laravel\Mcp\Server\Tool`
3. Implement `schema()` and `handle()` methods
4. Register in `app/Mcp/DatabaseServer.php`

## 🔄 Version History

### v2.0.0 (Current)
- ✨ Project-aware architecture
- ✨ Dynamic database switching
- ✨ Runtime connection resolution
- ✨ AI context configuration

### v1.0.0
- 🎉 Initial release
- ✅ Basic read-only database access
- ✅ Three core tools (schema + select)

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📄 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## 🙏 Acknowledgments

- Built with [Laravel](https://laravel.com)
- Powered by [Laravel MCP](https://github.com/laravel/mcp)
- Inspired by the [Model Context Protocol](https://modelcontextprotocol.io)

---

<p align="center">Made with ❤️ for AI-native development</p>
