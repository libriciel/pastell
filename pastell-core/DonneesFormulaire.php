<?php

use Pastell\Configuration\ElementType;
use Pastell\Storage\StorageInterface;
use Pastell\Utilities\Identifier\UuidGenerator;

/**
 * Gestion des données de formulaire à partir d'un fichier YML de type clé:valeur
 */
class DonneesFormulaire
{
    /** @var string|null */
    public $id_d;
    private $filePath;
    private $documentType;

    private $lastError;

    private $onChangeAction;

    private $editable_content;
    private $has_editable_content;

    private $isModified;

    private $fichierCleValeur;

    private $fieldDataList;

    private DocumentIndexor $documentIndexor;

    /**
     * DonneesFormulaire constructor.
     * @param $filePath string emplacement vers un fichier YML
     *                  contenant les données du document sous la forme de ligne clé:valeur
     * @param DocumentType $documentType
     * @param YMLLoader|null $ymlLoader
     */
    public function __construct(
        $filePath,
        DocumentType $documentType,
        ?YMLLoader $ymlLoader = null,
        private readonly bool $useVaultForPasswordStorage = false,
        private readonly ?StorageInterface $passwordStorage = null,
        private readonly ?UuidGenerator $uuidGenerator = null,
    ) {
        $this->filePath = $filePath;
        $this->documentType = $documentType;
        $this->onChangeAction = [];
        $this->fichierCleValeur = new FichierCleValeur($filePath, $ymlLoader);
        $this->checkPasswordValueToGet();
        $this->setOnglet();
        /** @var Field $field */
        foreach ($this->getFormulaire()->getAllFields() as $field) {
            $this->setFieldData($field->getName());
        }
    }

    private function setFieldData($fieldName, $ongletNum = -1)
    {
        if (empty($this->fieldDataList[$fieldName])) {
            if ($ongletNum != -1) {
                $onglet_list = $this->getOngletList();
                $onglet_name = $onglet_list[$ongletNum];
            } else {
                $onglet_name = false;
            }

            $field = $this->getFormulaire()->getField($fieldName, $onglet_name);
            if (! $field) {
                $field = new Field($fieldName, []);
            }
            $this->fieldDataList[$fieldName] = new FieldData($field, $this->getDisplayValue($field));
        }
    }

    public function fieldExists($fieldName)
    {
        return $this->getFormulaire()->getField($fieldName);
    }

    private function setNewValueToFieldData($fieldName)
    {
        $field = $this->getFieldData($fieldName)->getField();
        $this->fieldDataList[$fieldName] = new FieldData($field, $this->getDisplayValue($field));
    }

    public function setDocumentIndexor(DocumentIndexor $documentIndexor)
    {
        $this->documentIndexor = $documentIndexor;
    }

    public function getNbOnglet()
    {
        if ($this->documentType->isAfficheOneTab()) {
            return 1;
        }
        return count($this->getOngletList());
    }

    public function getOngletList()
    {
        $onglet = $this->getFormulaire()->getOngletList();
        $page_condition = $this->documentType->getPageCondition();
        foreach ($onglet as $ongletNum => $ongletName) {
            if (isset($page_condition[$ongletName])) {
                foreach ($page_condition[$ongletName] as $field => $value) {
                    if ($this->fichierCleValeur->get($field) != $value) {
                        unset($onglet[$ongletNum]);
                        continue;
                    }
                }
            }
        }
        return array_values($onglet);
    }

    public function getFieldDataListAllOnglet($my_role)
    {
        $ongletList = $this->getOngletList();
        $fieldsList = [];
        foreach ($ongletList as $onglet_num => $onglet) {
            $fieldsList = array_merge($fieldsList, $this->getFieldDataList($my_role, $onglet_num));
        }
        return $fieldsList;
    }

    public function getFieldDataList($my_role, $ongletNum = 0)
    {
        $ongletList = $this->getOngletList();
        if (empty($ongletList[$ongletNum])) {
            return [];
        }
        $fieldNameList = $this->getFormulaire()->getFieldsForOnglet($ongletList[$ongletNum]);
        return $this->getFieldDataListByFieldName($my_role, $fieldNameList, $ongletNum);
    }

    private function getFieldDataListByFieldName($my_role, array $fieldNameList, $ongletNum = -1)
    {
        $result = [];
        foreach ($fieldNameList as $field) {
            if ($field->isShowForRole($my_role)) {
                $result[] = $this->getFieldData($field->getName(), $ongletNum);
            }
        }
        return $result;
    }

