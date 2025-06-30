<?php

class DatabaseUpdate
{
    private $fileContent;
    private $databaseDefinition = [];
    /** @var DatabaseDiff */
    private $databaseDiff;

    public function __construct($fileContent, ?SQLQuery $sqlQuery = null)
    {
        $this->fileContent = json_decode($fileContent, true);
        if (!$this->fileContent) {
            $this->fileContent = [];
        }
        $this->databaseDiff = new DatabaseDiff();

        if ($sqlQuery) {
            $databaseDefinition = new DatabaseDefinition($sqlQuery);
            $this->databaseDefinition = $databaseDefinition->getDefinition();
        }
    }

    public function writeDefinition($binaryFilePath, $sqlCommandFilePath)
    {
        file_put_contents($binaryFilePath, $this->getDatabaseDefinition());
        file_put_contents($sqlCommandFilePath, implode("\n", $this->getAllSQLCommand()));
    }

    public function getAllSQLCommand()
    {
        return $this->databaseDiff->getDiff($this->databaseDefinition, []);
    }

    public function getDiff()
    {
        return $this->databaseDiff->getDiff($this->fileContent, $this->databaseDefinition);
    }

    /**
     * @throws JsonException
     */
    public function getDatabaseDefinition()
    {
        return json_encode($this->databaseDefinition, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }

    public function setDatabaseDefinition(array $databaseDefinition)
    {
        $this->databaseDefinition = $databaseDefinition;
    }
}
