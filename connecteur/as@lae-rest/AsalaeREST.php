<?php

declare(strict_types=1);

use Monolog\Logger;
use Pastell\Helpers\SedaHelper;
use Pastell\Seda\AsalaeSedaHelper;

class AsalaeREST extends SAEConnecteur
{
    private string $url;
    private string $login;
    private string $password;
    private int $chunk_size_in_bytes;
    private AsalaeSedaHelper $sedaHelper;

    public function __construct(
        private readonly CurlWrapperFactory $curlWrapperFactory,
        private readonly Logger $logger,
    ) {
    }

    public function setConnecteurConfig(DonneesFormulaire $donneesFormulaire)
    {
        $this->url = $donneesFormulaire->get('url');
        $this->login = $donneesFormulaire->get('login');
        $this->password = $donneesFormulaire->get('password');
        $this->chunk_size_in_bytes = (int)$donneesFormulaire->get('chunk_size_in_bytes');
        $this->sedaHelper = new AsalaeSedaHelper();
    }

    public function sendSIP(string $bordereau, string $archivePath): string
    {
        $tmpFile = new TmpFile();
        $bordereau_file = $tmpFile->create();
        file_put_contents($bordereau_file, $bordereau);

        try {
            if ($this->chunk_size_in_bytes && filesize($archivePath) > $this->chunk_size_in_bytes) {
                $this->sendArchiveByChunk($bordereau_file, $archivePath);
            } else {
                $this->callSedaMessage($bordereau_file, $archivePath);
            }
        } finally {
            $tmpFile->delete($bordereau_file);
        }

        return $this->getTransferId($bordereau);
    }

    private function getTransferId(string $bordereau): string
    {
        $xml = \simplexml_load_string($bordereau);
        return (string)($xml->TransferIdentifier ?? $xml->MessageIdentifier);
    }

    /**
     * @return bool|mixed
     * @throws Exception
     */
    public function callSedaMessage($seda_message_path, $attachments_path, bool $send_chunked_attachments = false)
    {
        $curlWrapper = $this->curlWrapperFactory->getInstance();
        $curlWrapper->addPostFile('seda_message', $seda_message_path, 'bordereau.xml');
        if ($attachments_path) {
            $curlWrapper->addPostFile('attachments', $attachments_path, basename($attachments_path));
        }
        if ($send_chunked_attachments) {
            $curlWrapper->addPostData('send_chunked_attachments', 'true');
        }
        return $this->getWS('/sedaMessages', "application/json", $curlWrapper);
    }

    /**
     * @throws Exception
     */
    private function sendArchiveByChunk($seda_message_path, $attachments_path): void
    {
        $this->logger->debug('Sending seda message by chunk');
        $seda_message_result = $this->callSedaMessage($seda_message_path, false, true);

        if (empty($seda_message_result['chunk_session_identifier']) || empty($seda_message_result['chunk_security_identifier'])) {
            throw new RuntimeException("Cette version d'asalae ne permet pas l'envoi d'archive par morceaux");
        }

        $this->logger->debug('Results of /sedaMessage call : ', $seda_message_result);
        $tmpFolder = new TmpFolder();
        $temp_dir = $tmpFolder->create();
        $temp_file_path = $temp_dir . '/' . basename($attachments_path);
        if (!copy($attachments_path, $temp_file_path)) {
            throw new RuntimeException("Unable to copy $attachments_path to $temp_dir");
        }

        $splitFile = new SplitFile($this->logger);
        $chunk_part_list = $splitFile->split($temp_file_path, $this->chunk_size_in_bytes, 'archive_part');

        try {
            foreach ($chunk_part_list as $chunk_index => $chunk_part) {
                $this->sedaAttachmentsChunkFiles(
                    $seda_message_result['chunk_session_identifier'],
                    $seda_message_result['chunk_security_identifier'],
                    basename($attachments_path),
                    count($chunk_part_list),
                    $chunk_index,
                    $temp_dir . '/' . $chunk_part
                );
            }
        } finally {
            $tmpFolder->delete($temp_dir);
        }
    }

