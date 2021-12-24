<?php

declare(strict_types=1);

namespace App\Command;

use Astaroth\Attribute\ClassAttribute\Conversation;
use Astaroth\Attribute\ClassAttribute\Event;
use Astaroth\Attribute\Method\ClientInfo;
use Astaroth\Commands\BaseCommands;
use Astaroth\Enums\ConversationType;
use Astaroth\Enums\Events;

#[Event(Events::MESSAGE_NEW)]
#[Conversation(ConversationType::PERSONAL)]
class Compatibility extends BaseCommands
{
    #[ClientInfo([], keyboard: false, inline_keyboard: false, carousel: false)]
    public function keyboardIncompatability(): bool
    {
        $this->message("Твой клиент не поддерживает современные функции вконтакте, обновись!");
        return false;
    }
}