<?php

namespace App\Command;

use App\Service\AnilibriaService;
use Astaroth\Anilibria\Method;
use Astaroth\Attribute\Conversation;
use Astaroth\Attribute\Event\MessageEvent;
use Astaroth\Attribute\Payload;
use Astaroth\Commands\BaseCommands;
use Astaroth\DataFetcher\Events\MessageEvent as Data;
use Astaroth\Foundation\Session;
use Astaroth\Support\Facades\RequestFacade;
use Astaroth\Support\Facades\UploaderFacade;
use Astaroth\VkUtils\Builders\Attachments\Message\PhotoMessages;

#[MessageEvent]
#[Conversation(Conversation::PERSONAL_DIALOG)]
final class Player extends BaseCommands
{
    #[Payload([AnilibriaService::MENU => AnilibriaService::ANIME_SEARCH])]
    public function searchAnimeButton(Data $data): void
    {
        (new Session($data->getPeerId(), AnilibriaService::ANIME_SEARCH))
            ->put(AnilibriaService::ENABLED, true);

        RequestFacade::request("messages.sendMessageEventAnswer",
            [
                "event_id" => $data->getEventId(),
                "user_id" => $data->getUserId(),
                "peer_id" => $data->getPeerId(),
                "event_data" => json_encode(
                    [
                        "type" => "show_snackbar",
                        "text" => "Напиши аниме которое ты хочешь посмотреть!"
                    ]
                )
            ]
        );
    }

    #[Payload([AnilibriaService::MENU => AnilibriaService::WATCH], Payload::CONTAINS)]
    public function watch(Data $data)
    {
        $link = $data->getPayload()[AnilibriaService::EPISODE];
        RequestFacade::request("messages.sendMessageEventAnswer",
            [
                "event_id" => $data->getEventId(),
                "user_id" => $data->getUserId(),
                "peer_id" => $data->getPeerId(),
                "event_data" => json_encode(
                    [
                        "type" => "open_link",
                        "link" => $link
                    ]
                )
            ]
        );
    }

    #[Payload([AnilibriaService::MENU => AnilibriaService::ANIME_RANDOM])]
    public function randomAnimeButton(Data $data): void
    {
        $this->sendMessageEventAnswer($data,
            [
                "type" => "show_snackbar",
                "text" => "Бросаю кубик &#127922;"
            ]
        );
        AnilibriaService::animePreviewer($data, Method::getRandomTitle());
    }


    #[Payload([AnilibriaService::MENU => AnilibriaService::PLAY], Payload::CONTAINS)]
    /**
     * Кэширование и подготовка к просмотру
     */
    public function play(Data $data, int $current = 1)
    {
        $payload_anime_code = $data->getPayload()[AnilibriaService::CODE];
        $session = new Session($data->getPeerId(), $payload_anime_code);

        $anime = $session->get(AnilibriaService::DATA);
        if ($anime === null) {
            $anime = Method::getTitle(code: $payload_anime_code);

            $preview = AnilibriaService::MIRROR . $anime["poster"]["url"];
            $anime[AnilibriaService::VK_CACHE_PREVIEW] = UploaderFacade::upload(new PhotoMessages($preview))[0];

            $first_episode = $anime["player"]["series"]["first"];
            $last_episode = $anime["player"]["series"]["last"];

            $session->put(AnilibriaService::DATA, $anime);
            $session->put(AnilibriaService::CURRENT_EPISODE, $current);
            $session->put(AnilibriaService::FIRST_EPISODE, $first_episode);
            $session->put(AnilibriaService::LAST_EPISODE, $last_episode);

            $this->play($data, $current);

        }

        $this->messagesEdit(
            AnilibriaService::generateTemplate
            (
                $data,
                $anime,
                $current ?? $anime["player"]["series"]["first"]
            ),
            $data->getConversationMessageId()
        );
    }

    #[Payload([AnilibriaService::MENU => AnilibriaService::BACK], Payload::CONTAINS)]
    #[Payload([AnilibriaService::MENU => AnilibriaService::FORWARD], Payload::CONTAINS)]
    /**
     * Динамический плеер для пролистывания серий
     */
    public function switcher(Data $data): void
    {
        $payload_anime_code = $data->getPayload()[AnilibriaService::CODE];
        $session = new Session($data->getPeerId(), $payload_anime_code);

        $anime = $session->get(AnilibriaService::DATA);
        $current = $session->get(AnilibriaService::CURRENT_EPISODE);

        if ($data->getPayload()[AnilibriaService::MENU] === AnilibriaService::BACK) {
            $session->put(AnilibriaService::CURRENT_EPISODE, --$current);
        }

        if ($data->getPayload()[AnilibriaService::MENU] === AnilibriaService::FORWARD) {
            $session->put(AnilibriaService::CURRENT_EPISODE, ++$current);
        }

        $this->messagesEdit(
            AnilibriaService::generateTemplate($data, $anime, $current),
            $data->getConversationMessageId()
        );
    }

    #[Payload(["menu" => AnilibriaService::SELECT_EPISODE], Payload::CONTAINS)]
    /**
     * Динамический плеер для пролистывания серий
     */
    public function switch_episode(Data $data): void
    {
        $payload_anime_code = $data->getPayload()[AnilibriaService::CODE];
        $session = new Session($data->getPeerId(), AnilibriaService::SELECT_EPISODE);

        $session->put(AnilibriaService::CODE, $payload_anime_code);
        $session->put(AnilibriaService::SELECTED, false);

        $this->sendMessageEventAnswer($data,
            [
                "type" => "show_snackbar",
                "text" => "Какая тебе серия нужна?"
            ]);

        RequestFacade::request("messages.delete",
            [
                "conversation_message_ids" => $data->getConversationMessageId(),
                "peer_id" => $data->getPeerId()
            ]
        );
    }
}