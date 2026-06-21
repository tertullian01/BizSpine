<?php

namespace App\Models;

use PDO;

/**
 * Simple Query Builder for optimized database queries
 */
class QueryBuilder
{
    private string $modelClass;
    private array $columns;
    private string $table;
    private array $wheres = [];
    private array $orderBy = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $joins = [];
    public function __construct(string $modelClass, $columns = ['*'])
    {
        $this->modelClass = $modelClass;
        $this->columns = is_array($columns) ? $columns : [$columns];
        $this->table = $modelClass::getTableName();
    }

    /**
     * Add a WHERE condition
     * @param string $column
     * @param string $operator
     * @param mixed $value
     * @return $this
     */
    public function where(string $column, string $operator, $value): self
    {
        $this->wheres[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'type' => 'AND'
        ];
        return $this;
    }

    /**
     * Add an OR WHERE condition
     * @param string $column
     * @param string $operator
     * @param mixed $value
     * @return $this
     */
    public function orWhere(string $column, string $operator, $value): self
    {
        $this->wheres[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'type' => 'OR'
        ];
        return $this;
    }

    /**
     * Add a WHERE IN condition
     * @param string $column
     * @param array $values
     * @return $this
     */
    public function whereIn(string $column, array $values): self
    {
        $this->wheres[] = [
            'column' => $column,
            'operator' => 'IN',
            'value' => $values,
            'type' => 'AND'
        ];
        return $this;
    }

    /**
     * Add a WHERE NULL condition
     * @param string $column
     * @return $this
     */
    public function whereNull(string $column): self
    {
        $this->wheres[] = [
            'column' => $column,
            'operator' => 'IS NULL',
            'value' => null,
            'type' => 'AND'
        ];
        return $this;
    }

    /**
     * Add a WHERE NOT NULL condition
     * @param string $column
     * @return $this
     */
    public function whereNotNull(string $column): self
    {
        $this->wheres[] = [
            'column' => $column,
            'operator' => 'IS NOT NULL',
            'value' => null,
            'type' => 'AND'
        ];
        return $this;
    }

    /**
     * Add ORDER BY clause
     * @param string $column
     * @param string $direction
     * @return $this
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy[] = $column . ' ' . strtoupper($direction);
        return $this;
    }

    /**
     * Add LIMIT clause
     * @param int $limit
     * @param int|null $offset
     * @return $this
     */
    public function limit(int $limit, ?int $offset = null): self
    {
        $this->limit = $limit;
        $this->offset = $offset;
        return $this;
    }

    /**
     * Add JOIN clause
     * @param string $table
     * @param string $first
     * @param string $operator
     * @param string $second
     * @param string $type
     * @return $this
     */
    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = [
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'type' => strtoupper($type)
        ];
        return $this;
    }

    /**
     * Execute the query and get all results
     * @return array
     */
    public function get(): array
    {
        $sql = $this->buildSelectSql();
        $params = $this->getWhereParams();
        $stmt = $this->modelClass::$db->prepare($sql);
        $stmt->execute($params);
        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = new $this->modelClass($row);
        }

        return $results;
    }

    /**
     * Execute the query and get the first result
     * @return mixed|null
     */
    public function first(): ?object
    {
        $originalLimit = $this->limit;
        $this->limit(1);
        $results = $this->get();
        $this->limit = $originalLimit;
// Reset limit

        return $results[0] ?? null;
    }

    /**
     * Execute a count query
     * @return int
     */
    public function count(): int
    {
        $sql = $this->buildCountSql();
        $params = $this->getWhereParams();
        $stmt = $this->modelClass::$db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Check if any records exist
     * @return bool
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Build the SELECT SQL query
     * @return string
     */
    private function buildSelectSql(): string
    {
        $columns = implode(', ', $this->columns);
        $sql = "SELECT {$columns} FROM {$this->table}";
// Add JOINs
        foreach ($this->joins as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }

        // Add WHERE conditions
        if (!empty($this->wheres)) {
            $sql .= " WHERE " . $this->buildWhereClause();
        }

        // Add ORDER BY
        if (!empty($this->orderBy)) {
            $sql .= " ORDER BY " . implode(', ', $this->orderBy);
        }

        // Add LIMIT
        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
            if ($this->offset !== null) {
                $sql .= " OFFSET {$this->offset}";
            }
        }

        return $sql;
    }

    /**
     * Build the COUNT SQL query
     * @return string
     */
    private function buildCountSql(): string
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
// Add JOINs
        foreach ($this->joins as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }

        // Add WHERE conditions
        if (!empty($this->wheres)) {
            $sql .= " WHERE " . $this->buildWhereClause();
        }

        return $sql;
    }

    /**
     * Build the WHERE clause
     * @return string
     */
    private function buildWhereClause(): string
    {
        $conditions = [];
        foreach ($this->wheres as $where) {
            $condition = '';
        // Add AND/OR connector for conditions after the first
            if (!empty($conditions)) {
                $condition .= $where['type'] . ' ';
            }

            $condition .= $where['column'] . ' ' . $where['operator'];
            if ($where['operator'] === 'IN') {
                $placeholders = str_repeat('?,', count($where['value']) - 1) . '?';
                $condition .= " ({$placeholders})";
            } elseif ($where['operator'] !== 'IS NULL' && $where['operator'] !== 'IS NOT NULL') {
                $condition .= ' ?';
            }

            $conditions[] = $condition;
        }

        return implode('', $conditions);
    }

    /**
     * Get the WHERE parameters
     * @return array
     */
    private function getWhereParams(): array
    {
        $params = [];
        foreach ($this->wheres as $where) {
            if ($where['operator'] === 'IN') {
                $params = array_merge($params, $where['value']);
            } elseif ($where['operator'] !== 'IS NULL' && $where['operator'] !== 'IS NOT NULL') {
                $params[] = $where['value'];
            }
        }

        return $params;
    }
}