    /**
     * @return bool|mixed
     * @throws Exception
     */
    private function sedaAttachmentsChunkFiles(
        $session_identifier,
        $security_identifier,
        $filename,
        $number_of_chunks,
        $chunk_index,
        $chunk_file_path
    ) {
        $curlWrapper = $this->curlWrapperFactory->getInstance();
        $curlWrapper->addPostFile('chunk_content', $chunk_file_path);

        $post_data = [
            'session_identifier' => $session_identifier,
            'security_identifier' => $security_identifier,
            'number_of_files' => 1,
            'file_index' => 1,
            'file_name' => $filename,
            'compression_algorithm' => 'TARGZ',
            'number_of_chunks' => $number_of_chunks,
            'chunk_index' => $chunk_index + 1,
            //  'chunk_content' => @$chunk_file_path
        ];


        foreach ($post_data as $name => $value) {
            $curlWrapper->addPostData($name, $value);
        }

        $this->logger->debug("Data send with sedaAttachmentsChunkFiles", $post_data);

        return $this->getWS('/sedaAttachmentsChunkFiles', "application/json", $curlWrapper);
    }

    public function provideAcknowledgment(): bool
    {
        return true;
    }

    public function getAck(string $transfertId, string $originatingAgencyId): string
    {
        if (!$transfertId) {
            throw new UnrecoverableException("L'identifiant du transfert n'a pas été trouvé");
        }
        return $this->getWS(
            urlencode(
                sprintf(
                    '/sedaMessages/sequence:ArchiveTransfer/message:Acknowledgement/originOrganizationIdentification:%s/originMessageIdentifier:%s',
                    $originatingAgencyId,
                    $transfertId
                )
            ),
            'application/xml'
        );
    }

    public function getAtr(string $transfertId, string $originatingAgencyId): string
    {
        if (!$transfertId) {
            throw new UnrecoverableException("L'identifiant du transfert n'a pas été trouvé");
        }

        return $this->getWS(
            urlencode(
                sprintf(
                    '/sedaMessages/sequence:ArchiveTransfer/message:ArchiveTransferReply/originOrganizationIdentification:%s/originMessageIdentifier:%s',
                    $originatingAgencyId,
                    $transfertId
                )
            ),
            'application/xml'
        );
    }

    /**
     * @return bool|mixed
     * @throws Exception
     */
    private function getWS(string $url, string $accept = 'application/json', CurlWrapper $curlWrapper = null)
    {
        if (!$curlWrapper) {
            $curlWrapper = $this->curlWrapperFactory->getInstance();
        }
        $curlWrapper->httpAuthentication($this->login, hash('sha256', $this->password));

        //see : http://stackoverflow.com/a/19250636
        $curlWrapper->addHeader("Expect", "");
        $curlWrapper->addHeader("Accept", $accept);

        $curlWrapper->dontVerifySSLCACert();
        $result = $curlWrapper->get($this->url . $url);
        if (!$result) {
            throw new Exception($curlWrapper->getLastError());
        }
        $http_code = $curlWrapper->getHTTPCode();
        if ($http_code != 200) {
            throw new Exception("$result - code d'erreur HTTP : $http_code");
        }
        $old_result = $result;
        if ($accept === "application/json") {
            $result = json_decode($result, true);
        }
        if (!$result) {
            throw new Exception(
                "Le serveur asalae n'a pas renvoyé une réponse compréhensible - problème de configuration ? : $old_result"
            );
        }

        return $result;
    }

    /**
     * @return bool|mixed
     * @throws Exception
     */
    public function getVersion()
    {
        return $this->getWS('/versions');
    }

    /**
     * @return bool|mixed
     * @throws Exception
     */
    public function ping()
    {
        return $this->getWS('/ping');
    }

    public function getSedaHelper(): SedaHelper
    {
        return $this->sedaHelper;
    }
}
