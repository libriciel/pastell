<?php

abstract class SQL
{
    public function __construct(
        private readonly SQLQuery $sqlQuery,
    ) {
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

    public function execute($query, $param = false): int
    {
        if (! is_array($param)) {
            $param = func_get_args();
            array_shift($param);
        }
        return $this->sqlQuery->execute($query, $param);
    }

    public function lastInsertId($name = null)
    {
        return $this->sqlQuery->getPdo()->lastInsertId($name);
    }

    public function getNow(): string
    {
        return date(Date::DATE_ISO);
    }
}
