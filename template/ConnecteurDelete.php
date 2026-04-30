<?php

/** @var Gabarit $this */
?>
<a class='btn btn-link' href='Connecteur/edition?id_ce=<?php echo $connecteur_entite_info['id_ce']?>'>
    <i class="fas fa-arrow-left"></i>&nbsp;Retour à la définition du connecteur
</a>

<div class="box">
<h2>Connecteur <?php hecho($connecteur_entite_info['type']) ?> - <?php hecho($connecteur_entite_info['id_connecteur'])?> : <?php hecho($connecteur_entite_info['libelle']) ?> 
</h2>

<div class='alert alert-danger'>
Attention, la suppression du connecteur est irréversible !
</div>

<form action='<?php $this->url("Connecteur/doDelete") ?>' method='post' >
    <?php $this->getCSRFToken()->displayFormInput(); ?>
    <input type='hidden' name='id_ce' value='<?php echo $connecteur_entite_info['id_ce'] ?>' />
    <button type="submit" class="btn btn-danger">
        <i class="fas fa-trash"></i>&nbsp;Supprimer
    </button></form>

</div>

