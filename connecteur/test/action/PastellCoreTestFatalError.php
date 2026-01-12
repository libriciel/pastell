<?php

declare(strict_types=1);

class PastellCoreTestFatalError extends ActionExecutor
{
    public function go(): void
    {
        throw new Error('Fatal error');
    }
}