    /**
     * @param string $fieldName
     * @param int $ongletNum
     * @return FieldData
     */
    public function getFieldData($fieldName, $ongletNum = -1)
    {
        $fieldName  = Field::Canonicalize($fieldName);
        unset($this->fieldDataList[$fieldName]);
        $this->setFieldData($fieldName, $ongletNum);
        return $this->fieldDataList[$fieldName];
    }

    private function getDisplayValue(Field $field)
    {
        if (! $field->getProperties('depend')) {
            return $this->get($field->getName());
        }
        $cible = $this->get($field->getProperties('depend'));
        if (!$cible) {
            $cible = [];
        }
        $value = [];
        foreach ($cible as $j => $file) {
            $value[$file] = $this->get($field->getName() . "_$j");
        }
        return $value;
    }

    /*Fonction pour la construction de l'objet*/
    private function setOnglet()
    {
        $onglet_to_remove = [];
        $page_condition = $this->documentType->getPageCondition();
        foreach ($page_condition as $page => $condition) {
            foreach ($condition as $field => $value) {
                if ($this->get($field) != $value) {
                    $onglet_to_remove[] = $page;
                }
            }
        }
        $this->getFormulaire()->removeOnglet($onglet_to_remove);
        $this->getFormulaire()->setAfficheOneTab($this->documentType->isAfficheOneTab());
    }



    //C'est un truc qu'on peut récupérer de DocumentType et de l'action en cours
    public function setEditableContent(array $editable_content)
    {
        $this->has_editable_content = true;
        $this->editable_content = $editable_content;
    }

    /*Fonctions pour récupérer des objets ou des infos de plus bas niveau*/
    /**
     * Permet de récupérer l'objet Formulaire configuré vis-à-vis des données de ce DonneesFormulaire
     * @return Formulaire
     */
    public function getFormulaire()
    {
        return $this->documentType->getFormulaire();
    }

    /**
     * @param $item
     * @param mixed $default
     * @return string|array|bool
     */
    public function get($item, $default = false)
    {
        $item  = Field::Canonicalize($item);
        if (! $this->fichierCleValeur->exists($item)) {
            return $default;
        }
        $value = $this->fichierCleValeur->get($item);
        if (!is_array($value)) {
            $field = $this->getFormulaire()->getField($item);
            if ($field instanceof Field && $field->getType() === ElementType::CHECKBOX->value) {
                if (in_array(strtolower($value), ['true', 'on', '+', 'yes', 'y'])) {
                    return true;
                }

                if (in_array(strtolower($value), ['false', 'off', '-', 'no', 'n'])) {
                    return false;
                }
            }
        }
        return $value;
    }

    public function getSelectValue(string $item): string
    {
        $field = $this->getFormulaire()->getField($item);
        $key = $this->get($item);
        if (! $field || $field->getType() != Field::TYPE_SELECT || ! $key) {
            return "";
        }
        $select_array = $field->getSelect();
        if (! isset($select_array[$key])) {
            return "";
        }
        return $select_array[$key];
    }

    /**
     * @return string contenu du champs déclaré comme titre dans le formulaire
     */
    public function getTitre()
    {
        $titre_field = $this->getFormulaire()->getTitreField();
        return $this->get($titre_field);
    }

    /*Fonctions utilisées pour le rendu/l'affichage des données*/

    /**
     * Indique si le champs est modifiable
     *
     * @param string $field_name
     * @return boolean
     */
    public function isReadOnly($field_name)
    {
        $fieldData = $this->getFieldData($field_name);

        $field = $fieldData->getField();

        /* Ce n'est pas parce qu'on a un no-show que c'est read-only...*/
        /*if ($field->getProperties('no-show')){
            return true;
        }*/

        $read_only_content = $field->getProperties('read-only-content') ;
        if (!$read_only_content) {
            return false;
        }
        foreach ($read_only_content as $key => $value) {
            if ($this->get($key) != $value) {
                return false;
            }
        }
        return true;
    }

    public function isEditable($field_name)
    {
        if ($this->isReadOnly($field_name)) {
            return false;
        }
        if (! $this->has_editable_content) {
            return true;
        }
        return in_array($field_name, $this->editable_content);
    }


    /*fonction sur l'emplacement et le nom des fichiers annexes*/
    public function getFilePath($field_name, $num = 0): string
    {
        if (\preg_match('#[^\w-]#', $field_name)) {
            throw new UnrecoverableException("Champ `$field_name` incorrect");
        }

        return  $this->filePath . '_' . $field_name . "_$num";
    }

