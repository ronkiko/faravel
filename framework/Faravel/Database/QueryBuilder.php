<?php // v0.4.1
/* framework/Faravel/Database/QueryBuilder.php
Purpose: Построитель SQL-запросов (select/first/count/value, CRUD, where, join, limit).
         Единая низкоуровневая точка для чтения/записи БД без ORM.
FIX: Добавлен laravel-подобный exists(): быстрый SELECT 1 ... LIMIT 1 с
     сохранением/восстановлением select/limit. PHPDoc обновлён.
*/
namespace Faravel\Database;

use PDO;
use PDOStatement;

class QueryBuilder
{
    protected PDO $pdo;
    protected string $table;

    /** @var array<int,string> */
    protected array $select = ['*'];

    /** @var array<int,array{0:string,1:string,2:string}> */
    protected array $wheres = [];

    /** @var array<string,mixed> */
    protected array $bindings = [];

    /**
     * @var array<int,array{type:string,table:string,first?:string,op?:string,second?:string,raw_on?:string}>
     *  Supported:
     *   - columns ON:  ['type'=>'INNER','table'=>'tags t','first'=>'t.id','op'=>'=','second'=>'ct.tag_id']
     *   - raw ON expr: ['type'=>'LEFT','table'=>'stats s','raw_on'=>'s.cid=ct.cid AND s.tid=ct.tid']
     */
    protected array $joins = [];

    /** @var array<int,array{0:string,1:string}> */
    protected array $orderBys = [];

    protected ?int $limit = null;
    protected ?int $offset = null;

    public function __construct(PDO $pdo, string $table)
    {
        $this->pdo   = $pdo;
        $this->table = $table;
    }

    /**
     * Set SELECT columns.
     *
     * @param array<int,string>|string $columns
     * @return self
     */
    public function select($columns): self
    {
        $this->select = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    /**
     * Add WHERE condition with bound parameter.
     *
     * @param string $column
     * @param string $operator
     * @param mixed  $value
     * @return self
     */
    public function where(string $column, string $operator, $value): self
    {
        $param = ':' . preg_replace('/\W+/', '_', $column) . '_' . count($this->bindings);
        $this->wheres[] = [$column, $operator, $param];
        $this->bindings[$param] = $value;
        return $this;
    }

    /** @return self */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $dir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orderBys[] = [$column, $dir];
        return $this;
    }

