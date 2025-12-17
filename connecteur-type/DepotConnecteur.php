<?php

/*
 * Cette classe devra à terme remplacer la classe GEDConnecteur actuelle
 */

use Symfony\Component\Yaml\Yaml;

abstract class DepotConnecteur extends GEDConnecteur
{
    /* Les arguments directory_name sont relatifs à l'emplacement défini dans le connecteur  */
    abstract public function listDirectory();

    abstract public function makeDirectory(string $directory_name);

    abstract public function saveDocument(string $directory_name, string $filename, string $filepath);

    abstract public function directoryExists(string $directory_name);

    abstract public function fileExists(string $filename);

    public const DEPOT_TYPE_DEPOT = 'depot_type_depot';
    public const DEPOT_TYPE_DEPOT_DIRECTORY = 1;
    public const DEPOT_TYPE_DEPOT_ZIP = 2;
    public const DEPOT_TYPE_DEPOT_FICHIERS = 3;

    public const DEPOT_TITRE_REPERTOIRE = 'depot_titre_repertoire';
    public const DEPOT_TITRE_REPERTOIRE_TITRE_PASTELL = 1;
    public const DEPOT_TITRE_REPERTOIRE_METADATA = 2;
    public const DEPOT_TITRE_REPERTOIRE_ID_DOCUMENT = 3;

    public const DEPOT_TITRE_EXPRESSION = 'depot_titre_expression';

    public const DEPOT_METADONNEES = 'depot_metadonnees';
    public const DEPOT_METADONNEES_NO_FILE = 1;
    public const DEPOT_METADONNEES_XML_FILE = 2;
    public const DEPOT_METADONNEES_JSON_FILE = 3;
    public const DEPOT_METADONNEES_YAML_FILE = 4;

    public const DEPOT_METADONNES_FILENAME = 'depot_metadonnes_filename';

    public const DEPOT_METADONNEES_RESTRICTION = 'depot_metadonnees_restriction';

    public const DEPOT_PASTELL_FILE_FILENAME = 'depot_pastell_file_filename';
    public const DEPOT_PASTELL_FILE_FILENAME_ORIGINAL = 1;
    public const DEPOT_PASTELL_FILE_FILENAME_PASTELL = 2;
    public const DEPOT_PASTELL_FILE_FILENAME_REGEX = 3;

    public const DEPOT_FILE_RESTRICTION = 'depot_file_restriction';

    public const DEPOT_FILENAME_REPLACEMENT_REGEXP = 'depot_filename_replacement_regexp';

    public const DEPOT_FILENAME_PREG_MATCH = 'depot_filename_preg_match';

    public const DEPOT_CREATION_FICHIER_TERMINE = 'depot_creation_fichier_termine';

    public const DEPOT_NOM_FICHIER_TERMINE = 'depot_nom_fichier_termine';

    public const DEPOT_EXISTE_DEJA = 'depot_existe_deja';
    public const DEPOT_EXISTE_DEJA_ERROR = 1;
    public const DEPOT_EXISTE_DEJA_RENAME = 2;


    /** @var array */
    private $file_to_save;
    /** @var string */
    private $directory_name;

    /** @var TmpFolder $tmpFolder */
    private $tmpFolder;
    /** @var string */
    private $tmp_folder;

    /** @var TmpFile $tmpFile */
    private $tmpFile;
    /** @var array */
    private $tmp_files;


    public function testLecture()
    {
        return "Contenu du répertoire : " .
            json_encode(
                $this->listDirectory()
            );
    }

