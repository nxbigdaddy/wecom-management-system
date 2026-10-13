<?php
/**
 * MySQL 数据库操作封装（PDO）
 * 
 * 单例模式，提供简单的查询方法。
 * 使用参数化查询防止 SQL 注入。
 */

namespace Wxwork\Core;

class Database
{
    /** @var self|null 单例 */
    private static ?self $instance = null;

    /** @var \PDO PDO 实例 */
    private \PDO $pdo;

    /** @var string 表前缀 */
    private string $prefix;

    /**
     * 私有构造函数
     */
    private function __construct(array $config)
    {
        $host    = $config['db_host'] ?? '127.0.0.1';
        $port    = $config['db_port'] ?? 3306;
        $dbName  = $config['db_name'] ?? 'wxwork_manager';
        $user    = $config['db_user'] ?? 'root';
        $pass    = $config['db_password'] ?? '';
        $charset = 'utf8mb4'; // 强制使用 utf8mb4

        $this->prefix = $config['db_prefix'] ?? 'wxwork_';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}";

        $options = [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE {$charset}_unicode_ci",
            // Docker 环境下持久连接可能导致问题，关闭
            \PDO::ATTR_PERSISTENT => false,
            \PDO::ATTR_TIMEOUT    => 10,
        ];

        $this->pdo = new \PDO($dsn, $user, $pass, $options);
        
        // 再次确保字符集正确
        $this->pdo->exec("SET character_set_client = {$charset}");
        $this->pdo->exec("SET character_set_connection = {$charset}");
        $this->pdo->exec("SET character_set_results = {$charset}");
    }

    /**
     * 获取单例（使用配置数组初始化）
     */
    public static function getInstance(array $config = []): self
    {
        if (self::$instance === null) {
            // 如果没有传入配置，尝试从全局配置获取
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /**
     * 使用 Config 对象初始化
     */
    public static function initWithConfig(Config $config): self
    {
        if (self::$instance === null) {
            self::$instance = new self([
                'db_host'     => $config->get('db_host'),
                'db_port'     => $config->get('db_port'),
                'db_name'     => $config->get('db_name'),
                'db_user'     => $config->get('db_user'),
                'db_password' => $config->get('db_password'),
                'db_charset'  => $config->get('db_charset', 'utf8mb4'),
                'db_prefix'   => $config->get('db_prefix', 'wxwork_'),
            ]);
        }
        return self::$instance;
    }

    /**
     * 获取原生 PDO 对象
     */
    public function getPdo(): \PDO
    {
        return $this->pdo;
    }

    /**
     * 获取表前缀
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * 获取带前缀的表名
     */
    public function table(string $name): string
    {
        // 如果已经带前缀，不重复添加
        if (str_starts_with($name, $this->prefix)) {
            return $name;
        }
        return $this->prefix . $name;
    }

    /**
     * 查询多行
     * @param string $sql    SQL（可用 :param 占位符）
     * @param array  $params 参数绑定
     * @return array
     */
    public function select(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * 查询单行
     * @return array|null
     */
    public function selectOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * 查询单个值
     * @return mixed
     */
    public function selectValue(string $sql, array $params = [], mixed $default = null): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return $value !== false ? $value : $default;
    }

    /**
     * 执行 INSERT / UPDATE / DELETE
     * @return int 影响行数
     */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * INSERT 并返回最后插入 ID
     * @return int|string
     */
    public function insert(string $sql, array $params = []): int|string
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $this->pdo->lastInsertId();
    }

    /**
     * 便捷 INSERT（传入表名和数据）
     * @param string $table 表名（不含前缀）
     * @param array  $data  数据键值对
     * @return int|string 插入ID
     */
    public function insertRow(string $table, array $data): int|string
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table($table),
            $columns,
            $placeholders
        );

        return $this->insert($sql, array_values($data));
    }

    /**
     * 便捷 UPDATE（传入表名、数据和条件）
     * @param string $table  表名（不含前缀）
     * @param array  $data   数据键值对
     * @param string $where  WHERE 条件（如 'id = ?'）
     * @param array  $params WHERE 参数
     * @return int 影响行数
     */
    public function updateRow(string $table, array $data, string $where, array $params = []): int
    {
        $sets = implode(', ', array_map(fn($col) => "{$col} = ?", array_keys($data)));

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $this->table($table),
            $sets,
            $where
        );

        return $this->execute($sql, array_merge(array_values($data), $params));
    }

    /**
     * 便捷 DELETE
     * @param string $table  表名（不含前缀）
     * @param string $where  WHERE 条件
     * @param array  $params 参数
     * @return int 影响行数
     */
    public function deleteRow(string $table, string $where, array $params = []): int
    {
        $sql = sprintf('DELETE FROM %s WHERE %s', $this->table($table), $where);
        return $this->execute($sql, $params);
    }

    /**
     * 开始事务
     */
    public function beginTransaction(): void
    {
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }
    }

    /**
     * 提交事务
     */
    public function commit(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
    }

    /**
     * 回滚事务
     */
    public function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    /**
     * 事务闭包（自动提交/回滚）
     * @param callable $callback function(Database $db): mixed
     * @return mixed 回调返回值
     * @throws \Throwable
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }
}
