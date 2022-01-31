<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\AnilibriaService;
use Astaroth\Anilibria\Method;
use Astaroth\Attribute\ClassAttribute\Conversation;
use Astaroth\Attribute\ClassAttribute\Event;
use Astaroth\Attribute\Method\Payload;
use Astaroth\Commands\BaseCommands;
use Astaroth\DataFetcher\Events\MessageEvent as Data;
use Astaroth\Enums\ConversationType;
use Astaroth\Enums\Events;
use Astaroth\Enums\PayloadValidation;
use Astaroth\Support\Facades\Request;
use Astaroth\Support\Facades\Session;
use Astaroth\Support\Facades\State;
use Astaroth\Support\Facades\Upload;
use Astaroth\VkUtils\Builders\Attachments\Message\PhotoMessages;
use Exception;
use Throwable;

#[Event(Events::MESSAGE_EVENT)]
#[Conversation(ConversationType::PERSONAL)]
final class ButtonNavigation extends BaseCommands
{
    /**
     * @throws Throwable
     */
    #[Payload([AnilibriaService::MENU => AnilibriaService::ANIME_SEARCH])]
    public function searchAnimeButton(Data $data): void
    {
        Request::call("messages.sendMessageEventAnswer",
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

    /**
     * @throws Throwable
     */
    #[Payload([AnilibriaService::MENU => AnilibriaService::WATCH], PayloadValidation::CONTAINS)]
    public function watch(Data $data)
    {
        $link = $data->getPayload()[AnilibriaService::EPISODE];
        Request::call("messages.sendMessageEventAnswer",
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

    /**
     * @throws Throwable
     */
    #[Payload([AnilibriaService::MENU => AnilibriaService::ANIME_RANDOM])]
    public function randomAnimeButton(Data $data): void
    {
        $this->request()->sendMessageEventAnswer($data,
            [
                "type" => "show_snackbar",
                "text" => "Бросаю кубик &#127922;"
            ]
        );
        AnilibriaService::animePreviewer($data, Method::getRandomTitle());
    }


    #[Payload([AnilibriaService::MENU => AnilibriaService::PLAY], PayloadValidation::CONTAINS)]
    /**
     * Кэширование и подготовка к просмотру
     * @throws Exception|Throwable
     */
    public function play(Data $data, int $current = 1)
    {
        $payload_anime_code = (string)$data->getPayload()[AnilibriaService::CODE];
        $session = new Session($data->getPeerId(), AnilibriaService::ANIME);

        $anime = $session->get($payload_anime_code);
        if ($anime === null) {
            $anime = Method::getTitle(code: $payload_anime_code);

            $preview = AnilibriaService::posterNormalizer($anime);
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

        $this->request()->messagesEdit(
            AnilibriaService::generateTemplate
            (
                $data,
                $anime,
                $current ?? $anime["player"]["series"]["first"]
            ),
            $data->getConversationMessageId()
        );
    }

    #[Payload([AnilibriaService::MENU => AnilibriaService::BACK], PayloadValidation::CONTAINS)]
    #[Payload([AnilibriaService::MENU => AnilibriaService::FORWARD], PayloadValidation::CONTAINS)]
    /**
     * Динамический плеер для пролистывания серий
     * @throws Throwable
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

        $this->request()->messagesEdit(
            AnilibriaService::generateTemplate($data, $anime, $current),
            $data->getConversationMessageId()
        );
    }

    /**
     * @throws Throwable
     */
    #[Payload([AnilibriaService::MENU => AnilibriaService::SELECT_EPISODE], PayloadValidation::CONTAINS)]
    public function switchEpisode(Data $data): void
    {
        $payload_anime_code = $data->getPayload()[AnilibriaService::CODE];
        $session = new Session($data->getPeerId(), AnilibriaService::SELECT_EPISODE);

        $session->put(AnilibriaService::CODE, $payload_anime_code);
        State::add($data->getPeerId(), AnilibriaService::SELECT_EPISODE);

        $this->request()->sendMessageEventAnswer($data,
            [
                "type" => "show_snackbar",
                "text" => "Какая тебе серия нужна?"
            ]);

        Request::call("messages.delete",
            [
                "conversation_message_ids" => $data->getConversationMessageId(),
                "peer_id" => $data->getPeerId()
            ]
        );
    }
}