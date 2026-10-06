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

    /**
     * @return Generator<array>
     */
    protected function queryStream($query, array $param = []): Generator
    {
        $max_execution_time = ini_get('max_execution_time');
        $this->sqlQuery->useUnberfferedQuery();
        $this->sqlQuery->prepareAndExecute($query, $param);

        while ($this->sqlQuery->hasMoreResult()) {
            $row = $this->sqlQuery->fetch();
            ini_set('max_execution_time', $max_execution_time);
            yield $row;
        }
    }
}
