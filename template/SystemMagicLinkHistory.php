<?php

declare(strict_types=1);

/**
 * @var Gabarit $this
 * @var array<int, array<string, mixed>> $magic_link_history
 * @var string $search
 */

?>
<div class="box">
    <form action="System/magicLinkHistory" method='get' class="table-end">
        <table class='table table-striped'>
            <tr>
                <td class='w300'>Recherche</td>
                <td>
                    <input class="form-control col-md-5" type='text' name='search'
                           value='<?php hecho($search) ?>'
                           placeholder="Rechercher par nom, prénom, email ou motif"
                    />
                </td>
            </tr>
        </table>
        <a href="System/magicLinkHistory" class="btn btn-outline-primary"
        ><i class="fas fa-undo"></i>&nbsp;Réinitialiser</a>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-search"></i>&nbsp;Rechercher
        </button>
    </form>

    <h2>Historique des accès créés - résultats de la recherche</h2>

    <table class='table table-striped'>
        <thead>
            <tr>
                <th>Titulaire</th>
                <th>Email</th>
                <th>Motif</th>
                <th>Créé par</th>
                <th>Créé le</th>
                <th>Expiration</th>
                <th>Statut</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($magic_link_history as $link) : ?>
                <tr>
                    <td><?= get_hecho(trim($link['titulaire_prenom'] . ' ' . $link['titulaire_nom'])) ?></td>
                    <td><?= get_hecho($link['titulaire_email']) ?></td>
                    <td><?= get_hecho($link['motif']) ?></td>
                    <td><?= get_hecho($link['created_by_login'] ?? '') ?></td>
                    <td><?= get_hecho($link['created_at']) ?></td>
                    <td><?= get_hecho($link['expires_at']) ?></td>
                    <td>
                        <?php if ($link['revoked_at'] !== null) : ?>
                            <p class='badge bg-danger'>Révoqué</p>
                        <?php else : ?>
                            <p class='badge bg-secondary'>Expiré</p>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a class="btn btn-primary btn-sm"
                           href='Journal/index?id_u=<?= (int)$link['id_u'] ?>'>
                            <i class="fas fa-history"></i>&nbsp;Dernières actions
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