    /*Fonctions de sauvegarde*/
    public function injectData($fieldName, $fieldValue)
    {
        $this->fichierCleValeur->set($fieldName, $fieldValue);
        $this->getFieldData($fieldName)->setValue($fieldValue);
    }

    /**
     * Permet de sauver tous les champs contenu sur le même onglet. Les champs non renseigné sont mis à vide (sauf les champs de type password)
     * @param Recuperateur $recuperateur
     * @param FileUploader $fileUploader
     * @param int $pageNumber numéro de l'onglet
     */
    public function saveTab(Recuperateur $recuperateur, FileUploader $fileUploader, $pageNumber)
    {
        $this->isModified = false;
        $this->getFormulaire()->setTabNumber($pageNumber);

        /** @var Field $field */
        foreach ($this->getFormulaire()->getFields() as $field) {
            if (! $this->isEditable($field->getName())) {
                continue;
            }

            if ($field->getProperties('no-show') || $field->getProperties('read-only')) {
                continue;
            }
            $type = $field->getType();

            if ($type == 'externalData') {
                continue;
            }
            if ($type == 'file') {
                $this->saveFile($field, $fileUploader);
            } elseif ($field->getProperties('depend') && is_array($this->get($field->getProperties('depend')))) {
                foreach ($this->get($field->getProperties('depend')) as $i => $file) {
                    $key_name = $field->getName() . "_$i";
                    if (! $this->fichierCleValeur->exists($key_name)) {
                        $this->fichierCleValeur->set($key_name, false);
                    }
                    if ($this->fichierCleValeur->get($key_name) != $recuperateur->get($key_name)) {
                        $this->fichierCleValeur->set($key_name, $recuperateur->get($key_name));
                        $this->isModified = true;
                    }
                }
            } else {
                $name = $field->getName();
                $value =  $recuperateur->get($name);

                if ($type == 'password') {
                    $value =  $recuperateur->getNoTrim($name, "");
                }
                if (! $this->fichierCleValeur->exists($name)) {
                    $this->fichierCleValeur->set($name, "");
                }

                if (( $this->fichierCleValeur->get($name) != $value) &&  $field->getOnChange()) {
                    if (! in_array($field->getOnChange(), $this->onChangeAction)) {
                        $this->onChangeAction[] = $field->getOnChange();
                    }
                }

                if (( ($type != 'password' ) || $field->getProperties('may_be_null')  ) ||  $value) {
                    $this->setInfo($field, $value);
                }
            }
        }
        $this->saveDataFile(false);
    }

    private function setInfo(Field $field, $value)
    {
        if ($this->fichierCleValeur->get($field->getName()) === $value) {
            return;
        }
        if ($field->getType() == 'date') {
            $value = preg_replace("#^(\d{2})/(\d{2})/(\d{4})$#", '$3-$2-$1', $value);
        }

        $this->injectData($field->getName(), $value);
        $this->isModified = true;
    }

    public function saveAllFile(FileUploader $fileUploader)
    {
        $allField = $this->getFormulaire()->getAllFieldsDisplayedFirst();
        foreach ($fileUploader->getAll() as $filename => $name) {
            if (isset($allField[$filename])) {
                /** @var Field $field */
                $field = $allField[$filename];
                if (! $this->isEditable($field->getName())) {
                    continue;
                }
                $this->saveFile($field, $fileUploader);
            }
        }
        if ($this->isModified) {
            $this->saveDataFile(false);
        }
    }

    private function saveFile(Field $field, FileUploader $fileUploader)
    {
        $fname = $field->getName();

        if ($fileUploader->getName($fname)) {
            $num = $this->fichierCleValeur->count($fname);

            if ($field->isMultiple()) {
                for ($i = 0; $i < $fileUploader->getNbFile($fname); $i++) {
                    $this->fichierCleValeur->addValue($fname, $fileUploader->getName($fname, $i));
                }
                $this->setFieldData($fname);
                for ($i = 0; $i < $fileUploader->getNbFile($fname); $i++) {
                    $fileUploader->save($fname, $this->getFilePath($fname, $num + $i), $i);
                }
            } else {
                $this->fichierCleValeur->setMulti($fname, $fileUploader->getName($fname));
                $this->setFieldData($fname);
                $fileUploader->save($fname, $this->getFilePath($fname));
            }

            $this->isModified = true;
            if ($field->getOnChange()) {
                $this->onChangeAction[] = $field->getOnChange();
            }
        }
    }

