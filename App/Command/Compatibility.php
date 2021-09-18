<?php

declare(strict_types=1);

namespace App\Command;

use Astaroth\Attribute\ClientInfo;
use Astaroth\Attribute\Conversation;
use Astaroth\Attribute\Event\MessageNew;
use Astaroth\Commands\BaseCommands;
use Astaroth\DataFetcher\Events\MessageNew as Data;

#[Conversation(Conversation::PERSONAL_DIALOG)]
#[MessageNew]
class Compatibility extends BaseCommands
{
    #[ClientInfo([], keyboard: false, inline_keyboard: false, carousel: false)]
    public function keyboardIncompatability(Data $data): bool
    {
        $this->message($data->getPeerId(), "Твой клиент не поддерживает современные функции вконтакте, обновись!");
        return false;
    }
}