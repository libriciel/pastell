<?php
/**
 * @var Gabarit $this
 * @var int $id_e
 * @var array $flux_connecteur_list
 * @var $subtitle
 * @var $all_herited
 * @var $id_e_mere
 * @var bool $connecteur_edition
 */
?>
<a class='btn btn-link' href='Flux/index?id_e=<?php echo $id_e?>'>
    <i class="fa fa-arrow-left"></i>&nbsp;Retour à la liste des types de dossier
</a>

<div class="box">
    <h2><?php hecho($subtitle); ?></h2>
    <table class="table table-striped" aria-label="Liste des connecteurs">
        <tr>
            <th>Type de connecteur</th>
            <th>Connecteur</th>
            <th>Hérité</th>
            <th>&nbsp;</th>
        </tr>

        <?php foreach ($flux_connecteur_list as $connecteur_info) : ?>
            <tr>
                <td>

                    <?php echo $connecteur_info['connecteur_type'];?>
                    <?php if ($connecteur_info['connecteur_with_same_type']) : ?>
                        (connecteur #<?php echo $connecteur_info['num_same_type'] + 1;?>)
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($connecteur_info['connecteur_info']) : ?>
                        <a href='<?php $this->url("Connecteur/edition?id_ce={$connecteur_info['connecteur_info']['id_ce']}") ?>'>
                            <?php hecho($connecteur_info['connecteur_info']['libelle']) ?>
                        </a>
                        &nbsp;(<?php hecho($connecteur_info['connecteur_info']['id_connecteur']) ?>)
                    <?php else :?>
                        AUCUN
                    <?php endif;?>
                </td>
                <td>
                    <?php if ($connecteur_info['connecteur_info']) : ?>
                        <?php if ($connecteur_info['connecteur_info']['id_e'] != $id_e) : ?>
                            <em> de
                                <a href='Entite/detail?id_e=<?php echo $connecteur_info['connecteur_info']['id_e'];?>'>
                                    <?php echo $connecteur_info['connecteur_info']['denomination']; ?>
                                </a>
                            </em>
                        <?php endif;?>
                    <?php endif;?>
                    &nbsp;
                </td>
                <td>
                    <?php if (! $connecteur_info['inherited_flux'] && ! $all_herited && $connecteur_edition) :?>
                        <?php
                        $fluxEditionUrl = sprintf(
                            'Flux/edition?id_e=%s&flux=%s&type=%s&num_same_type=%s',
                            $id_e,
                            $connecteur_info['id_flux'],
                            $connecteur_info['connecteur_type'],
                            $connecteur_info['num_same_type']
                        );
                        ?>
                        <a class='btn btn-primary' href='<?php $this->url($fluxEditionUrl); ?>'>
                            <i class="fa fa-link"></i>&nbsp;
                            Associer
                        </a>
                    <?php endif;?>
                </td>
            </tr>
        <?php endforeach;?>
    </table>
    <?php if ($id_e_mere && ! $all_herited) : ?>
        <form action='<?php $this->url('Flux/toogleHeritage'); ?>' method='post' >
            <?php $this->displayCSRFInput(); ?>
            <input type='hidden' name='id_e' value='<?php echo $id_e ?>' />
            <input type='hidden' name='flux' value='<?php hecho($flux_connecteur_list[0]['id_flux']) ?>' />
            <?php if ($flux_connecteur_list[0]['inherited_flux']) :?>
                <?php if ($connecteur_edition) : ?>
                    <button type='submit' class='btn btn-primary'>
                        <i class='fa fa-minus-circle'></i>&nbsp;Supprimer l'héritage
                    </button>
                <?php endif;?>
                <br/>
                <em>(type de dossier hérité de la mère)</em>
            <?php elseif ($connecteur_edition) :?>
                <button type='submit' class='btn btn-primary'>
                    <i class='fa fa-plus-circle'></i>&nbsp;Faire hériter
                </button>
            <?php endif;?>
        </form>
    <?php endif;?>
</div>
