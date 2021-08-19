<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\AnilibriaService;
use Astaroth\Attribute\Conversation;
use Astaroth\Attribute\Event\MessageNew;
use Astaroth\Attribute\Message;
use Astaroth\Commands\BaseCommands;
use Astaroth\DataFetcher\Events\MessageNew as Data;
use Astaroth\Support\Facades\BuilderFacade;
use Astaroth\TextMatcher;
use Astaroth\VkKeyboard\Contracts\Keyboard\Button\FactoryInterface;
use Astaroth\VkKeyboard\Facade;
use Astaroth\VkKeyboard\Object\Keyboard\Button\Text;

#[Conversation(Conversation::PERSONAL_DIALOG)]
#[MessageNew]
final class Menu extends BaseCommands
{
    #[Message("меню", TextMatcher::START_AS)] #[Message("начать", TextMatcher::START_AS)]
    #[Message("старт", TextMatcher::START_AS)] #[Message("оняме", TextMatcher::START_AS)]
    #[Message("/start", TextMatcher::START_AS)]
    public function getStarted(Data $data): void
    {
        $keyboard = Facade::createKeyboardBasic(function (FactoryInterface $factory) {
            return [
                [$factory->callback("Поиск", [AnilibriaService::MENU => AnilibriaService::ANIME_SEARCH], Text::COLOR_GRAY)],
                [$factory->callback('Случайное аниме', [AnilibriaService::MENU => AnilibriaService::ANIME_RANDOM], Text::COLOR_BLUE)],
            ];
        }, false);

        BuilderFacade::create(
            (new \Astaroth\VkUtils\Builders\Message())
                ->setPeerId($data->getPeerId())
                ->setMessage("приветик %@name")
                ->setKeyboard($keyboard)
        );
    }
}