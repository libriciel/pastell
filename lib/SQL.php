<?php

abstract class SQL
{
    private $sqlQuery;

    public function __construct(SQLQuery $sqlQuery)
    {
        $this->sqlQuery = $sqlQuery;
    }

    public function query($query, $param = false)
    {
        if (! is_array($param)) {
            $param = func_get_args();
            array_shift($param);
        }
        return $this->sqlQuery->query($query, $param);
    }

    public function queryOne($query, $param = false)
    {
        if (! is_array($param)) {
            $param = func_get_args();
            array_shift($param);
        }
        return $this->sqlQuery->queryOne($query, $param);
    }

    public function queryOneCol($query, $param = false)
    {
        if (! is_array($param)) {
            $param = func_get_args();
            array_shift($param);
        }
        return $this->sqlQuery->queryOneCol($query, $param);
    }

    public function lastInsertId($name = null)
    {
        return $this->sqlQuery->getPdo()->lastInsertId($name);
    }

    protected function queryStream(string $query, array $param = [], bool $buffered = false): Generator
    {
        $max_execution_time = ini_get('max_execution_time');
        $pdo = $this->sqlQuery->getPdo();
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $buffered);
        try {
            $statement = $pdo->prepare($query);
            $statement->execute($param);
            while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                ini_set('max_execution_time', $max_execution_time);
                yield $row;
            }
        } finally {
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        }
    }
}
