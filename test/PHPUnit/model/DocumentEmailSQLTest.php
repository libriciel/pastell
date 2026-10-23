<?php

declare(strict_types=1);

use Pastell\Service\Document\DocumentEmailRecipientType;

class DocumentEmailSQLTest extends PastellTestCase
{
    private DocumentEmailSQL $documentEmailSQL;
    private string $id_d;

    protected function setUp(): void
    {
        parent::setUp();
        $this->documentEmailSQL = $this->getObjectInstancier()->getInstance(DocumentEmailSQL::class);
        $this->id_d = $this->createDocument('test')['id_d'];
    }

    public function testGetByKey(): void
    {
        $this->documentEmailSQL->insert($this->id_d, 'foo@example.org', 'KEY', DocumentEmailRecipientType::CC->value);
        static::assertSame('KEY', $this->documentEmailSQL->getKey($this->id_d, 'foo@example.org'));
        $info = $this->documentEmailSQL->getByKey('KEY');
        static::assertSame('cc', $info['type_destinataire']);
        static::assertSame($info, $this->documentEmailSQL->getById($info['id_de']));
    }

    public function testUnknownReturnsNull(): void
    {
        static::assertNull($this->documentEmailSQL->getKey($this->id_d, 'foo@example.org'));
        static::assertNull($this->documentEmailSQL->getByKey('KEY'));
        static::assertNull($this->documentEmailSQL->getById(9999));
    }

    public function testRead(): void
    {
        $this->documentEmailSQL->insert($this->id_d, 'a@example.org', 'KEY1', DocumentEmailRecipientType::TO->value);
        $this->documentEmailSQL->insert($this->id_d, 'b@example.org', 'KEY2', DocumentEmailRecipientType::TO->value);
        static::assertSame(0, $this->documentEmailSQL->countRead($this->id_d));

        $this->documentEmailSQL->setRead('KEY1');
        static::assertSame(1, $this->documentEmailSQL->countRead($this->id_d));
        static::assertFalse($this->documentEmailSQL->isAllRead($this->id_d));

        $this->documentEmailSQL->setRead('KEY2');
        static::assertTrue($this->documentEmailSQL->isAllRead($this->id_d));
    }

    public function testIsAllInError(): void
    {
        $this->documentEmailSQL->insert($this->id_d, 'a@example.org', 'KEY1', DocumentEmailRecipientType::TO->value);
        $this->documentEmailSQL->insert($this->id_d, 'b@example.org', 'KEY2', DocumentEmailRecipientType::TO->value);
        [$id_de1, $id_de2] = $this->documentEmailSQL->getAllRecipientIds($this->id_d);

        $this->documentEmailSQL->setError($id_de1, 'error');
        static::assertFalse($this->documentEmailSQL->isAllInError($this->id_d));
        $this->documentEmailSQL->setError($id_de2, 'error');

        static::assertSame('error', $this->documentEmailSQL->getById($id_de1)['last_error']);
        static::assertTrue($this->documentEmailSQL->isAllInError($this->id_d));
    }

    public function testSetNotReceived(): void
    {
        $this->documentEmailSQL->insert($this->id_d, 'a@example.org', 'KEY1', DocumentEmailRecipientType::TO->value);
        $this->documentEmailSQL->insert($this->id_d, 'b@example.org', 'KEY2', DocumentEmailRecipientType::TO->value);
        $this->documentEmailSQL->setRead('KEY1');

        $this->documentEmailSQL->setNotReceived($this->id_d);

        static::assertSame(0, $this->documentEmailSQL->getByKey('KEY1')['non_recu']);
        static::assertSame(1, $this->documentEmailSQL->getByKey('KEY2')['non_recu']);
    }

    public function testIncrementResend(): void
    {
        $this->documentEmailSQL->insert($this->id_d, 'a@example.org', 'KEY', DocumentEmailRecipientType::TO->value);
        $id_de = $this->documentEmailSQL->getByKey('KEY')['id_de'];

        $this->documentEmailSQL->incrementResend($id_de);

        static::assertSame(1, $this->documentEmailSQL->getById($id_de)['nb_renvoi']);
    }
}