    public function setData($field_name, $field_value)
    {
        $this->injectData($field_name, $field_value);
        $this->saveDataFile();
    }

    public function deleteField($fieldName)
    {
        $this->fichierCleValeur->deleteField($fieldName);
        $this->saveDataFile();
    }

    public function setTabData(array $field)
    {
        foreach ($field as $name => $value) {
            $this->injectData($name, $value);
        }
        $this->saveDataFile();
    }

    /**
     * @throws DonneesFormulaireException
     */
    public function setTabDataVerif(array $input_field)
    {
        $allField = $this->getFormulaire()->getFieldsList();
        foreach ($input_field as $field_name => $value) {
            if (isset($allField[$field_name])) {
                if ($allField[$field_name]->getType() === 'file') {
                    $this->lastError = "Le champ '$field_name' n'est pas autorisé sur un PATCH";
                    throw new DonneesFormulaireException($this->lastError);
                }
                if (! $this->isEditable($field_name)) {
                    continue;
                }
                $this->injectData($field_name, $value);
                $this->isModified = true;
                /** @var Field $field */
                $field = $allField[$field_name];
                if ($field->getOnChange()) {
                    $this->onChangeAction[] = $field->getOnChange();
                }
            }
        }
        /**
         * @var string $field_name
         * @var  Field $field
         */
        foreach ($allField as $field_name => $field) {
            if (
                $field->getProperties('depend') &&
                is_array($this->get($field->getProperties('depend')))
            ) {
                foreach ($this->get($field->getProperties('depend')) as $i => $file) {
                    if (isset($input_field[$field_name . "_$i"])) {
                        $this->injectData($field_name . "_$i", $input_field[$field_name . "_$i"]);
                        $this->isModified = true;
                    }
                }
            }
        }
        $this->saveDataFile(false);
    }

    /**
     * @param string $field_name
     * @param int $file_num
     * @throws DonneesFormulaireException
     */
    private function checkFileNumForNonMultipleField(string $field_name, int $file_num)
    {
        if (
            $this->getFormulaire()->getField($field_name) &&
            ! $this->getFormulaire()->getField($field_name)->isMultiple() &&
            $file_num !== 0
        ) {
            $this->lastError = "Le champ $field_name n'est pas multiple";
            throw new DonneesFormulaireException($this->lastError);
        }
    }

    /**
     * @param $field_name
     * @param $file_name
     * @param $raw_data
     * @param int $file_num
     * @throws Exception
     */
    public function addFileFromData($field_name, $file_name, $raw_data, $file_num = 0)
    {
        $this->checkFileNumForNonMultipleField($field_name, $file_num);

        $this->fichierCleValeur->setMulti($field_name, $file_name, $file_num);
        $file_path = $this->getFilePath($field_name, $file_num);
        $result = file_put_contents($file_path, $raw_data);
        if ($result === false) {
            throw new Exception("Impossible d'écrire dans le fichier $file_path");
        }
        $this->setNewValueToFieldData($field_name);
        $this->saveDataFile();

        $allField = $this->getFormulaire()->getFieldsList();
        if (isset($allField[$field_name])) {
            $field = $allField[$field_name];
            if ($field->getOnChange()) {
                $this->onChangeAction[] = $field->getOnChange();
            }
        }
    }

    /**
     * @param $field_name
     * @param $file_name
     * @param $file_source_path
     * @param int $file_num
     * @throws DonneesFormulaireException
     */
    public function addFileFromCopy($field_name, $file_name, $file_source_path, $file_num = 0)
    {
        $this->checkFileNumForNonMultipleField($field_name, $file_num);
        $this->fichierCleValeur->setMulti($field_name, $file_name, $file_num);
        copy($file_source_path, $this->getFilePath($field_name, $file_num));
        $this->setNewValueToFieldData($field_name);
        $this->saveDataFile();
        $this->isModified = true;

        $allField = $this->getFormulaire()->getFieldsList();
        if (isset($allField[$field_name])) {
            $field = $allField[$field_name];
            if ($field->getOnChange()) {
                $this->onChangeAction[] = $field->getOnChange();
            }
        }
    }