    /**
     * @return mixed
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function testEcriture()
    {
        $directory_path = 'test_rep_' . mt_rand(0, mt_getrandmax());
        $this->makeDirectory($directory_path);

        if (!$this->directoryExists($directory_path)) {
            throw new UnrecoverableException("Le répertoire créé n'a pas été trouvé !");
        }

        $filename = 'test_file_' . mt_rand(0, mt_getrandmax());

        $tmpFolder = new TmpFolder();
        $tmp_folder = $tmpFolder->create();
        file_put_contents($tmp_folder . "/" . $filename, "test de contenu");

        $result = $this->saveDocument($directory_path, $filename, $tmp_folder . "/" . $filename);
        $tmpFolder->delete($tmp_folder);
        return $result;
    }

    /**
     * @return mixed
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function testEcritureFichier()
    {
        $tmpFolder = new TmpFolder();
        $tmp_folder = $tmpFolder->create();
        $filename = 'test_file_' . mt_rand(0, mt_getrandmax());
        file_put_contents($tmp_folder . "/" . $filename, "test de fichier");
        $result = $this->saveDocument("", $filename, $tmp_folder . "/" . $filename);

        if (!$this->fileExists($filename)) {
            throw new UnrecoverableException("Le fichier créé n'a pas été trouvé !");
        }

        $tmpFolder->delete($tmp_folder);
        return $result;
    }

    /**
     * @param DonneesFormulaire $donneesFormulaire
     * @return array
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function send(DonneesFormulaire $donneesFormulaire): array
    {
        $this->file_to_save = [];
        $this->createTmpDir();
        try {
            $this->saveFiles($donneesFormulaire);
            $this->saveMetaData($donneesFormulaire);
            $this->finallySave($donneesFormulaire);
            $this->traitementFichierTermine();
        } finally {
            $this->deleteTmpDir();
            $this->deleteTmpFiles();
        }
        return $this->getGedDocumentsId();
    }

    /**
     * @throws Exception
     */
    private function createTmpDir()
    {
        $this->tmpFolder = new TmpFolder();
        $this->tmp_folder = $this->tmpFolder->create();
    }

    private function deleteTmpDir()
    {
        $this->tmpFolder->delete($this->tmp_folder);
    }

    /**
     * Copy an existing file into a temporary file with a different name
     *
     * @param string $source_file_path The full path of the original file
     * @param string $dest_file_name The name of the copied file
     * @return string The full path of the copied file
     * @throws Exception If the file already exist
     */
    private function copyTmpFile($source_file_path, $dest_file_name)
    {
        $this->tmpFile = new TmpFile();
        $tmp_file_path = $this->tmpFile->copyToTmpDir($source_file_path, $dest_file_name);
        $this->tmp_files[] = $tmp_file_path;
        return $tmp_file_path;
    }

    /**
     * Delete the files in tmp_files
     *
     * @return void
     */
    private function deleteTmpFiles()
    {
        foreach ($this->tmp_files as $tmp_file) {
            $this->tmpFile->delete($tmp_file);
        }
    }

    /**
     * @param DonneesFormulaire $donneesFormulaire
     * @throws Exception
     */
    private function saveFiles(DonneesFormulaire $donneesFormulaire)
    {
        $restrict_file_included = $this->getFileIncluded();
        $all_file = $donneesFormulaire->getAllFile();

        $expressionPerField = $this->getExpressionsPerField();

        foreach ($all_file as $field) {
            if ($restrict_file_included && !in_array($field, $restrict_file_included)) {
                continue;
            }
            $files = $donneesFormulaire->get($field);
            foreach ($files as $num_file => $file_name) {
                if ($this->saveFileWithPastellFileName()) {
                    $file_name = basename($donneesFormulaire->getFilePath($field, $num_file));
                } elseif ($this->saveFileWithRegexFileName()) {
                    $file_name = $this->getFileNameFromRegex(
                        $donneesFormulaire,
                        $expressionPerField,
                        $field,
                        $file_name,
                        $num_file
                    );
                }
                $file_name = $this->cleaningName($file_name);
                $file_path = $this->copyTmpFile($donneesFormulaire->getFilePath($field, $num_file), $file_name);
                $this->file_to_save[$file_name] = $file_path;
            }
        }
    }

    private function getMetadataIncluded()
    {
        if (!$this->connecteurConfig->get(self::DEPOT_METADONNEES_RESTRICTION)) {
            return [];
        }
        $result = explode(",", $this->connecteurConfig->get(self::DEPOT_METADONNEES_RESTRICTION));
        return array_map(function ($e) {
            return trim($e);
        }, $result);
    }

    private function getFileIncluded()
    {
        if (!$this->connecteurConfig->get(self::DEPOT_FILE_RESTRICTION)) {
            return [];
        }
        $result = explode(",", $this->connecteurConfig->get(self::DEPOT_FILE_RESTRICTION));
        return array_map(function ($e) {
            return trim($e);
        }, $result);
    }

