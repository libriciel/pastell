<?php

abstract class MailsecConnecteur extends Connecteur
{
    public const string CONNECTEUR_TYPE_ID = 'mailsec';

    /**
     * @throws Exception
     * @throws Throwable
     */
    abstract public function sendAllMail(int $id_e, string $id_d): void;

    /**
     * @throws Exception
     * @throws Throwable
     */
    abstract public function resendUnopenedEmails(int $id_e, string $id_d): void;

    /**
     * @throws Exception
     * @throws Throwable
     */
    abstract public function sendOneMail(int $id_e, string $id_d, int $id_de): void;

    /**
     * Synchronises the recipients' status in DocumentEmail with the sending service.
     * @throws Throwable
     */
    abstract public function updateStatus(int $id_e, string $id_d): void;

    /**
     * @throws Throwable
     */
    abstract public function updateReceipt(string $id_d): void;

    abstract public function mustRelance(string $first_mail_date): bool;

    abstract public function mustGoToNextState(string $first_mail_date): bool;

    abstract public function getDateRelance(string $first_mail_date): string;

    abstract public function getDateNextState(string $first_mail_date): string;
}
