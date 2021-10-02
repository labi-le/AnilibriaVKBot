<?php

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

#[Conversation(Conversation::PERSONAL_DIALOG)]
#[MessageNew]
final class TextNavigation extends BaseCommands
{
    #[State(AnilibriaService::ANIME_SEARCH, State::PEER)]
    public function searchAnime(Data $data, string $anime_name = null): void
    {
        $result = AnilibriaService::searchTitle($anime_name ?? $data->getText());
        if ($result === null) {
            $this->message($data->getPeerId(), "Я ничево не смогла найти");
        } else {
            $textTemplate = $result["template"];

            Create::new(
                (new \Astaroth\VkUtils\Builders\Message())
                    ->setPeerId($data->getPeerId())
                    ->setMessage("Выбрать необходимое аниме можно просто отправив его code (вместе с #)\n\n" . $textTemplate)
            );
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


    #[Message("поиск", TextMatcher::START_AS)]
    public function simplySearch(Data $data): void
    {
        $this->searchAnime($data, mb_substr($data->getText(), 6));
    }

    #[MessageRegex("/#(.*)/u")]
    public function playForCodename(Data $data)
    {
        preg_match("/#(.*)/u", $data->getText(), $matches);
        unset($matches[0]);

        try {
            AnilibriaService::animePreviewer($data, $matches[1]);
        } catch (\Throwable) {
            $this->message($data->getPeerId(), "Аниме не найдено, возможно допущена опечатка");
        }
    }

}