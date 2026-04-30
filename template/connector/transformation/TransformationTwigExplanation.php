<?php

/**
 * @var Gabarit $this
 */

?>
<div class="box">
    <a class="collapse-link" data-bs-toggle="collapse" data-bs-target="#collapseTwigDocumentation">
        <h2><em class="fas fa-plus-square"></em>Explications</h2>
    </a>

    <div class="collapse alert alert-info" id="collapseTwigDocumentation">
        <p>Identifiant de l'élément représente l'élément qui va recevoir le résultat de la transformation</p>
        <p>Transformation représente une expression <a href="https://twig.symfony.com/" target="_blank">twig</a> dont le
            résultat sera affecté à l'élément associé</p>
        <?php $this->render('/twigDocumentation/TwigCommandDocumentation'); ?>
    </div>

</div>
