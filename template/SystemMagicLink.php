<?php

declare(strict_types=1);

/**
 * @var Gabarit $this
 * @var array<int, array<string, mixed>> $active_magic_links
 */

?>
<style>
    .magic-link-table {
        table-layout: fixed;
        width: 100%;
    }

    .magic-link-table td {
        overflow-wrap: anywhere;
    }

    .magic-link-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .25rem;
        align-items: center;
    }

    .magic-link-actions form {
        margin: 0;
    }

    .magic-link-actions .btn {
        white-space: nowrap;
    }
</style>
<div class="box">
    <a href="System/magicLinkEdition" class='btn btn-primary grow'
    ><i class="fas fa-plus-circle"></i>&nbsp;Créer un accès temporaire</a>

    <table class='table table-striped magic-link-table'>
        <colgroup>
            <col style="width: 12%"/>
            <col style="width: 13%"/>
            <col style="width: 8%"/>
            <col style="width: 12%"/>
            <col style="width: 9%"/>
            <col style="width: 12%"/>
            <col style="width: 8%"/>
            <col style="width: 26%"/>
        </colgroup>
        <thead>
            <tr>
                <th>Titulaire</th>
                <th>Email</th>
                <th>Code</th>
                <th>Motif</th>
                <th>Créé par</th>
                <th>Expiration</th>
                <th>Temps restant</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($active_magic_links as $link) : ?>
                <tr>
                    <td><?= get_hecho(trim($link['titulaire_prenom'] . ' ' . $link['titulaire_nom'])) ?></td>
                    <td><?= get_hecho($link['titulaire_email']) ?></td>
                    <td><strong><?= get_hecho($link['code']) ?></strong></td>
                    <td><?= get_hecho($link['motif']) ?></td>
                    <td><?= get_hecho($link['created_by_login'] ?? '') ?></td>
                    <td><?= get_hecho($link['expires_at']) ?></td>
                    <td>
                        <span class="magic-link-timer"
                              data-expires="<?= strtotime($link['expires_at']) ?>">…</span>
                    </td>
                    <td>
                        <div class="magic-link-actions">
                            <form action='<?php $this->url('System/doMagicLinkResend'); ?>' method='post'>
                                <?php $this->displayCSRFInput() ?>
                                <input type='hidden' name='id' value='<?= get_hecho((string)$link['id']) ?>'/>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-envelope"></i>&nbsp;Renvoyer le mail
                                </button>
                            </form>
                            <a class="btn btn-primary btn-sm"
                               href='Journal/index?id_u=<?= (int)$link['id_u'] ?>'>
                                <i class="fas fa-history"></i>&nbsp;Dernières actions
                            </a>
                            <a class="btn btn-danger btn-sm"
                               href='<?php $this->url('System/magicLinkRevoke?id=' . rawurlencode((string)$link['id'])); ?>'>
                                <i class="fas fa-ban"></i>&nbsp;Révoquer
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script type="text/javascript">
    (function () {
        function tick() {
            const now = Math.floor(Date.now() / 1000);
            document.querySelectorAll('.magic-link-timer').forEach(function (element) {
                const remaining = parseInt(element.getAttribute('data-expires'), 10) - now;
                if (remaining <= 0) {
                    element.textContent = 'Expiré';
                    return;
                }
                const h = String(Math.floor(remaining / 3600)).padStart(2, '0');
                const m = String(Math.floor((remaining % 3600) / 60)).padStart(2, '0');
                const s = String(remaining % 60).padStart(2, '0');
                element.textContent = `${h}:${m}:${s}`;
            });
        }

        tick();
        setInterval(tick, 1000);
    })();
</script>
