<?php

//WTF ???
class RechercheAvanceFormulaireHTML extends PastellControler
{
    private $documentTypeFactory;
    private $recuperateur;
    private $documentType;


    public function __construct(ObjectInstancier $objectInstancier)
    {
        parent::__construct($objectInstancier);
        $this->documentTypeFactory = $objectInstancier->getInstance(DocumentTypeFactory::class);
        $this->setRecuperateur(new Recuperateur($_GET));
    }

    public function setRecuperateur(Recuperateur $recuperateur)
    {
        $this->recuperateur = $recuperateur;
        $this->documentType = $this->documentTypeFactory->getFluxDocumentType($this->getParameter('type'));
    }

    private function getParameter($field_name)
    {
        return $this->recuperateur->get($field_name);
    }

    public function display()
    {
        $champs_recherche_avance = $this->documentType->getChampsRechercheAvancee();

        ?>
        <table class="table table-striped"><?php
        foreach ($champs_recherche_avance as $field_name) { ?>
            <tr>
                <th class="w300"><?php hecho($this->getLibelle($field_name)) ?></th>
                <td>
                    <?php $this->displayInput($field_name); ?>
                </td>
            </tr>
            <?php
        }
        ?></table><?php
    }

    private function displayInput($field_name)
    {
        $found = true;
        switch ($field_name) {
            case 'type':
                $this->displayTypeDocument();
                break;
            case 'id_e':
                $this->displayEntite();
                break;
            case 'lastetat':
                $this->displayLastState();
                break;
            case 'last_state_begin':
                $this->displayLastStateBegin();
                break;
            case 'etatTransit':
                $this->displayEtatTransit();
                break;
            case 'state_begin':
                $this->displayStateBegin();
                break;
            case 'notEtatTransit':
                $this->displayNotEtatTransit();
                break;
            case 'search':
                $this->displayInputText('search');
                break;
            case 'tri':
                $this->displayTri();
                break;
            default:
                $found = false;
                break;
        }
        if ($found) {
            return;
        }
        $field = $this->documentType->getFormulaire()->getField($field_name);

        switch ($field->getType()) {
            case 'date':
                $this->displayDate($field_name);
                break;
            case 'select':
                $this->displaySelect($field_name);
                break;
            case 'externalData':
                $this->displayExternalData($field_name);
                break;
            default:
                $this->displayInputText($field_name);
        }
    }


