<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\AnilibriaService;
use Astaroth\Attribute\Conversation;
use Astaroth\Attribute\Event\MessageNew;
use Astaroth\Attribute\Message;
use Astaroth\Attribute\MessageRegex;
use Astaroth\Attribute\State;
use Astaroth\Commands\BaseCommands;
use Astaroth\DataFetcher\Events\MessageNew as Data;
use Astaroth\Support\Facades\Create;
use Astaroth\Support\Facades\Session;
use Astaroth\TextMatcher;
use Astaroth\Support\Facades\State as StateFacade;
use Throwable;

#[Conversation(Conversation::PERSONAL_DIALOG)]
#[MessageNew]
final class TextNavigation extends BaseCommands
{
    #[State(AnilibriaService::ANIME_SEARCH, State::PEER)]
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

    #[State(AnilibriaService::SELECT_EPISODE, State::PEER)]
    public function selectEpisode(Data $data): void
    {
        $episode = (int)$data->getText();
        $code = (new Session($data->getPeerId(), AnilibriaService::SELECT_EPISODE))->get(AnilibriaService::CODE);
        if (AnilibriaService::selectEpisode($data, $code, $episode)) {
            StateFacade::remove($data->getPeerId(), AnilibriaService::SELECT_EPISODE);
        }
    }


    #[Message("поиск", Message::START_AS)]
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