<?php

namespace App\Service;

use Astaroth\Anilibria\Method;
use Astaroth\DataFetcher\Events\MessageNew as Data;
use Astaroth\DataFetcher\Events\MessageEvent as EventData;
use Astaroth\Foundation\Session;
use Astaroth\Support\Facades\BuilderFacade;
use Astaroth\Support\Facades\UploaderFacade;
use Astaroth\VkKeyboard\Contracts\Keyboard\Button\FactoryInterface;
use Astaroth\VkKeyboard\Facade;
use Astaroth\VkKeyboard\Object\Keyboard\Button\Text;
use Astaroth\VkUtils\Builders\Attachments\Message\PhotoMessages;
use Astaroth\VkUtils\Builders\Message;

final class AnilibriaService
{
    public const FIRST_EPISODE = "first-episode";
    public const LAST_EPISODE = "last-episode";

    public const VK_CACHE_PREVIEW = "vk_cache_preview";

    public const CURRENT_EPISODE = "current-episode";
    public const DATA = "data";
    public const MIRROR = "https://dl3.anilib.top";

    public const SELECT_EPISODE = "select-episode";
    public const ANIME_SEARCH = "anime-search";
    public const ANIME_RANDOM = "anime-random";

    public const ENABLED = "enabled";
    public const FOUND = "found";
    public const SELECTED = "selected";
    public const CODE = "code";

    public const MENU = "menu";
    public const FORWARD = "forward";
    public const BACK = "back";
    public const PLAY = "play";

    public const WRONG_SELECTED_NOTICE = 19;
    public const NOT_FOUND_ANIME_NOTICE = 404;

    /** singleton */
    private function __construct()
    {
    }

    public static function generateTemplateText(array $data, int $current_serie = null): string
    {
        $template_text = "%s\n\nТип: %s\nСтатус: %s\nЖанры: %s\nСезон: %s\nОзвучили: %s\nТаймили: %s\n\n%s";

        $text = sprintf($template_text,
            $data["names"]["ru"] ?? $data["names"]["en"],
            $data["type"]["full_string"],
            $data["status"]["string"],
            implode(", ", $data["genres"]),
            $data["season"]["string"],
            implode(", ", $data["team"]["voice"]),
            implode(", ", $data["team"]["timing"]),
            $data["description"]
        );

        if (isset($current_serie)) {
            $text .= "\n\nСерия $current_serie из " . $data["player"]["series"]["last"];
        }

        return $text;
    }

    /**
     * Генератор плеера
     * @param Data|EventData $data
     * @param array $anime
     * @param int $current_episode
     * @return Message
     */
    public static function generateTemplate(Data|EventData $data, array $anime, int $current_episode): Message
    {
        $player = $anime["player"];
        $host = $player["host"];
        $playlist = $player["playlist"];

        $first_episode = $playlist[$current_episode];
        $hls = $first_episode["hls"];

        $best_quality = $hls["fhd"] ?? $hls["hd"] ?? $hls["sd"];
        $stream = "https://$host$best_quality";

        $last_episode = $anime["player"]["series"]["last"];

        $keyboard = Facade::createKeyboardInline(function (FactoryInterface $factory) use ($anime, $stream) {
            return [
                [
                    $factory->callback("Назад", ["menu" => "back", "code" => $anime["code"]], Text::COLOR_BLUE),
                    $factory->callback("Вперёд", ["menu" => "forward", "code" => $anime["code"]]),
                ],
                [
                    $factory->link("Смотреть", $stream, [])
                ],
                [
                    $factory->callback("Выбрать серию", ["menu" => "select-episode", "code" => $anime["code"]], Text::COLOR_BLUE)
                ]
            ];
        });

        $patch_keyboard = @json_decode($keyboard, true);
        if ($current_episode === $last_episode) {
            unset($patch_keyboard["buttons"][0][1]);
        }

        if ($current_episode === 1) {
            unset($patch_keyboard["buttons"][0][0]);
        }

        return (new Message())
            ->setMessage(self::generateTemplateText($anime, $current_episode))
            ->setAttachment($anime["vk_cache_preview"])
            ->setKeyboard(@json_encode($patch_keyboard))
            ->setPeerId($data->getPeerId());
    }

//    public static function encryptPayload(string $payload): string
//    {
//        return @openssl_encrypt($payload, ENCRYPT_ALGO, ENCRYPT_PASSPHRASE);
//    }
//
//    public static function decryptPayload(string $payload): string
//    {
//        return @openssl_decrypt($payload, ENCRYPT_ALGO, ENCRYPT_PASSPHRASE);
//    }

