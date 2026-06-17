<?php

/**
 * @var Gabarit $this
 * @var array $role_info
 * @var string $role
 * @var bool $role_edition
 * @var array<string, array<string, bool>> $droits_administration
 * @var array<string, array<string, array{libelle: string, droits: array<string, bool>}>> $droits_type_dossier
 */

?>

<a class='btn btn-link' href='<?php $this->url("Role/index") ?>'><i class="fas fa-arrow-left"></i>&nbsp;Retour à la liste des rôles</a>

<div class="box">
    <h2>Gestion du rôle : <?php hecho($role_info['libelle'] ?? '') ?></h2>
    <div class="bloc-flex">
        <a class='btn btn-primary inline' href='<?php
        $this->url("Role/edition?role=" . get_hecho($role)) ?>'><i class='fas fa-pen'></i>&nbsp;Modifier le libellé</a>
        <form action='<?php $this->url("Role/doDelete") ?>' method='post' class="form-suppression">
            <?php $this->displayCSRFInput() ?>
            <input type='hidden' name='role' value='<?php hecho($role) ?>'/>
            <button type="submit" class="btn btn-danger">
                <i class="fas fa-trash"></i>&nbsp;Supprimer le rôle
            </button>
        </form>
    </div>
</div>


<div class="box">
    <form action='<?php $this->url("Role/doDetail") ?>' method='post'>
        <?php $this->displayCSRFInput() ?>
        <?php if ($role_edition) : ?>
            <input type='hidden' name='role' value='<?php hecho($role); ?>'/>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-floppy-disk"></i>&nbsp;Enregistrer
            </button>
        <?php endif;?>

        <h2>Gestion des droits</h2>
        <div class="pt-3 input-group col-md-4 mb-3">
            <input type="search" id="recherche-droit" class="form-control"
                   placeholder="Filtrer par catégorie ou par droit..." autocomplete="off"/>
            <button type="button" class="btn btn-primary btn-search" id="search-droit"><i class="fas fa-search"></i>
            </button>
            <div class="col-md-8"></div>
        </div>
        <p id="recherche-droit-aucun-resultat" class="text-muted" style="display: none;">
            Aucune catégorie ne correspond à votre recherche.
        </p>

        <?php
        $afficherCategorie = static function (string $libelle, array $droits, bool $role_edition, string $categorieType): void {
            ?>
            <div class="js-categorie-droit mb-3 border rounded" data-categorie-type="<?= htmlspecialchars($categorieType) ?>">
                <div class="d-flex align-items-stretch">
                    <div class="d-flex align-items-center p-3 bg-light border-end" style="width: 30%; flex-shrink: 0; overflow-wrap: break-word;">
                        <h3 class="titre-section-ligne m-0 fs-6 fw-semibold"><?php hecho($libelle) ?></h3>
                    </div>
                    <div class="flex-grow-1 d-flex flex-column">
                        <?php $lastDroit = array_key_last($droits); ?>
                        <?php foreach ($droits as $droit => $enabled) : ?>
                            <div class="js-droit-ligne px-3 py-1 flex-grow-1<?= $droit !== $lastDroit ? ' border-bottom' : '' ?>" style="display: flex; align-items: center;">
                                <?php if ($role_edition) : ?>
                                    <input style="width: 15px; height: 15px; vertical-align: middle;" type='checkbox' name='droit[]'
                                           value='<?= $droit ?>' <?= $enabled ? "checked='checked'" : '' ?>/>&nbsp;
                                <?php endif; ?>
                                <?= $droit ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php
        };
        ?>

        <?php foreach ($droits_administration as $libelle => $droits) : ?>
            <?php $afficherCategorie($libelle, $droits, $role_edition, 'administration'); ?>
        <?php endforeach; ?>

        <h2 id="titre-types-dossiers" style="margin-top: 30px; font-size: 150%;">Types de dossiers</h2>
        <?php foreach ($droits_type_dossier as $type_name => $groupes_type) : ?>
            <div class="js-groupe-type-dossier">
                <h3 class="js-titre-type" style="font-size: 135%; margin-top: 20px;"><?php hecho($type_name) ?></h3>
                <?php foreach ($groupes_type as $groupe) : ?>
                    <?php $afficherCategorie($groupe['libelle'], $groupe['droits'], $role_edition, 'dossier'); ?>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <?php if ($role_edition) : ?>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-floppy-disk"></i>&nbsp;Enregistrer
            </button>
        <?php endif;?>
    </form>
    <script>
        (function () {
            const input = document.getElementById('recherche-droit');
            if (!input) {
                return;
            }
            const titreTypesDossiers = document.getElementById('titre-types-dossiers');
            const aucunResultat = document.getElementById('recherche-droit-aucun-resultat');

            function normalise(texte) {
                return texte.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            }

            const categories = Array.from(document.querySelectorAll('.js-categorie-droit')).map(function (el) {
                const groupe = el.closest('.js-groupe-type-dossier');
                const typeNom = groupe ? normalise(groupe.querySelector('.js-titre-type').textContent) : '';
                return {
                    table: el,
                    titre: normalise(el.querySelector('.titre-section-ligne').textContent),
                    typeNom: typeNom,
                    lignes: Array.from(el.querySelectorAll('.js-droit-ligne')).map(function (ligne) {
                        return {ligne: ligne, texte: normalise(ligne.textContent)};
                    })
                };
            });

            const groupesType = Array.from(document.querySelectorAll('.js-groupe-type-dossier')).map(function (div) {
                return {
                    div: div,
                    tables: Array.from(div.querySelectorAll('.js-categorie-droit'))
                };
            });

            function filtrer() {
                const recherche = normalise(input.value.trim());
                let nbCategorieVisible = 0;

                categories.forEach(function (categorie) {
                    const titreMatch = categorie.titre.includes(recherche) || categorie.typeNom.includes(recherche);
                    let nbLigneVisible = 0;

                    categorie.lignes.forEach(function (item) {
                        const visible = !recherche || titreMatch || item.texte.includes(recherche);
                        item.ligne.style.display = visible ? 'flex' : 'none';
                        if (visible) {
                            nbLigneVisible++;
                        }
                    });

                    const categorieVisible = !recherche || titreMatch || nbLigneVisible > 0;
                    categorie.table.style.display = categorieVisible ? '' : 'none';
                    if (categorieVisible) {
                        nbCategorieVisible++;
                    }
                });

                let nbGroupeTypeVisible = 0;
                groupesType.forEach(function (groupe) {
                    const hasVisible = groupe.tables.some(function (t) { return t.style.display !== 'none'; });
                    groupe.div.style.display = hasVisible ? '' : 'none';
                    if (hasVisible) {
                        nbGroupeTypeVisible++;
                    }
                });

                if (titreTypesDossiers) {
                    titreTypesDossiers.style.display = nbGroupeTypeVisible > 0 ? '' : 'none';
                }
                if (aucunResultat) {
                    aucunResultat.style.display = nbCategorieVisible === 0 ? '' : 'none';
                }
            }

            input.addEventListener('input', filtrer);
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                }
            });
        })();
    </script>
</div>
