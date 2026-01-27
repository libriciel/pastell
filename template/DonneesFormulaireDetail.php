<?php

/**
 * @var FieldData[] $fieldDataList
 * @var array $inject
 * @var string $recuperation_fichier_url
 * @var DonneesFormulaire $donneesFormulaire
 */

$id_ce = $inject['id_ce'];
$id_d = $inject['id_d'];
$action = $inject['action'];
$id_e = $inject['id_e'];

?>
<?php  if (! $donneesFormulaire->isValidable()) :  ?>
    <div class="alert alert-danger">
        <?php hecho($donneesFormulaire->getLastError()); ?>
    </div>
<?php endif; ?>
    
<table class='table table-striped break-table-content'>
<?php foreach ($fieldDataList as $num_field => $displayField) :
    if ($displayField->getField()->isEditOnly()) {
        continue;
    }

    ?>      <tr>
            <th class="w300">
                <?php hecho($displayField->getField()->getLibelle()); ?>
            </th>
            <td>
                <?php foreach ($displayField->getValue() as $num => $value) :?>
                        <?php if ($displayField->isURL()) :?>
                            <a href='<?php hecho($displayField->getURL($recuperation_fichier_url, $num, $id_e))?>' id="link_<?php echo $num_field?>">
                        <?php endif;?>
                            <?php if ($displayField->getField()->getType() == 'textarea') : ?>
                                <?php
                                    $data = get_hecho($value);
                                    $data = preg_replace('#(https?://[^\s]+)#', '<a href="\1">\1</a>', $data);
                                    echo nl2br($data);
                                ?>
                            <?php else :?>
                            <span><?php hecho($value);?></span>
                            <?php endif;?>
                            <br/>
                        <?php if ($displayField->isURL()) :?>
                            </a>
                        <?php endif;?>
                <?php endforeach;?>
                <?php if ($displayField->isDownloadZipAvailable()) : ?>
                    <br/>
                    <?php
                    $downloadZipUrl = isset($download_all_link) ?
                    $download_all_link . '&field=' . $displayField->getField()->getName() :
                    sprintf(
                        "/DonneesFormulaire/downloadAll?id_e=$id_e&id_d=$id_d&id_ce=$id_ce&field=%s",
                        $displayField->getField()->getName()
                    );
                    ?>
                    <a href="<?php hecho($downloadZipUrl); ?>" class="btn btn-primary">
                        <i class="fa fa-download"></i>&nbsp;Télécharger tous les fichiers :
                        <?php hecho($displayField->getField()->getLibelle()) ?>
                    </a>
                <?php endif;?>

                <?php if ($displayField->getField()->getVisionneuse()) :?>
                    <?php
                    $viewerUrl = sprintf(
                        '/DonneesFormulaire/visionneuse?id_e=%s&id_d=%s&id_ce=%s&field=%s',
                        $id_e,
                        $id_d,
                        $id_ce,
                        $displayField->getField()->getName()
                    );
                    ?>
                    <a
                            id='visionneuse_link_<?php echo $num_field?>'
                            class=' btn btn-primary'
                            href='<?php hecho($viewerUrl); ?>'
                    >
                        <i class="fa fa-eye"></i>
                        &nbsp;Voir
                    </a>
                    <div class='visionneuse_result'></div>
                    <script>
$(document).ready(function(){
    let visionneuse_link = $('#visionneuse_link_<?php echo $num_field ?>');
    $(".visionneuse_result").hide();

    visionneuse_link.click(function(){
        var link=$(this).attr('href');
        var visionneuse_result = $(this).next(".visionneuse_result");
        visionneuse_result.toggle();
            $.ajax({
                url: link,
                cache: false
            }).done(function( html ) {
                visionneuse_result.html( html );
            });
        return false;
    });
                    <?php if (! $displayField->getField()->displayLink()) : ?>
        $("#link_<?php echo $num_field ?>").hide();
                    <?php endif; ?>
});
                    </script>
                    
                <?php endif;?>
            </td>
        </tr>
<?php endforeach;?>
</table>
