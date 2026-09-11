<?php

declare(strict_types=1);

/**
 * @var int $id_e
 * @var bool $mfa_obligation_enabled
 * @var bool $mfa_obligation_inherited
 * @var ?int $mfa_obligation_ancestor_id
 * @var ?string $mfa_obligation_ancestor_name
 */

?>
<div class="box">
    <h2 id="desc-mfa-obligation">Double authentification (MFA)</h2>
    <?php if ($mfa_obligation_inherited) : ?>
        <p>
            <i class="fas fa-shield-alt text-success"></i>&nbsp;
            L'obligation de double authentification est activée sur l'entité mère
            <strong><a href='<?php $this->url("Entite/securite?id_e=$mfa_obligation_ancestor_id"); ?>'><?php hecho($mfa_obligation_ancestor_name); ?></a></strong>.
            Elle s'applique à cette entité et ne peut pas être désactivée ici.
        </p>
    <?php elseif ($mfa_obligation_enabled) : ?>
        <p>
            <i class="fas fa-shield-alt text-success"></i>&nbsp;
            La double authentification est obligatoire sur cette entité et ses entités filles.
        </p>
        <p>
            Tous les utilisateurs rattachés à cette entité et à ses entités filles doivent la configurer
            dès leur prochaine connexion pour accéder à Pastell.
        </p>
        <form action='Entite/doSecurite' method='post' class='d-inline'>
            <input type="hidden" name='id_e' value='<?php echo $id_e ?>'/>
            <input type="hidden" name='mfa_obligation' value='0'/>
            <?php $this->displayCSRFInput() ?>
            <button type='submit' class='btn btn-danger'
                    onclick="return confirm('Confirmer la désactivation ?')">
                <i class="fas fa-shield-alt"></i>&nbsp;Désactiver la double authentification
            </button>
        </form>
    <?php else : ?>
        <p>
            En activant l'obligation, tous les utilisateurs rattachés à cette entité et à ses entités filles
            devront configurer la double authentification (TOTP) dès leur prochaine connexion pour accéder à Pastell.
        </p>
        <form action='Entite/doSecurite' method='post' class='d-inline'>
            <input type="hidden" name='id_e' value='<?php echo $id_e ?>'/>
            <input type="hidden" name='mfa_obligation' value='1'/>
            <?php $this->displayCSRFInput() ?>
            <button type='submit' class='btn btn-primary'
                    onclick="return confirm('Confirmer l\'activation ?')">
                <i class="fas fa-shield-alt"></i>&nbsp;Activer la double authentification
            </button>
        </form>
    <?php endif; ?>
</div>
