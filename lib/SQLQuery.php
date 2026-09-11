<?php

use Monolog\Handler\NullHandler;

class SQLQuery
{
    public const SLOW_QUERY_IN_MS = 2000;
    public const PREFERRED_TABLE_COLLATION = "utf8mb4_unicode_ci";

    private $dsn;
    private $user;
    private $password;

    private $pdo;

    /** @var Monolog\Logger */
    private $logger;

    public function __construct($bd_dsn, $bd_user = null, $bd_password = null)
    {
        $this->dsn = $bd_dsn;
        $this->user = $bd_user;
        $this->password = $bd_password;

        $logger = new Monolog\Logger("S2LOW");

        $logger->setHandlers([new NullHandler()]);
        $this->setLogger($logger);
    }

    public function setLogger(Monolog\Logger $logger)
    {
        $this->logger = $logger;
    }

    public function disconnect()
    {
        $this->pdo = null;
    }

    public function isConnected(): bool
    {
        return $this->pdo !== null;
    }

    public function getPdo()
    {
        if (! $this->pdo) {
            $this->pdo = new PDO($this->dsn, $this->user, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->query('SET SQL_MODE="NO_ENGINE_SUBSTITUTION";');
            $this->query("SET time_zone = ?", TIMEZONE);
        }
        return $this->pdo;
    }

    /**
     * @throws Exception
     */
    public function query($query, $param = false)
    {
        $start = microtime(true);
        if (! is_array($param)) {
            $param = func_get_args();
            array_shift($param);
        }

        $pdoStatement = $this->prepareAndRun($query, $param);
        $result = [];
        if ($pdoStatement->columnCount()) {
            $result = $pdoStatement->fetchAll(PDO::FETCH_ASSOC);
        }

        $duration = microtime(true) - $start;
        if ($duration > self::SLOW_QUERY_IN_MS) {
            $requete =  $pdoStatement->queryString . "|" . implode(",", $param);
            trigger_error("Requete lente ({$duration}ms): $requete", E_USER_WARNING);
        }

        return $result;
    }

    /**
     * @throws Exception
     */
    public function execute($query, $param = false): int
    {
        if (! is_array($param)) {
            $param = func_get_args();
            array_shift($param);
        }
        return $this->prepareAndRun($query, $param)->rowCount();
    }

    private function prepareAndRun(string $query, array $param): PDOStatement
    {
        try {
            $pdoStatement = $this->getPdo()->prepare($query);
        } catch (Exception $e) {
            throw new Exception($e->getMessage() . " - " . $query);
        }
        $this->logger->debug("SQL REQUEST : $query");
        try {
            $pdoStatement->execute($param);
        } catch (Exception $e) {
            $message = $e->getMessage() . " - " . $pdoStatement->queryString . "|" . implode(",", $param);
            throw new Exception($message, -1, $e);
        }
        return $pdoStatement;
    }

    public function queryOne($query, $param = false)
    {
        if (! is_array($param)) {
            $param = func_get_args();
            array_shift($param);
        }
        $result = $this->query($query, $param);
        if (! $result) {
            return false;
        }

        $result = $result[0];
        if (count($result) == 1) {
            return reset($result);
        }
        return $result;
    }

    public function queryOneCol($query, $param = false)
    {
        if (! is_array($param)) {
            $param = func_get_args();
            array_shift($param);
        }
        $result = $this->query($query, $param);
        if (! $result) {
            return [];
        }
        $r = [];
        foreach ($result as $line) {
            $line = array_values($line);
            $r[] = $line[0];
        }
        return $r;
    }

    /** @var  PDOStatement */
    private $lastPdoStatement;
    private $nextResult;
    private $hasMoreResult;

    /**
     * Utile pour ne pas charger entièrement le resultSet
     * A utiliser avec prepareAndExecute/fetch/hasMoreResult
     */
    public function useUnberfferedQuery()
    {
        $this->getPdo()->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
    }

    public function prepareAndExecute($query, $param = false)
    {
        if (! is_array($param)) {
            $param = func_get_args();
            array_shift($param);
        }
        $this->lastPdoStatement = $this->getPdo()->prepare($query);
        $this->lastPdoStatement->execute($param);
        $this->hasMoreResult = true;
        $this->fetch();
    }

    public function hasMoreResult()
    {
        return $this->hasMoreResult;
    }

    public function fetch()
    {
        $result = $this->nextResult;
        $this->nextResult = $this->lastPdoStatement->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT);

        if (! $this->nextResult) {
            $this->hasMoreResult = false;
        }
        return $result;
    }

    public function waitStarting(Closure $log_function, $nb_retry_max = 60)
    {
        $connected = false;
        $nb_retry = 0;
        do {
            try {
                $nb_retry++;

                $this->query("SELECT 1;");
                $log_function("MySQL est maintenant démarré");
                $connected = true;
            } catch (Exception $e) {
                $log_function("[essai $nb_retry] MySQL n'a pas démarré ... on attend une seconde de plus");
                sleep(1);
            }
        } while (! $connected && $nb_retry < $nb_retry_max);

        if (! $connected) {
            $log_function("MySQL n'a pas démarré après $nb_retry essai...");
        }
        return $connected;
    }

    public function getClientEncoding(): string
    {
        $result = $this->queryOne("SHOW VARIABLES LIKE  'character_set_client'");
        return $result['Value'];
    }

    public function getTablesCollation()
    {
        $result = [];
        $sql = "SHOW TABLE status;";
        foreach ($this->query($sql) as $info) {
            if (! isset($result[$info['Collation']])) {
                $result[$info['Collation']] = [];
            }
            $result[$info['Collation']][] = $info['Name'];
        }
        return $result;
    }
}
