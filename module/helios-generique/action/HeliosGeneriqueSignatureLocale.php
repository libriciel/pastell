<?php

use Pastell\Client\Crypto\CryptoClientException;
use Psr\Http\Client\ClientExceptionInterface;

class HeliosGeneriqueSignatureLocale extends ChoiceActionExecutor
{
    /**
     * @throws CryptoClientException
     * @throws UnrecoverableException
     * @throws NotFoundException
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    public function go()
    {
        $recuperateur = $this->getRecuperateur();

        /** @var Libersign $connector */
        $connector = $this->getConnecteur('signature');
        $publicCertificate = $recuperateur->get('publicCertificate');
        $dataToSignList = $recuperateur->get('dataToSignList');
        $pesFilepath = $this->getDonneesFormulaire()->getFilePath('fichier_pes');

        if ($publicCertificate && !$dataToSignList) {
            echo $connector->xadesGenerateDataToSign($pesFilepath, $publicCertificate);
            return true;
        }

        if (!$dataToSignList) {
            throw new UnrecoverableException("Aucune donnée de signature n'a été trouvée.");
        }
        $dataToSignListDecoded = json_decode($dataToSignList, true);
        $generatedDataToSign = json_decode($recuperateur->get('generatedDataToSign'), true);
        $dataToSign = $generatedDataToSign['dataToSignList'];
        foreach ($dataToSignListDecoded as $index => $signature) {
            $dataToSign[$index]['signatureValue'] = $signature;
        }

        $signedFile = $connector->xadesGenerateSignature(
            $pesFilepath,
            $publicCertificate,
            $dataToSign,
            $generatedDataToSign['signatureDateTime']
        );

        $this->getDonneesFormulaire()->addFileFromData(
            'fichier_pes_signe',
            $this
                ->getDonneesFormulaire()
                ->getFileNameWithoutExtension('fichier_pes') . '.' . $signedFile->extension,
            $signedFile->signature
        );
        $this->getDonneesFormulaire()->setData('signature_link', 'La signature a été recupérée');

        $this->getActionChange()->addAction(
            $this->id_d,
            $this->id_e,
            $this->id_u,
            'recu-iparapheur',
            "La signature a été récupérée depuis l'applet de signature"
        );
        $this->notify('recu-iparapheur', $this->type, "La signature a été récupérée depuis l'applet de signature");
        $this->setLastMessage('La signature a été récupérée');

        $this->redirect("/Document/detail?id_e=" . $this->id_e . "&id_d=" . $this->id_d . "&page=" . $this->page);
    }

    public function displayAPI()
    {
        throw new Exception("Cette fonctionnalité n'est pas disponible via l'API.");
    }

    /**
     * @throws UnrecoverableException
     * @throws NotFoundException
     */
    public function display()
    {
        $document_info = $this->getDocument()->getInfo($this->id_d);
        $this->setViewParameter('libersignConnecteur', $this->getConnecteur('signature'));
        $this->setViewParameter('title', $document_info['titre'] ?: $document_info['id_d']);

        $type_name = $this->getDocumentTypeFactory()->getFluxDocumentType($this->type)->getName();

        $this->renderPage(
            'Signature du fichier PES - ' . $this->getViewParameter()['title'] . ' (' . $type_name . ')',
            'module/helios/HeliosSignatureLocale'
        );
        return true;
    }
}