    private function saveMetaData(DonneesFormulaire $donneesFormulaire)
    {
        $depot_metadonnees = (int)$this->connecteurConfig->get(self::DEPOT_METADONNEES);
        if (
            !in_array(
                $depot_metadonnees,
                [
                    self::DEPOT_METADONNEES_YAML_FILE,
                    self::DEPOT_METADONNEES_JSON_FILE,
                    self::DEPOT_METADONNEES_XML_FILE
                ]
            )
        ) {
            return;
        }
        $filename = false;
        $extension_filename = '';
        $data = false;
        $raw_data = $donneesFormulaire->getRawData();
        $meta_data_included = $this->getMetadataIncluded();
        if ($meta_data_included) {
            foreach ($raw_data as $key => $d) {
                if (!in_array($key, $meta_data_included)) {
                    unset($raw_data[$key]);
                }
            }
        }
        if ($depot_metadonnees == self::DEPOT_METADONNEES_YAML_FILE) {
            $data = Yaml::dump($raw_data);
            $extension_filename = '.txt';
        }
        if ($depot_metadonnees == self::DEPOT_METADONNEES_JSON_FILE) {
            $data = json_encode($raw_data);
            $extension_filename = '.json';
        }
        if ($depot_metadonnees == self::DEPOT_METADONNEES_XML_FILE) {
            $metaDataXML = new MetaDataXML(false);
            $data = $metaDataXML->getMetaDataAsXML(
                $donneesFormulaire,
                $this->saveFileWithPastellFileName(),
                $meta_data_included
            );
            $extension_filename = '.xml';
        }
        $filename = "metadata" . $extension_filename;
        if ($this->connecteurConfig->get(self::DEPOT_METADONNES_FILENAME)) {
            $filename =
                $this->cleaningName($this->getNameFromMetadata(
                    $donneesFormulaire,
                    $this->connecteurConfig->get(self::DEPOT_METADONNES_FILENAME)
                )) . $extension_filename;
        }
        $metadata_file_path = $this->tmp_folder . "/$filename";
        file_put_contents($metadata_file_path, $data);
        $this->file_to_save[$filename] = $metadata_file_path;
    }

    private function saveFileWithPastellFileName(): bool
    {
        return (int)$this->connecteurConfig->get(self::DEPOT_PASTELL_FILE_FILENAME) === self::DEPOT_PASTELL_FILE_FILENAME_PASTELL;
    }

    private function saveFileWithRegexFileName(): bool
    {
        return (int)$this->connecteurConfig->get(self::DEPOT_PASTELL_FILE_FILENAME) === self::DEPOT_PASTELL_FILE_FILENAME_REGEX;
    }

    /**
     * @param DonneesFormulaire $donneesFormulaire
     * @throws UnrecoverableException
     */
    private function finallySave(DonneesFormulaire $donneesFormulaire)
    {
        if ((int)$this->connecteurConfig->get(self::DEPOT_TYPE_DEPOT) === self::DEPOT_TYPE_DEPOT_ZIP) {
            $this->saveZip($donneesFormulaire);
        } elseif ((int)$this->connecteurConfig->get(self::DEPOT_TYPE_DEPOT) === self::DEPOT_TYPE_DEPOT_FICHIERS) {
            $this->saveFichiers();
        } else {
            $this->saveDirectory($donneesFormulaire);
        }
    }

    /**
     * @throws UnrecoverableException
     */
    private function saveFichiers()
    {
        foreach ($this->file_to_save as $filename => $filepath) {
            $filename = $this->checkFileExists($filename);
            $this->saveDocument("", $filename, $filepath);
        }
    }

    /**
     * @param DonneesFormulaire $donneesFormulaire
     * @throws UnrecoverableException
     */
    private function saveZip(DonneesFormulaire $donneesFormulaire)
    {
        $zip_filename = $this->getDirectoryName($donneesFormulaire) . ".zip";
        $zip_filename = $this->checkFileExists($zip_filename);

        $zip_filepath = $this->tmp_folder . "/" . $zip_filename;

        $zip = new ZipArchive();
        $zip->open($zip_filepath, ZipArchive::CREATE);

        foreach ($this->file_to_save as $filename => $filepath) {
            $zip->addFile($filepath, $filename);
        }
        $zip->close();

        $this->saveDocument("", $zip_filename, $zip_filepath);
    }

    /**
     * @param DonneesFormulaire $donneesFormulaire
     * @throws UnrecoverableException
     */
    private function saveDirectory(DonneesFormulaire $donneesFormulaire)
    {
        $directory_name = $this->getDirectoryName($donneesFormulaire);
        $directory_name = $this->checkDirectoryExists($directory_name);
        $this->directory_name = $directory_name;
        $this->makeDirectory($directory_name);
        foreach ($this->file_to_save as $filename => $filepath) {
            $this->saveDocument($directory_name, $filename, $filepath);
        }
    }

