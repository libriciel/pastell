<?php

/**
 * @var Gabarit $this
 * @var array $info
 * @var string $id_d
 * @var string $id_e
 * @var int $page
 * @var string $type
 * @var DocumentType $documentType
 */

?>
<?php if ($info) : ?>
    <a class='btn btn-link'
       href='<?php $this->url("Document/detail?id_d=$id_d&id_e=$id_e&page=$page"); ?>'
    ><i class="fas fa-arrow-left"></i>&nbsp;<?php hecho($info['titre'] ?: $info['id_d']); ?></a>
<?php else : ?>
    <a class='btn btn-link'
       href='Document/list?type=<?php echo $type ?>&id_e=<?php echo $id_e ?>'
    ><i class="fas fa-arrow-left"></i>&nbsp;Liste des dossiers <?php hecho($documentType->getName()); ?></a>
<?php endif; ?>

<?php $this->render('DonneesFormulaireEdition'); ?>
