<?php

declare(strict_types=1);

class PastellCoreTestFatalError extends ActionExecutor
{
    /**
     * @throws UnrecoverableException
     */
    public function go(): void
    {
        throw new UnrecoverableException('Fatal error');
    }
}