    /**
     * @param $field_name
     * @param int $file_num
     * @throws DonneesFormulaireException
     */
    public function removeFile($field_name, $file_num = 0)
    {
        $this->checkFileNumForNonMultipleField($field_name, $file_num);
        if (! file_exists($this->getFilePath($field_name, $file_num))) {
            return;
        }
        unlink($this->getFilePath($field_name, $file_num));
        for ($i = $file_num + 1; $i < $this->fichierCleValeur->count($field_name); $i++) {
            rename($this->getFilePath($field_name, $i), $this->getFilePath($field_name, $i - 1));
        }
        $this->fichierCleValeur->delete($field_name, $file_num);

        $field = $this->getFieldData($field_name)->getField();
        if ($field->getOnChange()) {
            $this->onChangeAction[] = $field->getOnChange();
        }
        $this->isModified = true;
        $this->saveDataFile(false);
    }

    private function saveDataFile($setModifiedToFalse = true)
    {
        $this->checkPasswordFieldsToSaveOrDelete(true);
        $this->fichierCleValeur->save();
        if ($setModifiedToFalse) {
            $this->isModified = false;
        }
        $this->updateAllIndexedField();
        $this->setOnglet();
    }

    private function updateAllIndexedField()
    {
        if (! isset($this->documentIndexor)) {
            return;
        }
        if (empty($this->fieldDataList)) {
            return;
        }

        $all_index = $this->documentIndexor->getAllIndex();
        /**
         * @var string $fieldName
         * @var FieldData $fieldData
         */
        foreach ($this->fieldDataList as $fieldName => $fieldData) {
            if (! isset($all_index[$fieldName]) || $all_index[$fieldName] != $fieldData->getValueForIndex()) {
                $this->updateIndexedField($fieldData);
            }
        }
    }

    private function updateIndexedField(FieldData $fieldData)
    {
        if (! $fieldData->getField()->isIndexed()) {
            return;
        }
        $value = $fieldData->getValueForIndex();
        $this->documentIndexor->index($fieldData->getField()->getName(), $value);
    }

    /*Fonctions permettant de savoir si il y a eu des choses modifiés après la sauvegarde*/
    public function isModified()
    {
        return $this->isModified;
    }

    public function getOnChangeAction()
    {
        return $this->onChangeAction;
    }

    /*Fonction de récupération de valeur*/
    public function getFileContent($field_name, $num = 0)
    {
        $file_path = $this->getFilePath($field_name, $num);
        if (! is_readable($file_path)) {
            $this->lastError = "Le fichier $file_path ne peut pas être lu";
            return false;
        }
        return file_get_contents($file_path);
    }

    //http://stackoverflow.com/questions/6595183/docx-file-type-in-php-finfo-file-is-application-zip
    private function getOpenXMLMimeType($file_name)
    {
        $ext = pathinfo($file_name, PATHINFO_EXTENSION);
        $openXMLExtension = [
            'xlsx' => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            'xltx' => "application/vnd.openxmlformats-officedocument.spreadsheetml.template",
            'potx' =>  "application/vnd.openxmlformats-officedocument.presentationml.template",
            'ppsx' =>  "application/vnd.openxmlformats-officedocument.presentationml.slideshow",
            'pptx'   =>  "application/vnd.openxmlformats-officedocument.presentationml.presentation",
            'sldx'   =>  "application/vnd.openxmlformats-officedocument.presentationml.slide",
            'docx'   =>  "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            'dotx'   =>  "application/vnd.openxmlformats-officedocument.wordprocessingml.template",
            'xlam'   =>  "application/vnd.ms-excel.addin.macroEnabled.12",
            'xlsb'   =>  "application/vnd.ms-excel.sheet.binary.macroEnabled.12",
            'txt' => "text/plain"
        ];
        if (isset($openXMLExtension[$ext])) {
            return $openXMLExtension[$ext];
        }
        return false;
    }

    /* TODO refactor avec FileContentType */
    public function getContentType($field_name, $num = 0)
    {
        $file_path = $this->getFilePath($field_name, $num);
        if (! file_exists($file_path)) {
            return false;
        }

        $fileInfo = new finfo();
        $result = $fileInfo->file($file_path, FILEINFO_MIME_TYPE);

        if ($result == 'application/zip') {
            $file_name = $this->getFileName($field_name, $num);
            $result = $this->getOpenXMLMimeType($file_name) ?: 'application/zip';
        }

        /**
         * php 7.2, file_info renvoi "text/xml" à la place de "application/xml
         * @see https://bugs.php.net/bug.php?id=75380
         */
        if ($result == 'text/xml') {
            $result = 'application/xml';
        }

        if ($result == 'application/x-empty') {
            $result = "text/plain";
        }

        if ($result == 'application/octet-stream') {
            $file_name = $this->getFileName($field_name, $num);
            $result = $this->getOpenXMLMimeType($file_name) ?: 'application/octet-stream';
        }

        return $result;
    }

