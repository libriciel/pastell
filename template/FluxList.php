<?php

/**
 * @var Gabarit $this
 * @var array $flux_list
 * @var array $possible_pack_list
 * @var int $id_e_mere
 * @var array $all_herited
 * @var int $id_e
 * @var bool $droit_edition
 * @var bool $droitConnecteurEdition
 */
?>
<div class="box">
    <h2>Type de dossier configurés</h2>
        <table style='width:100%;' aria-label="Modifier les propriétés de l'héritage global">
            <tr>
                <td class='align_right'>
                <?php if ($id_e_mere) : ?>
                    <form action='<?php $this->url("Flux/toogleHeritage"); ?>' method='post' >
                        <?php $this->displayCSRFInput(); ?>
                        <input type='hidden' name='id_e' value='<?php echo $id_e ?>' />
                        <input type='hidden' name='flux' value='<?php echo FluxEntiteHeritageSQL::ALL_FLUX?>' />
                        <?php if ($all_herited) :?>
                            <em>Tous les types de dossier sont hérités de la mère</em>
                            <?php if ($droit_edition) : ?>
                                <button type='submit' class='btn btn-primary'><i class='fa fa-minus-circle'></i>&nbsp;Supprimer l'héritage</button>
                            <?php endif;?>
                        <?php elseif ($droit_edition) :?>
                            <button type='submit' class='btn btn-primary'><i class='fa fa-plus-circle'></i>&nbsp;Faire tout hériter</button>
                        <?php endif;?>
                    </form>
                <?php endif;?>
                </td>
            </tr>
        </table>
    <?php if ($flux_list) : ?>
        <table class="table table-striped" aria-label="Liste des types de dossier">
            <tr>
                <th>Type de dossier</th>
                <th>Identifiant du type de dossier</th>
                <th>Nombre de connecteurs associés</th>
            </tr>

        <?php foreach ($flux_list as $flux_id => $flux_info) :?>
            <tr>
                <td rowspan='<?php echo $flux_id ?>'>
                        <a href="<?php $this->url("Flux/detail?id_e=$id_e&flux=$flux_id")?>">
                            <strong>
                                <?php hecho($flux_info['nom']);?>
                            </strong>
                        </a>
                    <br/>
                </td>
                <td>
                    <?php hecho($flux_id);?>
                </td>
                <td>
                    <e class="mr-5">
                        <?php hecho($flux_info['nb_connector'] ?? 0); ?>
                    </e>
                    <em class="text-muted"
                        <?php if ($flux_info['affiche_hover']) : ?>
                            data-toggle="tooltip" title="<?php hecho($flux_info['hover']) ?>"
                        <?php endif ?>
                    >
                        <?php hecho($flux_info['famille_associe_affiche']); ?>
                    </em>
                </td>
            </tr>
        <?php endforeach;?>
        </table>
    <?php endif;?>
    <?php if ($droitConnecteurEdition) : ?>
        <h2>Configurer un nouveau type de dossier</h2>
        <form action='Flux/detail' method='get'>
            <input type='hidden' name='id_e' value='<?php hecho($id_e); ?>'/>
            <table class='table table-striped' aria-labelledby="desc-module-type-table">
                <tr id="tr_type_document">
                    <th class='w200' scope="row">
                        <label for="module_type">Type de dossier</label>
                    </th>
                    <td>
                        <select name="flux" id="module_type" class="select2_type_dossier form-select col-md-3">
                            <?php foreach ($possible_pack_list as $type => $possible_flux_list) : ?>
                                <optgroup label="<?php hecho($type) ?>">
                                <?php foreach ($possible_flux_list as $flux_id => $flux_info) : ?>
                                    <option value="<?php hecho($flux_id); ?>">
                                        <?php hecho($flux_info['nom']); ?>
                                    </option>
                                <?php endforeach ?>
                                </optgroup>
                            <?php endforeach ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td></td>
                    <td>
                        <button type='submit' class='btn btn-primary' id="valider">
                            <i class="fa fa-plus"></i>&nbsp;Accéder à la configuration
                        </button>
                    </td>
                </tr>
            </table>
        </form>
    <?php endif; ?>
</div>

<script>
    $(document).ready(function() { $(".select2_type_dossier").select2(); });
</script>

