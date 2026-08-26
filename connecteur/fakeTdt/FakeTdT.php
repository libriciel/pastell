<?php

class FakeTdT extends TdtAdapter
{
    public const string ANNEXES_ENVOYEES_FIELD = 'annexes_envoyees';

    private int $checkStatus;
    private DonneesFormulaire $connecteurConfig;

    public function setConnecteurConfig(DonneesFormulaire $donneesFormulaire)
    {
        $this->connecteurConfig = $donneesFormulaire;
        $this->checkStatus = (int)$donneesFormulaire->get('tdt_check_status', TdtConnecteur::STATUS_ACQUITTEMENT_RECU);
    }

    public function getLogicielName()
    {
        return "FakeTdT";
    }

    public function sendActes(TdtActes $tdtActes)
    {
        $id_transaction = mt_rand(1, mt_getrandmax());

        $annexes_envoyees = $this->getAnnexesEnvoyees();
        $annexes_envoyees[$id_transaction] = array_map(
            fn(Fichier $fichier): string => $this->getFilenameTransformation($fichier->filename),
            $tdtActes->autre_document_attache
        );
        $this->connecteurConfig->setData(
            self::ANNEXES_ENVOYEES_FIELD,
            json_encode($annexes_envoyees, JSON_THROW_ON_ERROR)
        );

        return $id_transaction;
    }

    public function getStatus($id_transaction)
    {
        return $this->checkStatus;
    }

    public function getARActes()
    {
        return file_get_contents($this->getDataDir() . '/connector/fakeTdt/ar-actes.xml');
    }

    public function getDateAR($id_transaction)
    {
        return date("Y-m-d");
    }

    public function getBordereau($id_transaction)
    {
        return file_get_contents($this->getDataDir() . '/_shared/vide.pdf');
    }

    public function getActeTamponne($id_transaction, ?string $date_affichage = null): ?string
    {
        return file_get_contents($this->getDataDir() . '/_shared/vide.pdf');
    }

    public function getListReponsePrefecture($transaction_id)
    {
        return [];
    }

    public function sendHelios(Fichier $fichierHelios)
    {
        return  mt_rand(1, mt_getrandmax());
    }

    public function getStatusHelios($id_transaction)
    {
        return TdtConnecteur::STATUS_HELIOS_INFO;
    }

    public function getStatusInfo($status)
    {
        return $status;
    }

    public function getFichierRetour($tedetis_transaction_id)
    {
        return file_get_contents($this->getDataDir() . '/connector/fakeTdt/pes_acquit.xml');
    }

    public function getAnnexesTamponnees(string $transaction_id, ?string $date_publication = null): array
    {
        $result = [];
        foreach ($this->getAnnexesEnvoyees()[$transaction_id] ?? [] as $filename) {
            $result[] = [
                'filename' => $filename,
                'content' => file_get_contents($this->getDataDir() . '/_shared/vide.pdf'),
            ];
        }
        return $result;
    }

    public function getFilenameTransformation($filename)
    {
        return $filename;
    }

    /**
     * @return array<string, string[]>
     */
    private function getAnnexesEnvoyees(): array
    {
        return json_decode(
            $this->connecteurConfig->get(self::ANNEXES_ENVOYEES_FIELD) ?: '[]',
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    public function getPESRetourListe()
    {
        return [];
    }

    public function annulationActes($id_transaction)
    {
        return $id_transaction;
    }
}
