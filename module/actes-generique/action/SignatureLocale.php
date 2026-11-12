<?php

use Pastell\Client\Crypto\CryptoClientException;
use Psr\Http\Client\ClientExceptionInterface;

class SignatureLocale extends ChoiceActionExecutor
{
    /**
     * @throws NotFoundException
     * @throws UnrecoverableException
     * @throws CryptoClientException
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    public function go()
    {
        $recuperateur = $this->getRecuperateur();

        /** @var Libersign $connector */
        $connector = $this->getConnecteur('signature');
        $connectorConfig = $this->getConnecteurConfigByType('signature');

        if ($this->getDonneesFormulaire()->fieldExists('arrete')) {
            $acteFilePath = $this->getDonneesFormulaire()->getFilePath('arrete');
            $field = 'arrete';
        } elseif ($this->getDonneesFormulaire()->fieldExists('document')) {
            //FIXME WTF ! => C'est vraiement pas beau !
            // Document à faire signer CDG85
            $acteFilePath = $this->getDonneesFormulaire()->getFilePath('document');
            $field = 'document';
        } else {
            throw new UnrecoverableException('arrete ou document non présent');
        }
        $user = $this->objectInstancier->getInstance(UtilisateurSQL::class);
        $myUser = $user->getInfo($this->id_u);

        $publicCertificate = $recuperateur->get('publicCertificate');
        $dataToSignList = $recuperateur->get('dataToSignList');
        if ($publicCertificate && !$dataToSignList) {
            if ($connectorConfig->get('libersign_signature_type') === Libersign::LIBERSIGN_SIGNATURE_PADES) {
                echo $connector->padesGenerateDataToSign($acteFilePath, $publicCertificate, $myUser['prenom'] . ' ' . $myUser['nom']);
            } else {
                echo $connector->cadesGenerateDataToSign($acteFilePath, $publicCertificate);
            }
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
        if ($connectorConfig->get('libersign_signature_type') === Libersign::LIBERSIGN_SIGNATURE_PADES) {
            $signedFile = $connector->padesGenerateSignature(
                $acteFilePath,
                $publicCertificate,
                $dataToSign,
                $generatedDataToSign['signatureDateTime'],
                $myUser['prenom'] . ' ' . $myUser['nom']
            );
            $filename = $this
                    ->getDonneesFormulaire()
                    ->getFileNameWithoutExtension($field) . '.' . $signedFile->extension;
        } else {
            $signedFile = $connector->cadesGenerateSignature(
                $acteFilePath,
                $publicCertificate,
                $dataToSign,
                $generatedDataToSign['signatureDateTime']
            );
            $filename = 'signature.pk7';
        }

        $actes = $this->getDonneesFormulaire();
        $actes->setData('signature_link', 'La signature a été recupérée');
        $actes->addFileFromData('signature', $filename, $signedFile->signature);

        $this->getActionChange()->addAction(
            $this->id_d,
            $this->id_e,
            $this->id_u,
            'recu-iparapheur',
            "La signature a été récupérée depuis l'applet de signature"
        );
        $this->notify('recu-iparapheur', $this->type, "La signature a été récupérée depuis l'applet de signature");
        $this->setLastMessage('La signature a été correctement récupérée');

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
            "Signature de l'acte - " . $document_info['titre'] . ' (' . $type_name . ')',
            'module/actes/SignatureLocale'
        );
        return true;
    }
}