    /**
     * @param $directory_name
     * @return string
     * @throws UnrecoverableException
     */
    private function checkDirectoryExists($directory_name)
    {
        if (!$this->directoryExists($directory_name)) {
            return $directory_name;
        }
        if ((int)$this->connecteurConfig->get(self::DEPOT_EXISTE_DEJA) === self::DEPOT_EXISTE_DEJA_RENAME) {
            return $directory_name . "_" . date("Ymdhis") . "_" . mt_rand(0, mt_getrandmax());
        }
        throw new UnrecoverableException("Le répertoire $directory_name existe déjà !");
    }

    /**
     * @param $filename
     * @return string
     * @throws UnrecoverableException
     */
    private function checkFileExists($filename)
    {
        if (!$this->fileExists($filename)) {
            return $filename;
        }
        if ((int)$this->connecteurConfig->get(self::DEPOT_EXISTE_DEJA) === self::DEPOT_EXISTE_DEJA_RENAME) {
            $basename = pathinfo($filename, PATHINFO_FILENAME);
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            return $basename . "_" . date("Ymdhis") . "_" . mt_rand(0, mt_getrandmax()) . "." . $extension;
        }
        throw new UnrecoverableException("Le fichier $filename existe déjà !");
    }

    private function getDirectoryName(DonneesFormulaire $donneesFormulaire)
    {
        $directoryTitleChoice = (int)$this->connecteurConfig->get(self::DEPOT_TITRE_REPERTOIRE);
        if (
            $directoryTitleChoice === self::DEPOT_TITRE_REPERTOIRE_METADATA
            && $this->connecteurConfig->get(self::DEPOT_TITRE_EXPRESSION)
        ) {
            $name = $this->getNameFromMetadata(
                $donneesFormulaire,
                $this->connecteurConfig->get(self::DEPOT_TITRE_EXPRESSION)
            );
        } elseif ($directoryTitleChoice === self::DEPOT_TITRE_REPERTOIRE_ID_DOCUMENT) {
            $name = $donneesFormulaire->id_d;
        } else {
            $name = $donneesFormulaire->getTitre();
        }
        return $this->cleaningName($name);
    }

    private function getNameFromMetadata(DonneesFormulaire $donneesFormulaire, $expression)
    {
        return preg_replace_callback(
            "#%([^%]*)%#",
            function ($matches) use ($donneesFormulaire) {
                $field = $donneesFormulaire->getFormulaire()->getField($matches[1]);
                if ($field && $field->isFile()) {
                    return pathinfo($donneesFormulaire->getFileName($matches[1]), PATHINFO_FILENAME);
                }
                return $donneesFormulaire->get($matches[1]);
            },
            $expression
        );
    }

    private function cleaningName($name)
    {
        $regexp = $this->connecteurConfig->get(self::DEPOT_FILENAME_REPLACEMENT_REGEXP) ?: '#[\\\\/]#';
        return preg_replace($regexp, "-", $name);
    }

    private function traitementFichierTermine()
    {
        if (
            !$this->connecteurConfig->get(self::DEPOT_CREATION_FICHIER_TERMINE)
            || (int)$this->connecteurConfig->get(self::DEPOT_TYPE_DEPOT) === self::DEPOT_TYPE_DEPOT_ZIP
            || (int)$this->connecteurConfig->get(self::DEPOT_TYPE_DEPOT) === self::DEPOT_TYPE_DEPOT_FICHIERS
        ) {
            return;
        }
        $filename = $this->connecteurConfig->get(self::DEPOT_NOM_FICHIER_TERMINE) ?: "fichier_termine.txt";
        $filepath = $this->tmp_folder . "/" . $filename;
        file_put_contents($filepath, "Le transfert est terminé");
        $this->saveDocument($this->directory_name, $filename, $filepath);
    }

    private function getExpressionsPerField(): array
    {
        $expressionPerField = [];
        foreach (explode("\n", $this->connecteurConfig->get(self::DEPOT_FILENAME_PREG_MATCH)) as $line) {
            $values = explode(':', $line);
            if (count($values) < 2) {
                continue;
            }
            $expressionPerField[trim($values[0])] = trim($values[1]);
        }
        return $expressionPerField;
    }

    private function getFileNameFromRegex(
        DonneesFormulaire $donneesFormulaire,
        array $expressionPerField,
        string $field,
        string $file_name,
        int $num_file
    ): string {
        if (isset($expressionPerField[$field]) && $donneesFormulaire->getFormulaire()->getField($field)) {
            $extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $file_name = $this->getNameFromMetadata($donneesFormulaire, $expressionPerField[$field]);
            if ($donneesFormulaire->getFormulaire()->getField($field)->isMultiple()) {
                $file_name .= "_$num_file";
            }
            if ($extension) {
                $file_name .= ".$extension";
            }
        }
        return $file_name;
    }
}
