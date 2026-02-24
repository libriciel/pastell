<?php

/**
 * @var Gabarit $this
 * @var int $id_e
 * @var int $page
 * @var string $search
 * @var bool $droit_edition
 * @var int $offset
 * @var int $nbAgent
 * @var array $listAgent
 */

?>
<div class="box">

    <h2>Liste des agents</h2>

    <form action='Entite/agents' method='get' class="input-group">
        <input type='hidden' name='id_e' value='<?php echo $id_e; ?>'/>
        <input type='hidden' name='page' value='<?php echo $page; ?>'/>
        <input type='text' name='search' value='<?php hecho($search); ?>' class="form-control col-md-2 me-2"/>
        <button type='submit' class='btn btn-primary'><i class='fa fa-search'></i>&nbsp;Rechercher</button>

        <?php if ($droit_edition) : ?>
            <a href="Entite/import?id_e=<?php echo $id_e; ?>&onglet=agents&page_retour=2"
               class='btn btn-primary ms-2'><i class="fa fa-upload"></i>&nbsp;Importer</a>
        <?php endif; ?>
    </form>

    <?php
    $this->suivantPrecedent(
        $offset,
        AgentSQL::NB_MAX,
        $nbAgent,
        "Entite/agents?id_e=$id_e&page=$page&search=$search"
    ); ?>

    <table class="table table-striped">
        <tr>
            <th>Matricule</th>
            <th>Nom</th>
            <th>Prénom</th>
            <th>Grade</th>
            <?php if ($id_e == 0) : ?>
                <th>Collectivité</th>
            <?php endif; ?>
        </tr>
        <?php foreach ($listAgent as $i => $agent) : ?>
            <tr>
                <td><label for="label_agent_<?php echo $i; ?>"><?php hecho($agent['matricule']); ?></label></td>
                <td><label for="label_agent_<?php echo $i; ?>"><?php hecho($agent['nom_patronymique']); ?></label></td>
                <td><label for="label_agent_<?php echo $i; ?>"><?php hecho($agent['prenom']); ?></label></td>
                <td>
                    <label for="label_agent_<?php echo $i; ?>"><?php hecho($agent['emploi_grade_libelle']); ?></label>
                </td>
                <?php if ($id_e == 0) : ?>
                    <td>
                        <a href='Entite/detail?id_e=<?php echo $agent['id_e'] ?>&page=2'
                        ><?php hecho($agent['denomination']); ?></a>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
    </table>

    <?php
    $this->suivantPrecedent(
        $offset,
        AgentSQL::NB_MAX,
        $nbAgent,
        "Entite/agents?id_e=$id_e&page=$page&search=$search"
    ); ?>

</div>
