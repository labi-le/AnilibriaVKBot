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
final class Menu extends BaseCommands
{
    /**
     * @throws \Throwable
     */
    #[Message("меню", Message::START_AS)] #[Message("начать", Message::START_AS)]
    #[Message("старт", Message::START_AS)] #[Message("оняме", Message::START_AS)]
    #[Message("/start", Message::START_AS)]
    public function getStarted(Data $data, Create $create): void
    {
        $keyboard = Facade::createKeyboardBasic(function (FactoryInterface $factory) {
            return [
                [$factory->callback("Поиск", [AnilibriaService::MENU => AnilibriaService::ANIME_SEARCH], Text::COLOR_GRAY)],
                [$factory->callback('Случайное аниме', [AnilibriaService::MENU => AnilibriaService::ANIME_RANDOM], Text::COLOR_BLUE)],
            ];
        }, false);

        $create(
            (new \Astaroth\VkUtils\Builders\Message())
                ->setPeerId($data->getPeerId())
                ->setMessage("приветик %@name")
                ->setKeyboard($keyboard)
        );
    }
}