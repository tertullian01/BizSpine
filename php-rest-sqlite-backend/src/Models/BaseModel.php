<?php
namespace App\Models;

use PDO;
use JsonSerializable;

abstract class BaseModel implements JsonSerializable
{
    protected static ?PDO $db = null;
    protected static string $tableName;

    public ?int $id = null;

    public function __construct(array $data = [])
    {
        if ($data) {
            foreach ($data as $key => $value) {
                if (property_exists($this, $key) || !property_exists(get_class($this), $key)) {
                    $this->{$key} = $value;
                }
            }
        }
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }

    /**
     * Injects the database connection into the base model.
     * This should be called once during application bootstrap.
     * @param PDO $pdo
     */
    public static function setDatabase(PDO $pdo): void
    {
        self::$db = $pdo;
    }

    /**
     * Get the table name for the model.
     * @return string
     */
    protected static function getTableName(): string
    {
        if (isset(static::$tableName)) {
            return static::$tableName;
        }
        $class = new \ReflectionClass(static::class);
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $class->getShortName())) . 's';
    }

    /**
     * Find a record by its primary key.
     * @param int $id
     * @return static|null
     */
    public static function find(int $id): ?static
    {
        $tableName = static::getTableName();
        $stmt = self::$db->prepare("SELECT * FROM {$tableName} WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            return new static($data);
        }

        return null;
    }

    /**
     * Find all records.
     * @return static[]
     */
    public static function findAll(): array
    {
        $tableName = static::getTableName();
        $stmt = self::$db->query("SELECT * FROM {$tableName}");
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = new static($row);
        }
        return $items;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::$db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_CLASS, static::class);
    }

    public static function fetchOne(string $sql, array $params = []): ?static
    {
        $stmt = self::$db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchObject(static::class);
        return $result === false ? null : $result;
    }

    private static function getTableColumns(): array
    {
        $tableName = static::getTableName();
        $stmt = self::$db->query("PRAGMA table_info({$tableName})");
        $columns = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $columns[] = $row['name'];
        }
        return $columns;
    }

    /**
     * Save the model to the database (create or update).
     */
    public function save(): void
    {
        $tableName = static::getTableName();
        $properties = get_object_vars($this);
        $columns = self::getTableColumns();
        
        $dataToSave = [];
        foreach ($properties as $key => $value) {
            if (in_array($key, $columns)) {
                $dataToSave[$key] = $value;
            }
        }

        unset($dataToSave['id']); // Let the database handle the ID on creation

        if ($this->id) {
            // Update
            $set = [];
            foreach (array_keys($dataToSave) as $key) {
                $set[] = "{$key} = :{$key}";
            }
            $sql = "UPDATE {$tableName} SET " . implode(', ', $set) . " WHERE id = :id";
            $dataToSave['id'] = $this->id;
        } else {
            // Create
            $columns = implode(', ', array_keys($dataToSave));
            $placeholders = ':' . implode(', :', array_keys($dataToSave));
            $sql = "INSERT INTO {$tableName} ({$columns}) VALUES ({$placeholders})";
        }

        $stmt = self::$db->prepare($sql);
        $stmt->execute($dataToSave);

        if (!$this->id) {
            $this->id = (int)self::$db->lastInsertId();
        }
    }

    /**
     * Delete the model from the database.
     */
    public function delete(): void
    {
        $tableName = static::getTableName();
        $stmt = self::$db->prepare("DELETE FROM {$tableName} WHERE id = :id");
        $stmt->execute([':id' => $this->id]);
    }
}