<?php

class UndeliveredMail extends Connecteur
{
    public const PASTELL_RETURN_INFO_HEADER = "X-PASTELL-DOCUMENT";
    public const CONNECTOR_TYPE = 'UndeliveredMail';

    /** @var  DonneesFormulaire */
    private $connecteurConfig;

    /** @var DocumentEmail  */
    private $documentEmail;

    private $journal;

    public function __construct(DocumentEmail $documentEmail, Journal $journal)
    {
        $this->documentEmail = $documentEmail;
        $this->journal = $journal;
    }

    public function setConnecteurConfig(DonneesFormulaire $donneesFormulaire)
    {
        $this->connecteurConfig = $donneesFormulaire;
    }

    public function testConnexion()
    {
        $imapWrapper = $this->getNewImapWrapper();
        return $this->openImapWrapper($imapWrapper);
    }

    public function getReturnPath()
    {
        return $this->connecteurConfig->get('return_path');
    }

    public function listMail()
    {
        return $this->processMail(false);
    }

    public function processMail($process = true)
    {
        $imapWrapper = $this->getNewImapWrapper();
        $this->openImapWrapper($imapWrapper);

        $nb_message = $imapWrapper->getNbMessage();

        $overview = $imapWrapper->fetchOverview(1, $nb_message);

        $result = [];
        foreach ($overview as $data) {
            $line_result = [
                'uid' => $data->uid,
                'subject' => get_hecho($this->decodeHeader($data->subject)),
                'from' => get_hecho($this->decodeHeader($data->from)),
                'body' => false,
                'pastell_header' => false,
                'id_de' => false,
            ];
            $body = $imapWrapper->getBody($data->uid);
            $line_result['is_pastell_return'] = preg_match("#" . self::PASTELL_RETURN_INFO_HEADER . ": (.*)$#m", $body, $matches);
            if ($line_result['is_pastell_return']) {
                $line_result['body'] = $body;
                $line_result['pastell_header'] = trim($matches[1]);
                $document_email_info = $this->documentEmail->getInfoFromKey($line_result['pastell_header']);
                $line_result['id_de'] = $document_email_info['id_de'];
                if ($process) {
                    $this->documentEmail->addError($document_email_info['id_de'], $body);
                    $id_e = $this->documentEmail->getId_e($document_email_info['id_d']);
                    $this->journal->add(
                        Journal::MAIL_SECURISE,
                        $id_e,
                        $document_email_info['id_d'],
                        'error-email',
                        "Un mail a été reçu sur l'adresse return-path correspondant à l'email sécurisé id_de={$document_email_info['id_de']}." .
                        "Il est donc possible que ce mail ne soit pas arrivé à destination"
                    );
                }
            }
            $result[] = $line_result;
            if ($process) {
                $imapWrapper->markDeleted($data->uid);
            }
        }
        if ($process) {
            $imapWrapper->expunge();
        }
        return $result;
    }


    private function getNewImapWrapper()
    {
        $imapWrapper = new ImapWrapper(
            $this->connecteurConfig->get('imap_server'),
            $this->connecteurConfig->get('imap_login') ?: $this->connecteurConfig->get('return_path'),
            $this->connecteurConfig->get('imap_password')
        );
        $imapWrapper->setOption(
            $this->connecteurConfig->get(
                'imap_option'
            ) ?: ImapWrapper::DEFAULT_OPTION
        );
        $imapWrapper->setPort($this->connecteurConfig->get('imap_port'));
        $imapWrapper->setMailBox($this->connecteurConfig->get('imap_mailbox'));
        return $imapWrapper;
    }

    public function openImapWrapper(ImapWrapper $imapWrapper)
    {
        return $imapWrapper->open();
    }


    private function decodeHeader($value)
    {
        $decodedParts = imap_mime_header_decode($value);
        $result = "";
        foreach ($decodedParts as $part) {
            if ($part->charset == 'UTF-8') {
                $result  .= mb_convert_encoding($part->text, 'ISO-8859-1');
            } else {
                $result  .= $part->text;
            }
        }
        return $result;
    }
}
