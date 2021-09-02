<?php

namespace App\Command;

use App\Service\AnilibriaService;
use Astaroth\Attribute\ClientInfo;
use Astaroth\Attribute\Conversation;
use Astaroth\Attribute\Event\MessageNew;
use Astaroth\Attribute\Message;
use Astaroth\Commands\BaseCommands;
use Astaroth\DataFetcher\Events\MessageNew as Data;
use Astaroth\Foundation\Session;
use Astaroth\TextMatcher;

#[Conversation(Conversation::PERSONAL_DIALOG)]
#[MessageNew]
final class Anime extends BaseCommands
{

    #[ClientInfo([], keyboard: false, inline_keyboard: false, carousel: false)]
    public function keyboardIncompatability(Data $data)
    {
        $this->message($data->getPeerId(), "Твой клиент не поддерживает современные функции вконтакте, обновись!");

        return false;
    }


    /**
     * Поиск аниме
     * Выбор эпизодов
     *
     * @param Data $data
     * @param string|null $needle
     * @throws \Throwable
     */
    #[Message("", TextMatcher::CONTAINS)]
    public function search(Data $data, string $needle = null): void
    {
        [$animeSearchSession, $selectEpisodeSession] =
            [
                new Session($data->getPeerId(), AnilibriaService::ANIME_SEARCH),
                new Session($data->getPeerId(), AnilibriaService::SELECT_EPISODE)
            ];

        if ($selectEpisodeSession->get(AnilibriaService::SELECTED) === false) {
            AnilibriaService::selectEpisode($data, $selectEpisodeSession);
        }

        if ($animeSearchSession->get(AnilibriaService::ENABLED) === true) {
            AnilibriaService::searchTitle($data, $animeSearchSession, $needle);
            return;
        }

        if ($animeSearchSession->get(AnilibriaService::ENABLED) === false) {
            AnilibriaService::select($data, $animeSearchSession, AnilibriaService::FOUND);
        }
    }


    #[Message("поиск", TextMatcher::START_AS)]
    public function simplySearch(Data $data)
    {
        (new Session($data->getPeerId(), AnilibriaService::ANIME_SEARCH))
            ->put(AnilibriaService::ENABLED, true);

        $this->search($data, mb_substr($data->getText(), 6));
    }

}