<?php

declare(strict_types=1);

class JournalControlerTest extends ControlerTestCase
{
    private JournalControler $journalControler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->journalControler = $this->getControlerInstance(JournalControler::class);
    }

    /**
     * @throws Exception
     */
    public function testIndexActionWithDeletedUserRemainsAccessible(): void
    {
        $this->authenticateNewUserWithPermission(['journal:lecture']);
        $_GET = ['id_u' => 999999];

        $this->expectOutputRegex('#id_u=999999#');
        $this->journalControler->indexAction();
    }
}
