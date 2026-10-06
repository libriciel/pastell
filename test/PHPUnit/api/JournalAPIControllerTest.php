<?php

class JournalAPIControllerTest extends PastellTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->getJournalEntryService()->add(JournalEventType::TEST, 0, '', 'test', 'Test');
    }

    public function testList(): void
    {
        $info = $this->getInternalAPI()->get('/journal');
        static::assertSame(
            [
                'id_j' => '1',
                'type' => '11',
                'id_e' => '0',
                'id_u' => '1',
                'id_d' => '0',
                'action' => 'test',
                'message' => 'Test',
                'date' => $info[0]['date'],
                'date_horodatage' => '0000-00-00 00:00:00',
                'message_horodate' => $info[0]['message_horodate'],
                'document_type' => '',
                'titre' => null,
                'denomination' => null,
                'nom' => 'Pommateau',
                'prenom' => 'Eric',
                'siren' => null,
                'document_type_libelle' => '',
                'action_libelle' => 'test',
            ],
            $info[0]
        );
    }

    public function testCSV(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Exit called with code 0');
        $this->expectOutputRegex('#Test#');
        $this->getInternalAPI()->get('/journal?format=csv&csv_entete_colonne=1');
    }

    public function testV1()
    {
        $this->expectOutputRegex('#Test#');
        $this->getV1('journal.php');
    }

    public function testDetail(): void
    {
        $info = $this->getInternalAPI()->get('/journal/1');
        static::assertSame(
            [
                'id_j' => '1',
                'type' => '11',
                'id_e' => '0',
                'id_u' => '1',
                'id_d' => '0',
                'action' => 'test',
                'message' => 'Test',
                'date' => $info['date'],
                'preuve' => '',
                'date_horodatage' => '0000-00-00 00:00:00',
                'message_horodate' => $info[ 'message_horodate'],
                'document_type' => '',
                'titre' => null,
                'denomination' => null,
                'nom' => 'Pommateau',
                'prenom' => 'Eric',
                'document_type_libelle' => '',
                'action_libelle' => 'test',
            ],
            $info
        );
    }

    public function testDetailFailed(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage("L'événement 42 n'a pas été trouvé");
        $this->getInternalAPI()->get('/journal/42');
    }

    public function testJeton(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Exit called with code 0');
        $this->expectOutputRegex('#pastell-journal-preuve-1.tsr#');
        $this->getInternalAPI()->get('/journal/1/jeton');
    }

    public function testPreuveFailed(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Ressource foo non trouvée');
        $this->getInternalAPI()->get('/journal/1/foo');
    }
}
