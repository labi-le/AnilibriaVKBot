<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\AnilibriaService;

use Astaroth\Attribute\ClassAttribute\Conversation;
use Astaroth\Attribute\ClassAttribute\Event;
use Astaroth\Attribute\General\State;
use Astaroth\Attribute\Method\Message;
use Astaroth\Attribute\Method\MessageRegex;
use Astaroth\Commands\BaseCommands;
use Astaroth\DataFetcher\Events\MessageNew as Data;
use Astaroth\Enums\ConversationType;
use Astaroth\Enums\Events;
use Astaroth\Enums\MessageValidation;
use Astaroth\Support\Facades\Session;
use Astaroth\Support\Facades\State as StateFacade;
use Throwable;

#[Event(Events::MESSAGE_NEW)]
#[Conversation(ConversationType::PERSONAL)]
final class TextNavigation extends BaseCommands
{
    #[State(AnilibriaService::ANIME_SEARCH, ConversationType::ALL)]
    public function searchAnime(Data $data, string $anime_name = null): void
    {
        $result = AnilibriaService::searchTitle($anime_name ?? $data->getText());
        if ($result === null) {
            $this->message("Я ничево не смогла найти")->send();
        } else {
            $textTemplate = $result["template"];
            $this->message("Выбрать необходимое аниме можно просто отправив его code (вместе с #)\n\n" . $textTemplate)->send();
        }

        //for callback buttons
        StateFacade::remove($data->getPeerId(), AnilibriaService::ANIME_SEARCH);
    }

    #[State(AnilibriaService::SELECT_EPISODE, ConversationType::ALL)]
    public function selectEpisode(Data $data): void
    {
        $episode = (int)$data->getText();
        $code = (new Session($data->getPeerId(), AnilibriaService::SELECT_EPISODE))->get(AnilibriaService::CODE);
        if (AnilibriaService::selectEpisode($data, $code, $episode)) {
            StateFacade::remove($data->getPeerId(), AnilibriaService::SELECT_EPISODE);
        }
    }


    #[Message("поиск", MessageValidation::START_AS)]
    public function simplySearch(Data $data): void
    {
        $this->searchAnime($data, mb_substr($data->getText(), 6));
    }

    #[MessageRegex(AnilibriaService::STARTS_WITH_LATTICE)]
    public function playForCodename(MessageRegex $regex, Data $data)
    {
        try {
            AnilibriaService::animePreviewer($data, $regex[1]);
        } catch (Throwable) {
            $this->message("Аниме не найдено, возможно допущена опечатка");
        }
    }

}