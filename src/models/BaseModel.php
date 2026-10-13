<?php
namespace Wxwork\Models;

use Wxwork\Core\Database;

/**
 * 数据模型基类
 * 
 * 提供通用的 CRUD 方法
 */
abstract class BaseModel
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';

    protected static function db(): Database
    {
        return Database::getInstance();
    }

    protected static function table(): string
    {
        return static::$table;
    }

    public static function find(int|string $id): ?array
    {
        return static::db()->selectOne(
            'SELECT * FROM ' . static::table() . ' WHERE ' . static::$primaryKey . ' = ? LIMIT 1',
            [$id]
        );
    }

    public static function all(string $order = ''): array
    {
        $sql = 'SELECT * FROM ' . static::table();
        if ($order) $sql .= ' ORDER BY ' . $order;
        return static::db()->select($sql);
    }

    public static function where(string $column, mixed $value, string $order = ''): array
    {
        $sql = 'SELECT * FROM ' . static::table() . ' WHERE ' . $column . ' = ?';
        if ($order) $sql .= ' ORDER BY ' . $order;
        return static::db()->select($sql, [$value]);
    }

    public static function insert(array $data): int|string
    {
        return static::db()->insertRow(static::table(), $data);
    }

    public static function update(int|string $id, array $data): int
    {
        return static::db()->updateRow(
            static::table(),
            $data,
            static::$primaryKey . ' = ?',
            [$id]
        );
    }

    public static function delete(int|string $id): int
    {
        return static::db()->deleteRow(
            static::table(),
            static::$primaryKey . ' = ?',
            [$id]
        );
    }

    public static function count(string $where = '', array $params = []): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . static::table();
        if ($where) $sql .= ' WHERE ' . $where;
        return (int)(static::db()->selectValue($sql, $params) ?? 0);
    }

    public static function paginate(int $page = 1, int $size = 20, string $where = '', array $params = []): array
    {
        $offset = ($page - 1) * $size;
        $total = static::count($where, $params);
        $sql = 'SELECT * FROM ' . static::table();
        if ($where) $sql .= ' WHERE ' . $where;
        $sql .= ' LIMIT ' . $offset . ', ' . $size;
        $rows = static::db()->select($sql, $params);
        return ['total' => $total, 'page' => $page, 'size' => $size, 'rows' => $rows];
    }
}