    /**
     * Выбрать желаемый эпизод\серию
     * @param Data|EventData $data
     * @param Session $session
     * @throws \Throwable
     */
    public static function selectEpisode(Data|EventData $data, Session $session): void
    {
        $anime = new Session($data->getPeerId(), $session->get(self::CODE));

        $desiredEpisode = (int)$data->getText();
        if ($desiredEpisode) {
            BuilderFacade::create(self::generateTemplate($data, $anime->get(self::DATA), $desiredEpisode));
        } else {
            self::notice($data, self::WRONG_SELECTED_NOTICE);
        }

        $session->purge(true);
    }

    /**
     * Select anime from search
     * @param Data $data
     * @param Session $session
     * @param string|null $key
     * @throws \Throwable
     */
    public static function select(Data $data, Session $session, string $key = null): void
    {
        $anime = $session->get($key);
        if (!empty($anime)) {
            $anime_number = (int)$data->getText();
            $concreteAnime = $anime[$anime_number] ?? null;
            if ($concreteAnime) {
                self::animePreviewer($data, $concreteAnime[self::CODE]);
            }
            if ($concreteAnime === null) {
                self::notice($data, self::WRONG_SELECTED_NOTICE);
            }
        }
        $session->purge(true);
    }

    /**
     * Рендер превью аниме
     * @param Data|EventData $data
     * @param array|string $anime
     * @throws \Throwable
     */
    public static function animePreviewer(Data|EventData $data, array|string $anime): void
    {
        if (is_string($anime)) {
            $anime = Method::getTitle(code: $anime);
        }
        $preview = self::MIRROR . $anime["poster"]["url"];

        $link = self::MIRROR . "/release/" . $anime[self::CODE] . ".html";
        $keyboard = Facade::createKeyboardInline(function (FactoryInterface $factory) use ($anime, $link) {
            return [
                [
                    $factory->link("Зеркало", $link, []),
                    $factory->callback("Play",
                        [
                            AnilibriaService::MENU => AnilibriaService::PLAY,
                            AnilibriaService::CODE => $anime[AnilibriaService::CODE]
                        ], Text::COLOR_RED)
                ],
            ];
        });

        BuilderFacade::create(
            (new Message())
                ->setMessage(self::generateTemplateText($anime))
                ->setAttachment(...UploaderFacade::upload(new PhotoMessages($preview)))
                ->setKeyboard($keyboard)
                ->setPeerId($data->getPeerId())
        );
    }

    /**
     * Различные памятки
     * @param Data $data
     * @param int $noticeType
     * @throws \Throwable
     */
    public static function notice(Data $data, int $noticeType): void
    {
        $message = match ($noticeType) {
            self::WRONG_SELECTED_NOTICE => "Ты видимо забыл цифры, тогда и я забуду!",
            self::NOT_FOUND_ANIME_NOTICE => "Я ничево не смог найти 🥺",
        };

        BuilderFacade::create(
            (new Message())->setMessage($message)
                ->setPeerId($data->getPeerId())
        );
    }

    /**
     * Поиск тайтлов с последующей отправкой в диалог
     * @param Data $data
     * @param Session $session
     * @param string|null $needle
     * @throws \Throwable
     */
    public static function searchTitle(Data $data, Session $session, string $needle = null): void
    {
        $anime = Method::searchTitles($needle ?? $data->getText());

        array_unshift($anime, "");
        unset($anime[0]);

        if ($anime === []) {
            self::notice($data, self::NOT_FOUND_ANIME_NOTICE);
            $session->purge(true);
        } else {
            $template = "Всё что я смог найти, выбрать нужное можно просто отправив цифру\n\n";
            $payload = [];
            foreach ($anime as $key => $value) {
                $name = $value["names"]["ru"] ?? $value["names"]["en"] ?? $value["names"]["alternative"];
                $template .= "$key. $name\n";

                $payload[$key] =
                    [
                        self::CODE => $value[self::CODE],
                        "name" => $name
                    ];
            }

            $session->put(self::FOUND, $payload);
            $session->put(self::ENABLED, false);

            BuilderFacade::create(
                (new Message())
                    ->setMessage($template)
                    ->setPeerId($data->getPeerId())
            );
        }

    }

}