<?php

declare(strict_types=1);

/**
 * @var Gabarit $this
 */
?>
<div class="box">
    <a class="collapse-link" data-bs-toggle="collapse" data-bs-target="#collapseTwigDocumentation">
        <h2><em class="fa fa-plus-square"></em>Explications</h2>
    </a>

    <div class="collapse alert alert-info" id="collapseTwigDocumentation">
        <p>
            Tous les éléments du bordereau peuvent recevoir une expression
            <a href="https://twig.symfony.com/" target="_blank">twig</a> qui sera analysée lors de la génération du
            bordereau
        </p>
        <?php $this->render('/twigDocumentation/TwigCommandDocumentation'); ?>

        <p>L'expression pour les fichiers et les unités d'archivage doit renvoyer un identifiant d'élément Pastell de
            type fichier</p>
        <p>Si l'expression d'un fichier commence par #ZIP# alors, le fichier sera dézippé, les répertoires seront
            transformés en unité d'archivage et les fichiers en document (exemple : #ZIP#fichier_zip)</p>

        <table class="table table-striped" aria-label="Explication sur le pré-processeur Pastell">
            <tr>
                <th class="w300" id="seda_generator_keyword">Mot clé des descriptions de fichiers et unités
                    d'archivage
                </th>
                <th id="seda_generator_explanation">Explication</th>
                <th id="seda_generator_exemple">Exemple</th>
            </tr>
            <tr>
                <td>#FILE_NUM#</td>
                <td>Numéro de fichier (commence par 0) dans le cas d'un fichier Pastell multiple.</td>
                <td>Annexe numéro {{ #FILE_NUM# + 1}}</td>
            </tr>
            <tr>
                <td>#FILENAME#</td>
                <td>Dans le cadre d'un fichier ZIP, le nom du fichier (sans le répertoire)</td>
                <td>Fichier #FILENAME#</td>
            </tr>
            <tr>
                <td>#FILEPATH#</td>
                <td>Dans le cadre d'un fichier ZIP, le chemin relatif à la racine du ZIP vers le fichier</td>
                <td>Fichier #FILEPATH#</td>
            </tr>
            <tr>
                <td>#IS_DIR#</td>
                <td>Dans le cadre d'un fichier ZIP, remplacé par "true" sur un répertoire, "false" sinon</td>
                <td>{% if (#IS_DIR#) %}Unité d'archivage #FILENAME#{% endif %}</td>
            </tr>
            <tr>
                <td>#IS_FILE#</td>
                <td>Dans le cadre d'un fichier ZIP, remplacé par "true" sur un fichier régulier (hors répertoire),
                    "false" sinon
                </td>
                <td>{% if (#IS_FILE#) %}Fichier #FILENAME#{% endif %}</td>
            </tr>
        </table>

        Exemple de génération de mots-clés:
        <table class="table table-striped" aria-label="Exemple de génération des mots clés">
            <tr>
                <th class="w600" id="keyword_exemple">Exemple</th>
                <th id="keyword_exemple_result">Résultat</th>
            </tr>
            <tr>
                <td >
                    <pre style="white-space: normal">
                        {% for montant in xpath_array('fichier_pes','//*/MtHT/@V' ) %}
                        {{ montant }},Montant pour une piece
                        {% endfor %}
                    </pre>
                </td>
                <td>
                    <pre lang="xml">
                        <?php hecho(
                            '
                    <Keyword>
                        <KeywordContent>39724.75</KeywordContent>
                        <KeywordReference>Montant pour une piece</KeywordReference>
                    </Keyword>
                    <Keyword>
                        <KeywordContent>12.12</KeywordContent>
                        <KeywordReference>Montant pour une piece</KeywordReference>
                    </Keyword>'
                        ); ?>
                    </pre>
                </td>
            </tr>
        </table>
    </div>
</div>