    private function displayExternalData($field_name)
    {
        $id_e = $this->getParameter('id_e');
        $type = $this->getParameter('type');
        $select = $this->getParameter($field_name);

        $id_d = 0;
        $field = $this->documentType->getFormulaire()->getField($field_name);

        $action_name = $field->getProperties('choice-action');

        $all_choice = $this->getActionExecutorFactory()
            ->getChoiceForSearch($id_e, $this->getId_u(), $type, $action_name, $field_name);
        ?>

        <select name='<?php hecho($field_name) ?>' class="form-select col-md-8">
            <option value=''></option>
            <?php foreach ($all_choice as $key => $value) : ?>
                <option <?php echo $key == $select ? "selected='selected'" : ""; ?>
                        value='<?php hecho($key) ?>'><?php hecho($value) ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    private function displayDate($field_name)
    {
        $date = $this->getParameter($field_name);
        $this->dateInput($field_name, $date);
    }

    private function displaySelect($field_name)
    {
        $select = $this->getParameter($field_name);
        $field = $this->documentType->getFormulaire()->getField($field_name);
        $possible_value = $field->getSelect();
        ?>
        <select name='<?php hecho($field_name) ?>' class="form-select col-md-8">
            <option value=''></option>
            <?php foreach ($possible_value as $value) : ?>
                <option value='<?php hecho($value) ?>' <?php echo $value == $select ? "selected='selected'" : ""; ?>>
                    <?php hecho($value) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    private function displayTri()
    {
        $tri = $this->getParameter('tri');
        $sens_tri = $this->getParameter('sens_tri');
        $type = $this->getParameter('type');
        $documentType = $this->getInstance(DocumentTypeFactory::class)->getFluxDocumentType($type);
        $indexedFieldsList = $documentType->getFormulaire()->getIndexedFields();
        ?>
        <select name='tri' class="form-select col-md-8 select2_appearance">
            <?php
            foreach (
                [
                    'date_dernier_etat' => "Date de dernière modification",
                    "titre" => 'Titre du dossier',
                    "entite" => "Nom de l'entité"
                ] as $key => $libelle
            ) :
                ?>
                <option value='<?php echo $key ?>' <?php echo $tri == $key ? 'selected="selected"' : '' ?>><?php echo $libelle ?></option>
            <?php endforeach; ?>
            <?php if ($type) : ?>
                <?php foreach ($indexedFieldsList as $indexField => $indexLabel) : ?>
                    <option value='<?php hecho($indexField) ?>' <?php echo $tri == $indexField ? 'selected="selected"' : '' ?>><?php hecho($indexLabel) ?></option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
        </td></tr>
        <tr>
        <th class="w300">Selon l'ordre</th>
        <td>
        <select name='sens_tri' class="form-select col-md-8 select2_appearance">
            <option value='DESC' <?php echo $sens_tri == 'DESC' ? 'selected="selected"' : '' ?>>Descendant (Z à A, 9 à
                0, plus récent au plus ancien)
            </option>
            <option value='ASC' <?php echo $sens_tri == 'ASC' ? 'selected="selected"' : '' ?>>Ascendant (A à Z, 0 à 9,
                plus ancien au plus récent)
            </option>
        </select>
        <?php
    }

    private function displayEtatTransit()
    {
        $listeEtat = $this->getDocumentStateList();
        $etatTransit = $this->getParameter('etatTransit');
        ?>
        <select name='etatTransit' class="form-select col-md-8">
            <option value=''>----</option>
            <?php foreach ($listeEtat as $typeDocument => $allEtat) : ?>
                <optgroup label="<?php hecho($typeDocument) ?>">
                    <?php foreach ($allEtat as $nameEtat => $libelle) : ?>
                        <option value='<?php echo $nameEtat ?>' <?php echo $etatTransit == $nameEtat ? "selected='selected'" : ""; ?>>
                            <?php echo $libelle ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>
        <?php
    }

    private function displayStateBegin()
    {
        $state_begin = $this->getParameter('state_begin');
        $state_end = $this->getParameter('state_end');
        ?>
        Début:    <?php $this->dateInput('state_begin', $state_begin); ?>
        Fin : <?php $this->dateInput('state_end', $state_end); ?>
        <?php
    }

    private function displayNotEtatTransit()
    {
        $listeEtat = $this->getDocumentStateList();
        $notEtatTransit = $this->getParameter('notEtatTransit');
        ?>
        <select name='notEtatTransit' class="form-select col-md-8">
            <option value=''>----</option>
            <?php foreach ($listeEtat as $typeDocument => $allEtat) : ?>
                <optgroup label="<?php hecho($typeDocument) ?>">
                    <?php foreach ($allEtat as $nameEtat => $libelle) : ?>
                        <option value='<?php echo $nameEtat ?>' <?php echo $notEtatTransit == $nameEtat ? "selected='selected'" : ""; ?>>
                            <?php echo $libelle ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>

        </select>
        <?php
    }

    private function displayLastStateBegin()
    {
        $last_state_begin = $this->getParameter('last_state_begin');
        $last_state_end = $this->getParameter('last_state_end');
        ?>

        Début:    <?php $this->dateInput('last_state_begin', $last_state_begin); ?>
        Fin : <?php $this->dateInput('last_state_end', $last_state_end); ?>
        <?php
    }

    private function displayLastState()
    {
        $listeEtat = $this->getDocumentStateList();
        $lastEtat = $this->getParameter('lastetat');
        ?>
        <select name='lastetat' class="form-select col-md-8">
            <option value=''>N'importe quel état</option>
            <?php foreach ($listeEtat as $typeDocument => $allEtat) : ?>
                <optgroup label="<?php hecho($typeDocument) ?>">
                    <?php foreach ($allEtat as $nameEtat => $libelle) : ?>
                        <option value='<?php echo $nameEtat ?>' <?php echo $lastEtat == $nameEtat ? "selected='selected'" : ""; ?>>
                            <?php echo $libelle ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>

        </select>
        <?php
    }

    private function displayInputText($field_name)
    {
        ?>
        <input class="form-control col-md-8" type='text' name='<?php hecho($field_name) ?>'
               value='<?php hecho($this->getParameter($field_name)); ?>'/>
        <?php
    }

    private function displayTypeDocument()
    {
        $this->getInstance(DocumentTypeHTML::class)->displaySelect($this->getParameter('type'), $this->getAllModule());
    }

    private function displayEntite()
    {
        $arbre = $this->getInstance(RoleUtilisateur::class)->getArbreFille($this->getId_u(), "entite:lecture");
        $id_e = $this->getParameter('id_e');

        ?>
        <select class="form-select col-md-8 select2_entite" name='id_e'>
            <?php foreach ($arbre as $entiteInfo) : ?>
                <option value='<?php echo $entiteInfo['id_e'] ?>' <?php echo $entiteInfo['id_e'] == $id_e ? "selected='selected'" : ""; ?>>
                    <?php for ($i = 0; $i < $entiteInfo['profondeur']; $i++) {
                        echo "&nbsp&nbsp;";
                    } ?>
                    |_<?php hecho($entiteInfo['denomination']); ?> </option>
            <?php endforeach; ?>
        </select>
        <?php
    }


    private function getLibelle($field_name)
    {
        $defaultLibelle = [
            'tri' => 'Trier le résultat par',
            'lastetat' => 'Dernier état',
            'last_state_begin' => 'Date de passage dans le dernier état',
            'etatTransit' => "Passé par l'état",
            'state_begin' => 'Date de passage dans cet état',
            'notEtatTransit' => "Non passé par l'état",
            'search' => 'Dont le titre contient',
            'type' => 'Type de dossier',
            'id_e' => 'Collectivité'
        ];
        if (isset($defaultLibelle[$field_name])) {
            return $defaultLibelle[$field_name];
        }

        $field = $this->documentType->getFormulaire()->getField($field_name);
        if ($field) {
            return $field->getLibelle();
        }
        return $field_name;
    }

    private function dateInput($name, $value = '')
    {
        ?>
        <div class="input-group custom-control-inline">
            <input type='text'
                   id='<?php echo $name ?>'
                   name='<?php echo $name ?>'
                   value='<?php hecho($value); ?>'
                   class='date form-control col-md-3 ls-box-input'
            />
            <div class="input-group-append ">
                <span class="input-group-text"><i class="fa fa-calendar"></i></span>
            </div>
        </div>
        <script type="text/javascript">
            jQuery.datepicker.setDefaults(jQuery.datepicker.regional['fr']);
            $(function () {
                $("#<?php echo $name?>").datepicker({dateFormat: 'dd/mm/yy'});

            });
        </script>
        <?php
    }

    public function getDocumentStateList(): array
    {
        $allDroit = $this->getInstance(RoleUtilisateur::class)->getAllDroit($this->getId_u());
        $documentStateList = $this->getInstance(DocumentTypeFactory::class)->getActionByRole($allDroit);
        foreach ($documentStateList as $stateKey => $state) {
            $documentStateList[$stateKey]['fatal-error'] = 'Erreur fatale';
        }
        return $documentStateList;
    }
}
