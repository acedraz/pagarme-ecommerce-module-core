<?php

namespace Mundipagg\Core\Kernel\I18N;

use Mundipagg\Core\Kernel\Abstractions\AbstractI18NTable;

class PTBR extends AbstractI18NTable
{
    protected function getTable()
    {
        return [
            'Invoice created: #%d.' => 'Nota fiscal criada: #%d',
            'Webhook received: %s.%s' => 'Webhook recebido: %s.%s',
            'Order paid.' => 'Pedido pago.',
        ];
    }
}