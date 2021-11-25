<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\AnilibriaService;
use Astaroth\Anilibria\Method;
use Astaroth\Attribute\Conversation;
use Astaroth\Attribute\Event\MessageEvent;
use Astaroth\Attribute\Payload;
use Astaroth\Commands\BaseCommands;
use Astaroth\DataFetcher\Events\MessageEvent as Data;
use Astaroth\Support\Facades\Request;
use Astaroth\Support\Facades\Session;
use Astaroth\Support\Facades\State;
use Astaroth\Support\Facades\Upload;
use Astaroth\VkUtils\Builders\Attachments\Message\PhotoMessages;

#[Conversation(Conversation::PERSONAL_DIALOG)]
#[MessageEvent]
final class ButtonNavigation extends BaseCommands
{
    #[Payload([AnilibriaService::MENU => AnilibriaService::ANIME_SEARCH])]
    public function searchAnimeButton(Data $data, Request $r): void
    {
        $r::call("messages.sendMessageEventAnswer",
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

        State::add($data->getPeerId(), AnilibriaService::ANIME_SEARCH);
    }

    #[Payload([AnilibriaService::MENU => AnilibriaService::WATCH], Payload::CONTAINS)]
    public function watch(Data $data, Request $r)
    {
        $link = $data->getPayload()[AnilibriaService::EPISODE];
        $r::call("messages.sendMessageEventAnswer",
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
        $payload_anime_code = (string)$data->getPayload()[AnilibriaService::CODE];
        $session = new Session($data->getPeerId(), AnilibriaService::ANIME);

        $anime = $session->get($payload_anime_code);
        if ($anime === null) {
            $anime = Method::getTitle(code: $payload_anime_code);

            $preview = AnilibriaService::MIRROR . $anime["poster"]["url"];
            $anime[AnilibriaService::VK_CACHE_PREVIEW] = Upload::attachments(new PhotoMessages($preview))[0];

            $first_episode = $anime["player"]["series"]["first"];
            $last_episode = $anime["player"]["series"]["last"];

            $dataStructure = array_merge($anime, [
                AnilibriaService::CURRENT_EPISODE => $current,
                AnilibriaService::FIRST_EPISODE => $first_episode,
                AnilibriaService::LAST_EPISODE => $last_episode,
            ]);
            $session->put($payload_anime_code, $dataStructure);

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
        $session = new Session($data->getPeerId(), AnilibriaService::ANIME);

        $anime = $session->get($payload_anime_code);

        $current = $anime[AnilibriaService::CURRENT_EPISODE];

        if ($data->getPayload()[AnilibriaService::MENU] === AnilibriaService::BACK) {
            --$current;
        }

        if ($data->getPayload()[AnilibriaService::MENU] === AnilibriaService::FORWARD) {
            ++$current;
        }

        $session->put($payload_anime_code, array_merge($anime, [AnilibriaService::CURRENT_EPISODE => $current]));

        $this->messagesEdit(
            AnilibriaService::generateTemplate($data, $anime, $current),
            $data->getConversationMessageId()
        );
    }

    #[Payload([AnilibriaService::MENU => AnilibriaService::SELECT_EPISODE], Payload::CONTAINS)]
    public function switch_episode(Data $data, Request $r): void
    {
        $payload_anime_code = $data->getPayload()[AnilibriaService::CODE];
        $session = new Session($data->getPeerId(), AnilibriaService::SELECT_EPISODE);

        $session->put(AnilibriaService::CODE, $payload_anime_code);
        State::add($data->getPeerId(), AnilibriaService::SELECT_EPISODE);

        $this->sendMessageEventAnswer($data,
            [
                "type" => "show_snackbar",
                "text" => "Какая тебе серия нужна?"
            ]);

        $r::call("messages.delete",
            [
                "conversation_message_ids" => $data->getConversationMessageId(),
                "peer_id" => $data->getPeerId()
            ]
        );
    }
}