    public function getFileNumber($field)
    {
        if (! $this->get($field)) {
            return 0;
        }
        return count($this->get($field));
    }

    public function getFileName($field_name, $num = 0)
    {
        $all_file_name = $this->get($field_name);
        if (! $all_file_name) {
            return "";
        }
        return basename($all_file_name[$num]);
    }

    public function getFileNameWithoutExtension($field_name, $num = 0)
    {
        $file_name = $this->getFileName($field_name, $num);
        return pathinfo($file_name, PATHINFO_FILENAME);
    }

    public function getWithDefault($item)
    {
        $default = $this->getFormulaire()->getField($item)->getDefault();
        $result = $this->get($item, $default);
        return $result ?: $default;
    }

    public function geth($item, $default = false)
    {
        return nl2br(htmlentities($this->get($item, $default), ENT_QUOTES, "UTF-8"));
    }

    public function isValidable()
    {
        $totalFileSize = 0;
        $fileSizesByField = [];

        /** @var FieldData $fieldData */
        foreach ($this->getFieldDataListAllOnglet(false) as $fieldData) {
            if (! $fieldData->isValide()) {
                $this->lastError = $fieldData->getLastError();
                return false;
            }
            /** @var Field $field */
            $field = $fieldData->getField();
            if ($field->getProperties('is_equal')) {
                if ($this->get($field->getProperties('is_equal')) != $this->get($field->getName())) {
                    $this->lastError = $field->getProperties('is_equal_error');
                    return false;
                }
            }
            if ($field->hasContentType()) {
                $file_list = $this->get($field->getName());
                if (! $file_list || ! $field->isFile()) {
                    $file_list = [];
                }
                foreach ($file_list as $file_num => $file_name) {
                    $ctype = $this->getContentType($field->getName(), $file_num);
                    if ($ctype && !in_array($ctype, $field->getContentType(), true)) {
                        $this->lastError = "Le type $ctype du fichier « $file_name » du champ « {$field->getLibelle()} » n'est pas conforme à : {$field->getProperties('content-type')} ";
                        return false;
                    }
                }
            }
            if ($field->getType() === 'file' && $this->get($field->getName())) {
                try {
                    $fileSizesByField[$field->getName()] = $this->validateAndReturnFieldSize($field);
                } catch (DonneesFormulaireException $e) {
                    $this->lastError = $e->getMessage();
                    return false;
                }
            }
        }
        $threshold = $this->documentType->getThresholdSize();

        if ($threshold) {
            if ($this->documentType->getThresholdFields()) {
                foreach ($this->documentType->getThresholdFields() as $fieldListed) {
                    $totalFileSize += $fileSizesByField[$fieldListed] ?? 0;
                }
            } else {
                $totalFileSize = array_sum($fileSizesByField);
            }

            if ($totalFileSize > $threshold) {
                $thresholdSizeInMB = number_format($threshold / (1000 * 1000), 2);
                $totalFileSizeInMB = number_format($totalFileSize / (1000 * 1000), 2);
                $this->lastError = "L'ensemble des fichiers dépasse le poids limite autorisé : $thresholdSizeInMB Mo ($threshold octets), $totalFileSizeInMB Mo ($totalFileSize octets) trouvés";
                return false;
            }
        }

        return true;
    }

    public function getLastError()
    {
        return $this->lastError;
    }

    public function delete(): void
    {
        $file_to_delete = glob($this->filePath . '*');
        $this->checkPasswordFieldsToSaveOrDelete(false);
        foreach ($file_to_delete as $file) {
            unlink($file);
        }
    }

    public function getRawData()
    {
        return $this->fichierCleValeur->getInfo();
    }

    public function getRawDataWithoutPassword()
    {
        $result = $this->getRawData() ?? [];
        foreach ($result as $element_id => $value) {
            $field = $this->getFormulaire()->getField($element_id);
            if (empty($field)) {
                continue;
            }
            if ($field->getType() == 'password') {
                $result[$element_id] = "MOT DE PASSE NON RECUPERABLE";
            }
        }
        return $result;
    }


    public function getMetaData()
    {
        return file_get_contents($this->filePath);
    }

