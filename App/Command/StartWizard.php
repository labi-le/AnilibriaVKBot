<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\AnilibriaService;
use Astaroth\Attribute\Conversation;
use Astaroth\Attribute\Event\MessageNew;
use Astaroth\Attribute\Message;
use Astaroth\Commands\BaseCommands;
use Astaroth\DataFetcher\Events\MessageNew as Data;
use Astaroth\Support\Facades\Create;
use Astaroth\VkKeyboard\Contracts\Keyboard\Button\FactoryInterface;
use Astaroth\VkKeyboard\Facade;
use Astaroth\VkKeyboard\Object\Keyboard\Button\Text;

#[Conversation(Conversation::PERSONAL_DIALOG)]
#[MessageNew]
final class StartWizard extends BaseCommands
{
    /**
     * @throws \Throwable
     */
    #[
        Message("меню"), Message("начать"), Message("старт"), Message("оняме"),
        Message("/start"), Message("помощь"), Message("help"), Message("хелп")
    ]
    public function getStarted(): void
    {
        $keyboard = Facade::createKeyboardBasic(function (FactoryInterface $factory) {
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