    /** @return self */
    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);
        return $this;
    }

    /** @return self */
    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);
        return $this;
    }

    /** INNER JOIN */
    public function join(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = [
            'type'   => 'INNER',
            'table'  => $table,
            'first'  => $first,
            'op'     => $operator,
            'second' => $second,
        ];
        return $this;
    }

    /** LEFT JOIN */
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = [
            'type'   => 'LEFT',
            'table'  => $table,
            'first'  => $first,
            'op'     => $operator,
            'second' => $second,
        ];
        return $this;
    }

    /** JOIN with raw ON expression */
    public function joinOn(string $table, string $onExpression): self
    {
        $this->joins[] = [
            'type'   => 'INNER',
            'table'  => $table,
            'raw_on' => $onExpression,
        ];
        return $this;
    }

    /** LEFT JOIN with raw ON expression */
    public function leftJoinOn(string $table, string $onExpression): self
    {
        $this->joins[] = [
            'type'   => 'LEFT',
            'table'  => $table,
            'raw_on' => $onExpression,
        ];
        return $this;
    }

    /**
     * Get all rows.
     *
     * @return array<int,object>
     */
    public function get(): array
    {
        [$sql, $bindings] = $this->compileSelect();
        $stmt = $this->execute($sql, $bindings);
        return $stmt->fetchAll(PDO::FETCH_OBJ) ?: [];
    }

    /**
     * First row or null.
     *
     * @return object|null
     */
    public function first(): ?object
    {
        $prevLimit = $this->limit;
        $this->limit = 1;
        [$sql, $bindings] = $this->compileSelect();
        $this->limit = $prevLimit;
        $stmt = $this->execute($sql, $bindings);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row !== false ? $row : null;
    }

    /**
     * Does any row exist for the current query?
     *
     * Lightweight and fast: SELECT 1 ... LIMIT 1 (with all WHERE/JOIN parts).
     * Restores previous select/limit so the builder can be reused.
     *
     * @return bool
     */
    public function exists(): bool
    {
        $prevSelect = $this->select;
        $prevLimit  = $this->limit;

        $this->select = ['1'];
        $this->limit  = 1;

        [$sql, $bindings] = $this->compileSelect();

        // restore builder state
        $this->select = $prevSelect;
        $this->limit  = $prevLimit;

        $stmt = $this->execute($sql, $bindings);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        return $row !== false;
    }

    /**
     * Get single column value from the first row.
     *
     * @param string $column
     * @return mixed
     */
    public function value(string $column)
    {
        $this->select([$column]);
        $row = $this->first();
        if (!$row) return null;
        if (is_object($row) && property_exists($row, $column)) return $row->$column;
        if (is_array($row) && array_key_exists($column, $row)) return $row[$column];
        $arr = (array)$row;
        return reset($arr);
    }

    /**
     * Count rows.
     *
     * @param string $column
     * @return int
     */
    public function count(string $column = '*'): int
    {
        [$sql, $bindings] = $this->compileSelect(true, $column);
        $stmt = $this->execute($sql, $bindings);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Insert row.
     *
     * @param array<string,mixed> $data
     * @return int affected rows
     */
    public function insert(array $data): int
    {
        $cols = array_keys($data);
        $params = [];
        $bindings = [];
        foreach ($data as $col => $val) {
            $param = ':ins_' . preg_replace('/\W+/', '_', $col) . '_' . count($bindings);
            $params[] = $param;
            $bindings[$param] = $val;
        }
        $sql = 'INSERT INTO ' . $this->table . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $params) . ')';
        $stmt = $this->execute($sql, $bindings);
        return $stmt->rowCount();
    }

    /**
     * Insert row and return last insert id (string|null).
     *
     * @param array<string,mixed> $data
     * @return string|null
     */
    public function insertGetId(array $data): ?string
    {
        $this->insert($data);
        $id = $this->pdo->lastInsertId();
        return $id !== '0' ? $id : null;
    }

    /**
     * Update rows.
     *
     * @param array<string,mixed> $data
     * @return int affected rows
     */
    public function update(array $data): int
    {
        $sets = [];
        $bindings = $this->bindings;
        foreach ($data as $col => $val) {
            $param = ':upd_' . preg_replace('/\W+/', '_', $col) . '_' . count($bindings);
            $sets[] = $col . ' = ' . $param;
            $bindings[$param] = $val;
        }
        $sql = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $sets);
        if ($this->wheres) {
            $conditions = array_map(fn($w) => $w[0] . ' ' . $w[1] . ' ' . $w[2], $this->wheres);
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $stmt = $this->execute($sql, $bindings);
        return $stmt->rowCount();
    }

    /** Delete rows. @return int affected rows */
    public function delete(): int
    {
        $sql = 'DELETE FROM ' . $this->table;
        if ($this->wheres) {
            $conditions = array_map(fn($w) => $w[0] . ' ' . $w[1] . ' ' . $w[2], $this->wheres);
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $stmt = $this->execute($sql, $this->bindings);
        return $stmt->rowCount();
    }

    /**
     * Compile SELECT SQL and bindings.
     *
     * @param bool   $isCount
     * @param string $countColumn
     * @return array{0:string,1:array<string,mixed>}
     */
    protected function compileSelect(bool $isCount = false, string $countColumn = '*'): array
    {
        $bindings = $this->bindings;

        $sql = 'SELECT ';
        if ($isCount) {
            $sql .= 'COUNT(' . $countColumn . ')';
        } else {
            $sql .= implode(', ', $this->select ?: ['*']);
        }
        $sql .= ' FROM ' . $this->table;

        if ($this->joins) {
            foreach ($this->joins as $j) {
                $type = $j['type'] === 'LEFT' ? 'LEFT JOIN' : 'INNER JOIN';
                $sql .= ' ' . $type . ' ' . $j['table'];
                if (isset($j['raw_on'])) {
                    $sql .= ' ON ' . $j['raw_on'];
                } else {
                    $sql .= ' ON ' . $j['first'] . ' ' . $j['op'] . ' ' . $j['second'];
                }
            }
        }

        if ($this->wheres) {
            $conditions = array_map(fn($w) => $w[0] . ' ' . $w[1] . ' ' . $w[2], $this->wheres);
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        if ($this->orderBys && !$isCount) {
            $orders = array_map(fn($o) => $o[0] . ' ' . $o[1], $this->orderBys);
            $sql .= ' ORDER BY ' . implode(', ', $orders);
        }

        if ($this->limit !== null && !$isCount) {
            $sql .= ' LIMIT ' . (int)$this->limit;
        }
        if ($this->offset !== null && !$isCount) {
            $sql .= ' OFFSET ' . (int)$this->offset;
        }

        return [$sql, $bindings];
    }

    /**
     * Prepare/execute statement with bound values.
     *
     * @param string                      $sql
     * @param array<string,mixed>         $bindings
     * @return PDOStatement
     */
    protected function execute(string $sql, array $bindings): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        foreach ($bindings as $param => $value) {
            $stmt->bindValue($param, $value);
        }
        $stmt->execute();
        return $stmt;
    }

    /**
     * MAX(column) с учётом текущих JOIN/WHERE.
     *
     * Сохраняет и затем восстанавливает select/order/limit/offset,
     * чтобы билдер можно было переиспользовать.
     *
     * @param string $column
     * @return int|float|string|null
     */
    public function max(string $column)
    {
        // лёгкая санитария имени поля (в духе остального билдера всё равно raw)
        $col = preg_replace('/[^A-Za-z0-9_\.\*]/', '', $column) ?: '*';

        $prevSelect = $this->select;
        $prevOrder  = $this->orderBys;
        $prevLimit  = $this->limit;
        $prevOffset = $this->offset;

        // агрегат не нуждается в ORDER/LIMIT/OFFSET
        $this->select   = ["MAX({$col})"];
        $this->orderBys = [];
        $this->limit    = null;
        $this->offset   = null;

        [$sql, $bindings] = $this->compileSelect(false);

        // восстановить состояние билдера
        $this->select   = $prevSelect;
        $this->orderBys = $prevOrder;
        $this->limit    = $prevLimit;
        $this->offset   = $prevOffset;

        $stmt = $this->execute($sql, $bindings);
        $val  = $stmt->fetchColumn();

        if ($val === false) {
            return null;
        }
        return is_numeric($val) ? $val + 0 : $val;
    }
}
