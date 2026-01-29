<?php

namespace App\Mcp\Tools;

use App\Mcp\Guards\QueryGuard;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class DbSelectQuery extends Tool
{
    /**
     * Tool 描述
     */
    protected string $description = <<<'MARKDOWN'
        Execute a structured SELECT query on a database table (read-only, no raw SQL allowed).
        
        Required: table (string)
        Optional: select (array of strings, default ["*"]), where (array of conditions), order_by (array), limit (integer, max 100, default 20), offset (integer, default 0)
        
        Example: {"table": "users", "select": ["id", "name"], "where": [["status", "=", "active"]], "limit": 10}
    MARKDOWN;

    /**
     * 输入 Schema
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'table' => $schema->string()
                ->description('The name of the table to query')
                ->required(),
            'select' => $schema->array()
                ->description('Array of column names to select. Example: ["id", "name", "email"] or ["*"] for all columns')
                ->items($schema->string())
                ->default(['*']),
            'limit' => $schema->integer()
                ->description('Maximum number of rows to return (max: 100, default: 20)')
                ->default(20),
            'offset' => $schema->integer()
                ->description('Number of rows to skip (default: 0)')
                ->default(0),
        ];
    }

    /**
     * 执行 Tool
     */
    public function handle(Request $request): Response
    {
        // 获取参数
        $input = [
            'table' => $request->string('table'),
            'select' => $request->array('select', ['*']),
            'where' => $request->array('where', []),
            'order_by' => $request->array('order_by', []),
            'limit' => $request->integer('limit', 20),
            'offset' => $request->integer('offset', 0),
        ];

        // 安全验证
        QueryGuard::validate($input);

        // 构建查询
        $query = DB::table($input['table']);

        // SELECT 字段
        $select = $input['select'];
        $query->select($select);

        // WHERE 条件
        if (!empty($input['where'])) {
            foreach ($input['where'] as $condition) {
                if (count($condition) === 2) {
                    // [column, value] - 默认使用 = 操作符
                    $query->where($condition[0], '=', $condition[1]);
                } elseif (count($condition) === 3) {
                    // [column, operator, value]
                    $query->where($condition[0], $condition[1], $condition[2]);
                }
            }
        }

        // ORDER BY
        if (!empty($input['order_by'])) {
            foreach ($input['order_by'] as $order) {
                if (count($order) === 2) {
                    $column = $order[0];
                    $direction = strtolower($order[1]) === 'desc' ? 'desc' : 'asc';
                    $query->orderBy($column, $direction);
                } elseif (count($order) === 1) {
                    // 默认升序
                    $query->orderBy($order[0], 'asc');
                }
            }
        }

        // LIMIT 和 OFFSET
        $limit = QueryGuard::getSafeLimit($input['limit']);
        $offset = $input['offset'];

        $query->limit($limit);
        if ($offset > 0) {
            $query->offset($offset);
        }

        // 执行查询
        $rows = $query->get()->toArray();

        // 获取总数（用于分页）
        $totalQuery = DB::table($input['table']);
        if (!empty($input['where'])) {
            foreach ($input['where'] as $condition) {
                if (count($condition) === 2) {
                    $totalQuery->where($condition[0], '=', $condition[1]);
                } elseif (count($condition) === 3) {
                    $totalQuery->where($condition[0], $condition[1], $condition[2]);
                }
            }
        }
        $total = $totalQuery->count();

        $result = [
            'rows' => $rows,
            'meta' => [
                'table' => $input['table'],
                'count' => count($rows),
                'limit' => $limit,
                'offset' => $offset,
                'total' => $total,
                'has_more' => ($offset + count($rows)) < $total,
            ],
        ];

        return Response::json($result);
    }
}

