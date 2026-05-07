<?php

/**
 * @var Gabarit $this
 * @var int $id_e
 * @var array $options
 * @var string $password
 */

use Pastell\Service\ImportExportConfig\ExportConfigService;

?>

<div class="box">
    <h2>Les éléments suivants vont être exportés</h2>
    <ul>
        <?php if (! empty($exportInfo[ExportConfigService::ENTITY_INFO])) :?>
            <li>
                Export des informations de l'entité :
                <?php hecho($exportInfo[ExportConfigService::ENTITY_INFO]['denomination']) ?>
            </li>
        <?php endif; ?>
        <?php if (! empty($exportInfo[ExportConfigService::ENTITY_CHILD])) :?>
            <li>
                Export de <?php echo count($exportInfo[ExportConfigService::ENTITY_CHILD]) ?> entité(s) fille(s) :
                <ul>
                <?php for ($i = 0; $i < min(10, count($exportInfo[ExportConfigService::ENTITY_CHILD])); $i++) : ?>
                    <li><?php echo $exportInfo[ExportConfigService::ENTITY_CHILD][$i]['denomination']?></li>
                <?php endfor; ?>
                    <?php if (count($exportInfo[ExportConfigService::ENTITY_CHILD]) > 10) : ?>
                    <li>...</li>
                    <?php endif; ?>
                </ul>
            </li>
        <?php endif; ?>
        <?php if (! empty($exportInfo[ExportConfigService::CONNECTOR_INFO])) :?>
            <li>
                Export de <?php echo count($exportInfo[ExportConfigService::CONNECTOR_INFO]) ?> connecteur(s) :
                <ul>
                    <?php for ($i = 0; $i < min(10, count($exportInfo[ExportConfigService::CONNECTOR_INFO])); $i++) : ?>
                        <li><?php echo $exportInfo[ExportConfigService::CONNECTOR_INFO][$i]['libelle']?></li>
                    <?php endfor; ?>
                    <?php if (count($exportInfo[ExportConfigService::CONNECTOR_INFO]) > 10) : ?>
                        <li>...</li>
                    <?php endif; ?>
                </ul>
            </li>
        <?php endif; ?>
    </ul>
</div>


    <?php if (
        empty($exportInfo[ExportConfigService::ENTITY_INFO])
        && empty($exportInfo[ExportConfigService::ENTITY_CHILD])
) :?>
        <div class="alert alert-warning">
                La configuration requiert d'inclure au minimum les informations sur l'entité ou les entités filles.
        </div>
    <?php endif; ?>


<div class="alert alert-info">
    Votre mot de passe pour ce fichier est <strong><?php hecho($password);?></strong><br>
    Assurez-vous de le sauvegarder, il ne sera plus affiché.<br>
    Le mot de passe généré permet de protéger le contenu du fichier.
    Il sera nécessaire pour importer à nouveau la configuration sur un autre Pastell.
</div>
<div class="box">

    <form action='Entite/doExportConfig' method='post'>
        <?php $this->displayCSRFInput() ?>
        <input type='hidden' name='id_e' value='<?php hecho($id_e)?>'>
        <?php foreach (ExportConfigService::getOptions() as $id => $label) : ?>
            <input type="hidden" name="<?php hecho($id) ?>" value="<?php hecho($options[$id])?>"/>
        <?php endforeach; ?>
        <a class='btn btn-outline-primary' href='Entite/exportConfig?id_e=<?php hecho($id_e); ?>'>
            <i class="fas fa-circle-xmark"></i>&nbsp;Annuler
        </a>
        <button type='submit' class='btn btn-primary'><i class="fas fa-download"></i>&nbsp;Récupérer le fichier</button>

    </form>
</div>
