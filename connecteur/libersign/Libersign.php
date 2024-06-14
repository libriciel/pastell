<?php

use Pastell\Client\Crypto\CryptoClient;
use Pastell\Client\Crypto\CryptoClientException;
use Pastell\Client\Crypto\CryptoClientFactory;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * @deprecated 4.0.0
 */
class Libersign extends SignatureConnecteur
{
    public const LIBERSIGN_SIGNATURE_CADES = 'CADES';
    public const LIBERSIGN_SIGNATURE_XADES = 'XADES';
    public const LIBERSIGN_SIGNATURE_PADES = 'PADES';

    /** @var DonneesFormulaire */
    private $collectiviteProperties;

    /**
     * @var string
     */
    private $cryptoUrl;

    /**
     * @var string
     */
    private $signatureType;

    /**
     * @var ConnecteurFactory
     */
    private $connecteurFactory;
    /**
     * @var ConnecteurEntiteSQL
     */
    private $connecteurEntiteSql;
    /**
     * @var CryptoClientFactory
     */
    private $cryptoClientFactory;
    /**
     * @var CryptoClient
     */
    private $cryptoClient;
    /**
     * @var DonneesFormulaire
     */
    private $globalConnectorConfig;

    public function __construct(
        ConnecteurFactory $connecteurFactory,
        ConnecteurEntiteSQL $connecteurEntiteSql,
        CryptoClientFactory $cryptoClientFactory,
        private readonly string $pastell_path,
    ) {
        $this->connecteurFactory = $connecteurFactory;
        $this->connecteurEntiteSql = $connecteurEntiteSql;
        $this->cryptoClientFactory = $cryptoClientFactory;
    }

    public function setConnecteurConfig(DonneesFormulaire $collectiviteProperties)
    {
        $this->collectiviteProperties = $collectiviteProperties;

        $globalConnectorId = $this->connecteurEntiteSql->getGlobal('libersign');
        if ($globalConnectorId) {
            $this->globalConnectorConfig = $this->connecteurFactory->getConnecteurConfig($globalConnectorId);
        }

        $url = $collectiviteProperties->get('libersign_crypto_url') ?: '';
        if (!$url && $this->globalConnectorConfig !== null) {
            $url = $this->globalConnectorConfig->get('libersign_crypto_url') ?: '';
        }
        $this->cryptoUrl = $url;
        $this->signatureType = $collectiviteProperties->get(
            'libersign_signature_type'
        ) ?: self::LIBERSIGN_SIGNATURE_XADES;
        $this->cryptoClient = $this->cryptoClientFactory->getClient($this->cryptoUrl);
    }

    public function getNbJourMaxInConnecteur()
    {
        throw new Exception("Not implemented");
    }

    public function getSousType()
    {
        throw new Exception("Not implemented");
    }

    public function getDossierID($id, $name)
    {
        return "n/a";
    }

    public function sendDossier(FileToSign $dossier)
    {
        throw new BadMethodCallException("Not implemented");
    }

    public function getSignature($dossierID, $archive = true)
    {
        throw new Exception("Not implemented");
    }

    public function getAllHistoriqueInfo($dossierID)
    {
        throw new Exception("Not implemented");
    }

    public function getLastHistorique($history): string
    {
        throw new Exception("Not implemented");
    }

    public function getDateSignature(stdClass|array $history): string
    {
        throw new Exception("Not implemented");
    }

    public function effacerDossierRejete($dossierID)
    {
        throw new Exception("Not implemented");
    }

    public function isLocalSignature()
    {
        return true;
    }

    public function displayLibersignJS(): void
    {
        // Variables included in template
        $libersign_applet_url = $this->collectiviteProperties->get('libersign_applet_url');
        $libersign_extension_update_url = $this->collectiviteProperties->get('libersign_extension_update_url');
        include_once $this->pastell_path . '/template/connector/libersign/LibersignJS.php';
    }

    public function isFinalState(string $lastState): bool
    {
        throw new BadMethodCallException('Not implemented');
    }

    public function isRejected(string $lastState): bool
    {
        throw new BadMethodCallException('Not implemented');
    }

    public function isDetached($signature): bool
    {
        throw new BadMethodCallException('Not implemented');
    }