    public function getAllFile()
    {
        $result = [];
        /** @var Field $field */
        foreach ($this->getFormulaire()->getAllFields() as $field) {
            if ($field->getType() != 'file') {
                continue;
            }
            if (! $this->get($field->getName())) {
                continue;
            }
            $result[] = $field->getName();
        }
        return $result;
    }

    public function extensionByMimeType($file_path, $file_name)
    {
        $path_parts = pathinfo($file_name);

        $fileInfo = new finfo();
        $contentType = $fileInfo->file($file_path, FILEINFO_MIME_TYPE);

        $map = [
            'application/pdf'   => '.pdf',
            'application/zip'   => '.zip',
            'application/xml'   => '.xml',
            'image/gif'         => '.gif',
            'image/jpeg'        => '.jpg',
            'image/png'         => '.png',
            'text/css'          => '.css',
            'text/html'         => '.html',
            'text/javascript'   => '.js',
            'text/plain'        => '.txt',
            'text/xml'          => '.xml',
        ];
        $result = "";

        if (isset($map[$contentType])) {
            $result = $map[$contentType];
        }

        if ($result == ".zip") {
            if (in_array($path_parts['extension'], ['xltx','potx','ppsx','sldx','docx','dotx','xlam','xlsb'])) {
                return "." . $path_parts['extension'];
            }
        }
        if ($result == '.txt') {
            $file_content = file_get_contents($file_path);
            if (preg_match("#-----BEGIN PKCS7-----#", $file_content)) {
                return ".p7c";
            }
        }

        if (!$result) {
            if (! empty($path_parts['extension'])) {
                $result = "." . $path_parts['extension'];
            }
        }

        return $result;
    }

    private function renameFilename($file_path, $new_filename)
    {
        $path_parts = pathinfo($file_path);
        return $path_parts['dirname'] . DIRECTORY_SEPARATOR . $new_filename;
    }

    public function copyFile(
        string $field_name,
        string $folder_destination,
        int $num = 0,
        ?string $new_filename = null
    ): string|false {
        $file_name = $this->get($field_name);
        if (! $file_name) {
            return false;
        }
        $file_name = $file_name[$num];
        $file_path = $this->getFilePath($field_name, $num);
        if (! file_exists($file_path)) {
            return false;
        }

        $destination = "$folder_destination/$file_name";
        if ($new_filename !== null) {
            $extension = $this->extensionByMimeType($file_path, $file_name);
            $destination = $this->renameFilename($destination, $new_filename . $extension);
        }
        copy($file_path, $destination);
        return $destination;
    }

    /**
     * used by pastell-quarantaine
     */
    public function copyAllFiles($field_name, $folder_destination, $new_filename = null): array
    {
        $result = [];
        if (!$this->get($field_name)) {
            return $result;
        }
        foreach ($this->get($field_name) as $i => $file_name) {
            $destination = $new_filename !== null ? $new_filename . '-' . $i : null;
            $result[] = $this->copyFile($field_name, $folder_destination, $i, $destination);
        }
        return $result;
    }

    public function jsonExport()
    {
        $result['metadata'] = $this->getRawData() ?? [];
        foreach ($this->getAllFile() as $field) {
            foreach ($this->get($field) as $file_num => $file_name) {
                $result['file'][$field][$file_num] = base64_encode($this->getFileContent($field, $file_num));
            }
        }
        return json_encode($result);
    }

    /**
     * @param $data
     * @throws DonneesFormulaireException
     * @throws Exception
     */
    public function jsonImport($data)
    {
        $result = json_decode($data, true);
        if ($result === null) {
            throw new Exception("Impossible de déchiffrer le fichier : erreur " . json_last_error());
        }
        if (!isset($result['metadata'])) {
            if (isset($result['salt'], $result['message'])) {
                throw new DonneesFormulaireException('Le contenu du connecteur est protégé');
            }
            throw new Exception("Clé metadata absente du fichier");
        }

        foreach ($result['metadata'] as $field_name => $field_value) {
            if (! is_array($field_value)) {
                $this->setData($field_name, $field_value);
            } else {
                foreach ($field_value as $file_num => $file_name) {
                    $file_content = "";
                    if (! empty($result['file'][$field_name][$file_num])) {
                        $file_content = $result['file'][$field_name][$file_num];
                        $file_content = base64_decode($file_content, true);
                    }
                    $this->addFileFromData($field_name, $file_name, $file_content, $file_num);
                }
            }
        }
    }



