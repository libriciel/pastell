<?php

declare(strict_types=1);

use Pastell\Viewer\Viewer;

class PESRetourVisionneuse implements Viewer
{
    private function getDomaineLibelle(string $libelle_numero): string
    {
        $libelle_list = [
            'technique',
            'technique',
            'technique',
            'validité du certificat',
            'pièce justificative',
            'dépense',
            'recette',
            'budget',
        ];

        return $libelle_list[$libelle_numero] ?? \sprintf('Domaine inconnu (%s)', $libelle_numero);
    }

    public function display(string $filename, string $filepath): void
    {
        if (!\file_exists($filepath)) {
            echo "Le fichier n'est pas encore disponible";
            return;
        }

        $xml = \simplexml_load_file($filepath);

        if ($xml === false) {
            echo 'Le fichier n\'existe pas';
            return;
        }

        $nomFic = $xml->Enveloppe->Parametres->NomFic['V'];

        $nb_erreur = 0;
        if (!empty($xml->ACQUIT->ElementACQUIT)) {
            foreach ($xml->ACQUIT->ElementACQUIT as $elementACQUIT) {
                if ((int)$elementACQUIT->EtatAck['V'] !== 1) {
                    $nb_erreur++;
                }
            }
        }
        ?>
        <style>
            .pes_retour {
                border-style: solid;
                border-width: thin;
                padding: 5px;
            }
        </style>
        <div class='pes_retour'>
            <h1>Rapport acquittement</h1>
            <p>
                <b>Identification du flux : </b><?php echo $nomFic; ?>
            </p>
            <p class="libelleControl">
                <?php echo count($xml->ACQUIT->ElementACQUIT); ?> éléments
                <?php if ($nb_erreur > 0) : ?>
                    &nbsp;-&nbsp;
                    <b style='color:red'><?php echo $nb_erreur; ?> erreur<?php echo $nb_erreur > 1 ? 's' : ''; ?></b>
                <?php endif; ?>
            </p>
            <?php if (!empty($xml->ACQUIT->ElementACQUIT)) : ?>
                <table>
                    <tr>
                        <th>Domaine</th>
                        <th>Exercice</th>
                        <th>Numéro de bordereau</th>
                        <th>Acquitté</th>
                        <th>Erreur</th>
                    </tr>
                    <?php foreach ($xml->ACQUIT->ElementACQUIT as $elementACQUIT) : ?>
                        <tr>
                            <td>
                                <?php echo $this->getDomaineLibelle((string)$elementACQUIT->DomaineAck['V']); ?>
                            </td>
                            <td><?php hecho((string)$elementACQUIT->ExerciceBord['V']); ?></td>
                            <td><?php hecho((string)$elementACQUIT->NumBord['V']); ?></td>
                            <td>
                                <?php if ((int)$elementACQUIT->EtatAck['V'] === 1) : ?>
                                    <b style='color:green'>OUI</b>
                                <?php else : ?>
                                    <b style='color:red'>NON</b>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int)$elementACQUIT->EtatAck['V'] === 1) : ?>
                                    &nbsp;
                                <?php else : ?>
                                    <?php if ($elementACQUIT->Erreur) : ?>
                                        <b>Erreur <?php hecho((string)$elementACQUIT->Erreur->NumAnoAck['V']); ?> :
                                        <?php hecho((string)$elementACQUIT->Erreur->LibelleAnoAck['V']); ?>
                                    <?php endif; ?>
                                    <?php foreach ($elementACQUIT->DetailPiece as $detailPiece) : ?>
                                        <?php if ($detailPiece->Erreur) : ?>
                                            <?php if ($detailPiece->Erreur->NumAnoAck['V']) : ?>
                                                <br>
                                                Sur pièce n° <?php hecho((string)$detailPiece->NumPiece['V']); ?>
                                                , <?php hecho((string)$detailPiece->Erreur->NumAnoAck['V']); ?>
                                                : <?php hecho((string)$detailPiece->Erreur->LibelleAnoAck['V']); ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php foreach ($detailPiece->DetailLigne as $detailLigne) : ?>
                                            <?php if ($detailLigne->Erreur->NumAnoAck['V']) : ?>
                                                <br>
                                                Sur pièce n° <?php hecho((string)$detailPiece->NumPiece['V']); ?>
                                                , ligne n° <?php hecho((string)$detailLigne->NumLigne['V']); ?>
                                                , <?php hecho((string)$detailLigne->Erreur->NumAnoAck['V']); ?>
                                                : <?php hecho((string)$detailLigne->Erreur->LibelleAnoAck['V']); ?>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?></b>
                                <?php endif; ?>
                            </td>

                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