    /**
     * Workaround because IParapheur::getSignature() does not return only the signature
     *
     * @param $file
     * @return mixed
     */
    public function getDetachedSignature($file)
    {
        throw new BadMethodCallException('Not implemented');
    }

    /**
     * Workaround because IParapheur::getSignature() does not return only the signature
     *
     * @param $file
     * @return mixed
     */
    public function getSignedFile($file)
    {
        throw new BadMethodCallException('Not implemented');
    }

    /**
     * Workaround because it is embedded in IParapheur::getSignature()
     *
     * @param $signature
     * @param string $documentId
     * @return ?Fichier
     */
    public function getBordereauFromSignature($signature, string $documentId = ''): ?Fichier
    {
        throw new BadMethodCallException('Not implemented');
    }

    /**
     * @param $dossierID
     */
    public function exercerDroitRemordDossier($dossierID)
    {
        throw new BadMethodCallException('Not implemented');
    }

    /**
     * @throws CryptoClientException
     * @throws ClientExceptionInterface
     */
    public function xadesGenerateDataToSign(string $filepath, string $certificate): string
    {
        $payload = [
            'city' => $this->collectiviteProperties->get('libersign_city'),
            'zipCode' => $this->collectiviteProperties->get('libersign_cp'),
            'country' => 'France',
            'claimedRoles' => ['Ordonnateur'],
        ];
        return $this->cryptoClient->xades()->generateDataToSign($filepath, $certificate, $payload);
    }

    /**
     * @throws ClientExceptionInterface
     * @throws CryptoClientException
     */
    public function xadesGenerateSignature(
        string $filepath,
        string $certificate,
        array $dataToSignList,
        string $signatureDateTime
    ): SignedFile {
        $payload = [
            'city' => $this->collectiviteProperties->get('libersign_city'),
            'zipCode' => $this->collectiviteProperties->get('libersign_cp'),
            'country' => 'France',
            'claimedRoles' => ['Ordonnateur'],
        ];
        return new SignedFile(
            $this->cryptoClient->xades()->generateSignature(
                $filepath,
                $certificate,
                $dataToSignList,
                $signatureDateTime,
                $payload
            ),
            'xml'
        );
    }

    /**
     * @throws CryptoClientException
     * @throws ClientExceptionInterface
     */
    public function cadesGenerateDataToSign(string $filepath, string $certificate): string
    {
        return $this->cryptoClient->cades()->generateDataToSign($filepath, $certificate);
    }

    /**
     * @throws ClientExceptionInterface
     * @throws CryptoClientException
     */
    public function cadesGenerateSignature(
        string $filepath,
        string $certificate,
        array $dataToSignList,
        string $signatureDateTime
    ): SignedFile {
        return new SignedFile(
            $this->cryptoClient->cades()->generateSignature(
                $filepath,
                $certificate,
                $dataToSignList,
                $signatureDateTime
            ),
            'pk7'
        );
    }

