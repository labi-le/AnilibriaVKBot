<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\AnilibriaService;
use Astaroth\Attribute\ClassAttribute\Conversation;
use Astaroth\Attribute\ClassAttribute\Event;
use Astaroth\Attribute\Method\Debug;
use Astaroth\Attribute\Method\Message;
use Astaroth\Commands\BaseCommands;
use Astaroth\Enums\ConversationType;
use Astaroth\Enums\Events;
use Astaroth\VkKeyboard\Contracts\Keyboard\Button\FactoryInterface;
use Astaroth\VkKeyboard\Facade;
use Astaroth\VkKeyboard\Object\Keyboard\Button\Text;
use Throwable;

#[Event(Events::MESSAGE_NEW)]
#[Conversation(ConversationType::PERSONAL)]
final class StartWizard extends BaseCommands
{
    /**
     * @throws Throwable
     */
    #[
        Message("меню"), Message("начать"), Message("старт"), Message("оняме"),
        Message("/start"), Message("помощь"), Message("help"), Message("хелп")
    ]
    public function getStarted(): void
    {
        $keyboard = Facade::createKeyboardBasic(static function (FactoryInterface $factory) {
            return [
                [$factory->callback("Поиск", [AnilibriaService::MENU => AnilibriaService::ANIME_SEARCH], Text::COLOR_GRAY)],
                [$factory->callback('Случайное аниме', [AnilibriaService::MENU => AnilibriaService::ANIME_RANDOM], Text::COLOR_BLUE)],
            ];
        }, false);

        $this
            ->message("Приветик %full-name\nВоспользуйся кнопками и они тебя наверняка приведут к успеху! 🥰")
            ->keyboard($keyboard)
            ->send();
    }
}