    /**
     * @param string $field_name
     * @param int $fileNumber
     * @return false|int
     * @throws DonneesFormulaireException
     */
    public function getFileSize($field_name, $fileNumber = 0)
    {
        $filepath = $this->getFilePath($field_name, $fileNumber);
        if (!file_exists($filepath)) {
            $field = $this->getFieldData($field_name)->getField();
            throw new DonneesFormulaireException(
                "Le fichier $fileNumber du champ «{$field->getLibelle()}» ($filepath) n'existe pas."
            );
        }
        return filesize($filepath);
    }

    public function getFileDigest(string $field_name, int $fileNumber = 0, string $digest_algorithm = 'sha256'): string
    {
        $filepath = $this->getFilePath($field_name, $fileNumber);
        return hash_file(
            $digest_algorithm,
            $filepath
        );
    }

    /**
     * @param Field $field
     * @return int
     * @throws DonneesFormulaireException
     */
    private function validateAndReturnFieldSize(Field $field): int
    {
        $fieldSize = 0;
        for ($fileNumber = 0; $fileNumber < $this->getFileNumber($field->getName()); ++$fileNumber) {
            $filesize = $this->getFileSize($field->getName(), $fileNumber);
            $filename = $this->getFileName($field->getName(), $fileNumber);
            if ($field->getMaxFileSize() && $filesize > $field->getMaxFileSize()) {
                $limitSizeInMB = number_format($field->getMaxFileSize() / (1000 * 1000), 2);
                $fileSizeInMB = number_format($filesize / (1000 * 1000), 2);
                throw new DonneesFormulaireException(
                    sprintf(
                        'Le fichier «%s» (%s) dépasse le poids limite autorisé :%s Mo (%s octets), %s Mo (%s octets) trouvés',
                        $filename,
                        $field->getLibelle(),
                        $limitSizeInMB,
                        $field->getMaxFileSize(),
                        $fileSizeInMB,
                        $filesize
                    )
                );
            }
            $fieldSize += $filesize;
        }
        if ($field->isMultiple() && $field->getMaxMultipleFileSize() && $fieldSize > $field->getMaxMultipleFileSize()) {
            $limitSizeInMB = number_format($field->getMaxMultipleFileSize() / (1000 * 1000), 2);
            $fieldSizeInMB = number_format($fieldSize / (1000 * 1000), 2);
            throw new DonneesFormulaireException(
                sprintf(
                    "L'ensemble des fichiers du champ multiple «%s» dépasse le poids limite autorisé : %s Mo (%s octets), %s Mo (%s octets) trouvés",
                    $field->getLibelle(),
                    $limitSizeInMB,
                    $field->getMaxMultipleFileSize(),
                    $fieldSizeInMB,
                    $fieldSize
                )
            );
        }
        return $fieldSize;
    }

    private function checkPasswordFieldsToSaveOrDelete(bool $toSave): void
    {
        if (
            $this->useVaultForPasswordStorage &&
            str_contains($this->id_d, DonneesFormulaireFactory::ID_CONNECTEUR)
        ) {
            foreach ($this->getFormulaire()->getFields() as $field) {
                if ($field->getType() === 'password' && ($value = $this->get($field->getName())) !== '') {
                    $passwordId = $this->fichierCleValeur->getYmlInfo()[$field->getName()];
                    if ($toSave) {
                        if ($this->passwordStorage->read($passwordId) === '404 : Bad status received from Vault') {
                            $passwordId = $this->uuidGenerator->generate();
                        }
                        $response = $this->passwordStorage->write($passwordId, $value);
                        $this->fichierCleValeur->set($field->getName(), $passwordId);
                    } else {
                        $response = $this->passwordStorage->delete($passwordId);
                    }
                    if ($response === '404 : Bad status received from Vault') {
                        throw new Exception("Problème d'accès au Vault");
                    }
                }
            }
        }
    }

    private function checkPasswordValueToGet(): void
    {
        if (
            $this->useVaultForPasswordStorage &&
            str_contains($this->filePath, DonneesFormulaireFactory::ID_CONNECTEUR)
        ) {
            foreach ($this->getFormulaire()->getFields() as $field) {
                if ($field->getType() === 'password') {
                    $passwordId = $this->fichierCleValeur->getYmlInfo()[$field->getName()] ?? '';
                    $password = $this->passwordStorage->read($passwordId);
                    if ($password !== '404 : Bad status received from Vault') {
                        $this->fichierCleValeur->set($field->getName(), $password);
                    }
                }
            }
        }
    }
}