    /**
     * @throws CryptoClientException
     * @throws ClientExceptionInterface
     */
    public function padesGenerateDataToSign(string $filepath, string $certificate, string $signatory): string
    {
        return $this->cryptoClient->pades()->generateDataToSign(
            $filepath,
            $certificate,
            $this->getStamp($signatory, (string)(time() * 1000))
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws CryptoClientException
     */
    public function padesGenerateSignature(
        string $filepath,
        string $certificate,
        array $dataToSignList,
        string $signatureDateTime,
        string $signatory
    ): SignedFile {
        return new SignedFile(
            $this->cryptoClient->pades()->generateSignature(
                $filepath,
                $certificate,
                $dataToSignList,
                $signatureDateTime,
                $this->getStamp($signatory, $signatureDateTime)
            ),
            'pdf'
        );
    }

    /**
     * @throws CryptoClientException
     * @throws ClientExceptionInterface
     * @throws RecoverableException
     */
    public function generateDataToSign(
        string $filepath,
        string $certificate,
        string $signatory = ''
    ): string {
        if ($this->signatureType === self::LIBERSIGN_SIGNATURE_CADES) {
            return $this->cadesGenerateDataToSign($filepath, $certificate);
        }

        if ($this->signatureType === self::LIBERSIGN_SIGNATURE_XADES) {
            return $this->xadesGenerateDataToSign($filepath, $certificate);
        }

        if ($this->signatureType === self::LIBERSIGN_SIGNATURE_PADES) {
            return $this->padesGenerateDataToSign($filepath, $certificate, $signatory);
        }
        throw new \RecoverableException("Unknown signature type : " . $this->signatureType);
    }

    /**
     * @throws CryptoClientException
     * @throws ClientExceptionInterface
     * @throws RecoverableException
     */
    public function generateSignature(
        string $filepath,
        string $certificate,
        array $dataToSignList,
        string $signatureDateTime,
        string $signatory = 'Default'
    ): SignedFile {
        if ($this->signatureType === self::LIBERSIGN_SIGNATURE_CADES) {
            return $this->cadesGenerateSignature($filepath, $certificate, $dataToSignList, $signatureDateTime);
        }

        if ($this->signatureType === self::LIBERSIGN_SIGNATURE_XADES) {
            return $this->xadesGenerateSignature($filepath, $certificate, $dataToSignList, $signatureDateTime);
        }

        if ($this->signatureType === self::LIBERSIGN_SIGNATURE_PADES) {
            return $this->padesGenerateSignature($filepath, $certificate, $dataToSignList, $signatureDateTime, $signatory);
        }
        throw new \RecoverableException("Unknown signature type : " . $this->signatureType);
    }

    /**
     * In a perfect world, it would have its own class
     */
    private function getStamp(string $signatory, string $signatureDateTime): array
    {
        // It could probably be cleaner in PHP 8 with nullsafe operator
        $stampLocation = $this->getDataDir() . '/connector/libersign/default-stamp.png';
        if ($this->collectiviteProperties->get('libersign_stamp_image')) {
            $stampLocation = $this->collectiviteProperties->getFilePath('libersign_stamp_image');
        } elseif ($this->globalConnectorConfig !== null && $this->globalConnectorConfig->get('libersign_stamp_image')) {
            $stampLocation = $this->globalConnectorConfig->getFilePath('libersign_stamp_image');
        }
        $x = 400;
        if ($this->collectiviteProperties->get('libersign_x_position')) {
            $x = $this->collectiviteProperties->get('libersign_x_position');
        } elseif ($this->globalConnectorConfig !== null && $this->globalConnectorConfig->get('libersign_x_position')) {
            $x = $this->globalConnectorConfig->get('libersign_x_position');
        }
        $y = 0;
        if ($this->collectiviteProperties->get('libersign_y_position')) {
            $y = $this->collectiviteProperties->get('libersign_y_position');
        } elseif ($this->globalConnectorConfig !== null && $this->globalConnectorConfig->get('libersign_y_position')) {
            $y = $this->globalConnectorConfig->get('libersign_y_position');
        }

        return [
            'x' => $x,
            'y' => $y,
            'page' => 1,
            'width' => 200,
            'height' => 70,
            'elements' => [
                [
                    'type' => 'IMAGE',
                    'x' => 33,
                    'y' => 17,
                    'width' => 25,
                    'height' => 25,
                    'value' => base64_encode(file_get_contents($stampLocation))
                ],
                [
                    'type' => 'TEXT',
                    'x' => 70,
                    'y' => 14,
                    'colorCode' => '#000000',
                    'font' => 'HELVETICA_BOLD',
                    'fontSize' => 8,
                    'value' => implode(PHP_EOL, [$signatory, date('d/m/Y', (int)$signatureDateTime / 1000)]),
                ],
                [
                    'type' => 'IMAGE',
                    'x' => 62,
                    'y' => 12,
                    'width' => 2,
                    'height' => 24,
                    'value' => base64_encode(
                        file_get_contents($this->getDataDir() . '/connector/libersign/vertical-bar.png')
                    )
                ],
            ]
        ];
    }

    /**
     * @throws CryptoClientException
     * @throws ClientExceptionInterface
     */
    public function testConnection(): string
    {
        return $this->cryptoClient->version()->getVersion();
    }

    public function getRefusalMessage($dossierID)
    {
        return '';
    }
}
