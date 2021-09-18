<?php

namespace App\Command;

use App\Service\AnilibriaService;
use Astaroth\Attribute\Conversation;
use Astaroth\Attribute\Event\MessageNew;
use Astaroth\Attribute\Message;
use Astaroth\Attribute\State;
use Astaroth\Commands\BaseCommands;
use Astaroth\DataFetcher\Events\MessageNew as Data;
use Astaroth\Support\Facades\Create;
use Astaroth\Support\Facades\Session;
use Astaroth\TextMatcher;

#[Conversation(Conversation::PERSONAL_DIALOG)]
#[MessageNew]
final class TextNavigation extends BaseCommands
{

    #[State(AnilibriaService::FOUND, State::PEER)]
    public function selectAnimeFromFoundList(Data $data): void
    {
        $session = new Session($data->getPeerId(), AnilibriaService::ANIME_SEARCH);
        $number = (int)$data->getText();

        $foundList = $session->get(AnilibriaService::FOUND);
        $anime = $foundList[$number] ?? null;

        if ($anime === null) {
            AnilibriaService::notice($data, AnilibriaService::WRONG_SELECTED_NOTICE);
        } else {
            AnilibriaService::animePreviewer($data, $anime[AnilibriaService::CODE]);
            \Astaroth\Support\Facades\State::remove($data->getPeerId(), AnilibriaService::FOUND);
            $session->purge(true);
        }
    }

    #[State(AnilibriaService::ANIME_SEARCH, State::PEER)]
    public function searchAnime(Data $data, string $anime_name = null): void
    {
        $session = new Session($data->getPeerId(), AnilibriaService::ANIME_SEARCH);

        $result = AnilibriaService::searchTitle($anime_name ?? $data->getText());
        if ($result === null) {
            $this->message($data->getPeerId(), "Я ничево не смогла найти");
            return;
        }

        $listFoundAnime = $result[AnilibriaService::FOUND];
        $textTemplate = $result["template"];

        $session->put(AnilibriaService::FOUND, $listFoundAnime);

        Create::new(
            (new \Astaroth\VkUtils\Builders\Message())
                ->setPeerId($data->getPeerId())
                ->setMessage("Всё что я смогла найти, выбрать нужное можно просто отправив цифру\n\n" . $textTemplate)
        );

        \Astaroth\Support\Facades\State::add($data->getPeerId(), AnilibriaService::FOUND);
        \Astaroth\Support\Facades\State::remove($data->getPeerId(), AnilibriaService::ANIME_SEARCH);
    }

    #[State(AnilibriaService::SELECT_EPISODE, State::PEER)]
    public function selectEpisode(Data $data): void
    {
        if (preg_match(AnilibriaService::REGEX_INTEGER, $data->getText(), $matches)) {
            $episode = current($matches);
            $code = (new Session($data->getPeerId(), AnilibriaService::SELECT_EPISODE))->get(AnilibriaService::CODE);
            if (AnilibriaService::selectEpisode($data, $code, $episode)) {
                \Astaroth\Support\Facades\State::remove($data->getPeerId(), AnilibriaService::SELECT_EPISODE);
            }
        }
    }


    #[Message("поиск", TextMatcher::START_AS)]
    public function simplySearch(Data $data): void
    {
        \Astaroth\Support\Facades\State::add($data->getPeerId(), AnilibriaService::ANIME_SEARCH);
        $this->searchAnime($data, mb_substr($data->getText(), 6));
    }

}