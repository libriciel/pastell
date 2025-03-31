<?php

/**
 * @var Gabarit $this
 * @var string $url
 * @var array $champs_affiches
 * @var string $url_tri
 * @var string $tri
 * @var string $sens_tri
 * @var array $listDocument
 * @var int $id_e
 */
?>
<div class="box">
    <h2 id="title-result" class="ls-off">Résultat(s) de la recherche
        <a href="Document/traitementLot?<?php hecho($url); ?>" class="btn btn-primary">
            <i class='fa fa-cogs'></i>&nbsp;Traitement par lot
        </a>

        <a class='btn btn-primary' href='Document/export?<?php hecho($url); ?>'>
            <i class='fa fa-download'></i>&nbsp;Exporter
        </a>
    </h2>

    <div class="table-responsive">
        <table class="table table-striped table-end">
            <thead>
                <tr>
                    <?php foreach ($champs_affiches as $champs => $champs_libelle) :?>
                        <th>
                            <?php if ($url_tri && $champs != 'dernier_etat') : ?>
                                <?php $headUrl = \sprintf(
                                    '%s&tri=%s&sens_tri=%s',
                                    $url_tri,
                                    $champs,
                                    ($champs == $tri) ? ($sens_tri === 'ASC' ? 'DESC' : 'ASC') : $sens_tri
                                );?>
                                <a href="<?php hecho($headUrl); ?>"><?php hecho($champs_libelle); ?></a>
                            <?php else : ?>
                                <?php hecho($champs_libelle); ?>
                            <?php endif;?>
                            <?php if ($champs == $tri) : ?>
                                <?php if ($sens_tri == 'ASC') :?>
                                    <i class="fa fa-sort-alpha-asc"></i>
                                <?php else : ?>
                                    <i class="fa fa-sort-alpha-desc"></i>
                                <?php endif;?>
                            <?php endif;?>
                        </th>
                    <?php endforeach;?>
                </tr>
            </thead>
            <tbody>
            <?php

            foreach ($listDocument as $i => $document) :
                $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($document['type']);
                $action = $documentType->getAction();
                $formulaire = $documentType->getFormulaire();
                ?>
                <tr>
                    <?php foreach ($champs_affiches as $champs => $champs_libelle) : ?>
                        <td>
                            <?php if ($champs == 'titre') :?>
                                <?php
                                $acknowlegmentAction = $action->getProperties(
                                    $document['last_action'],
                                    'accuse_de_reception_action'
                                );
                                if ($acknowlegmentAction) : ?>
                                    L'expéditeur a demandé un accusé de réception :
                                    <form action='Document/action' method='post'>
                                        <?php $this->displayCSRFInput() ?>
                                        <input type='hidden' name='id_d' value='<?php echo $document['id_d'] ?>' />
                                        <input type='hidden' name='id_e' value='<?php echo $id_e ?>' />
                                        <input type='hidden' name='page' value='0' />
                                        <input type='hidden'
                                               name='action'
                                               value='<?php echo $acknowlegmentAction; ?>'
                                        />
                                        <input type='submit'
                                               class='btn btn-primary'
                                               value='Envoyer un accusé de réception'
                                        />
                                    </form>
                                <?php else :?>
                                    <?php
                                    $detailDocumentUrl = \sprintf(
                                        'Document/detail?id_d=%s&id_e=%s',
                                        $document['id_d'],
                                        $document['id_e'],
                                    );
                                    ?>
                                    <a href='<?php $this->url($detailDocumentUrl); ?>'>
                                        <?php hecho($document['titre'] ?: $document['id_d'])?>
                                    </a>
                                <?php endif;?>
                            <?php elseif ($champs == 'type') :?>
                                <?php hecho($documentType->getName()); ?>
                            <?php elseif ($champs == 'entite') :?>
                                <?php if (isset($document['entite_base']) && ! $id_e) : ?>
                                    <a href='Entite/detail?id_e=<?php echo $document['id_e']?>'
                                    ><?php hecho($document['entite_base']); ?></a>
                                <?php endif;?>
                                <?php foreach ($document['entite'] as $entite) : ?>
                                    <a href='Entite/detail?id_e=<?php echo $entite['id_e']?>'>
                                        <?php hecho($entite['denomination']); ?>
                                    </a>
                                    <br/>
                                <?php endforeach;?>
                            <?php elseif ($champs == 'dernier_etat') :?>
                                <?php echo $action->getActionName($document['last_action_display']) ?>
                            <?php elseif ($champs == 'date_dernier_etat') :?>
                                <?php echo time_iso_to_fr($document['last_action_date']) ?>
                            <?php else :?>
                                <?php if ($formulaire->getField($champs)->getType() == 'file') : ?>
                                    <?php
                                    $downloadFileUrl = \sprintf(
                                        'Document/recuperationFichier?id_d=%s&id_e=%s&field=%s&num=0',
                                        $document['id_d'],
                                        $document['id_e'],
                                        $champs,
                                    );
                                    ?>
                                <a href='<?php echo $downloadFileUrl; ?>'>
                                    <?php hecho($this->getDocumentIndexSql()->get($document['id_d'], $champs));?>
                                </a>
                                <?php elseif ($formulaire->getField($champs)->getType() == 'date') :?>
                                    <?php echo date_iso_to_fr(
                                        $this->getDocumentIndexSql()->get($document['id_d'], $champs)
                                    ); ?>
                                <?php else :?>
                                    <?php hecho($this->getDocumentIndexSql()->get($document['id_d'], $champs));?>
                                <?php endif;?>
                            <?php endif;?>
                        </td>
                    <?php endforeach;?>
                </tr>
            <?php endforeach;?>
            </tbody>
        </table>
    </div>
</div>
