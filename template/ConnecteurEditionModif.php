<?php

/** @var Gabarit $this */
?>
<a class='btn btn-link' href='Connecteur/edition?id_ce=<?php echo $connecteur_entite_info['id_ce']?>'>
    <i class="fas fa-arrow-left"></i>&nbsp;Retour à la définition du connecteur
</a>


<?php $this->render("DonneesFormulaireEdition"); ?>

