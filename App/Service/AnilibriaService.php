<?php

namespace App\Service;

use Astaroth\Anilibria\Method;
use Astaroth\DataFetcher\Events\MessageNew as Data;
use Astaroth\DataFetcher\Events\MessageEvent as EventData;
use Astaroth\Support\Facades\Create;
use Astaroth\Support\Facades\Session;
use Astaroth\Support\Facades\Upload;
use Astaroth\VkKeyboard\Contracts\Keyboard\Button\FactoryInterface;
use Astaroth\VkKeyboard\Facade;
use Astaroth\VkKeyboard\Object\Keyboard\Button\Text;
use Astaroth\VkUtils\Builders\Attachments\Message\PhotoMessages;
use Astaroth\VkUtils\Builders\Message;

final class AnilibriaService
{
    public const FIRST_EPISODE = "_first-episode";
    public const LAST_EPISODE = "_last-episode";

    public const VK_CACHE_PREVIEW = "_vk_cache_preview";
    public const CURRENT_EPISODE = "_current-episode";

    public const DATA = "data";
    public const MIRROR = "https://dl4.anilib.top";

    public const SELECT_EPISODE = "_select-episode";
    public const ANIME_SEARCH = "_anime-search";
    public const ANIME_RANDOM = "_anime-random";

    public const FOUND = "found";
    public const SELECT = "select";
    public const CODE = "code";

    public const MENU = "menu";
    public const FORWARD = "forward";
    public const BACK = "back";
    public const PLAY = "play";
    public const WATCH = "watch";
    public const ANIME = "anime";
    public const EPISODE = "episode";

    public const WRONG_SELECTED_NOTICE = 19;

    public const NOT_FOUND_ANIME_NOTICE = 404;

    public const STARTS_WITH_LATTICE = "/^#(.*)/u";

    /** singleton */
    private function __construct()
    {
    }

    /**
     * Генератор текста
     * @param array $data
     * @param int|null $current_episode
     * @return string
     */
    public static function generateTemplateText(array $data, int $current_episode = null): string
    {
        $template_text = "%s\ncode: #%s\n\nТип: %s\nСтатус: %s\nЖанры: %s\nСезон: %s\nОзвучили: %s\nТаймили: %s\n\n%s";

        $text = sprintf($template_text,
            $data["names"]["ru"] ?? $data["names"]["en"],
            $data["code"],
            $data["type"]["full_string"],
            $data["status"]["string"],
            implode(", ", $data["genres"]),
            $data["season"]["string"],
            implode(", ", $data["team"]["voice"]),
            implode(", ", $data["team"]["timing"]),
            $data["description"]
        );

        if (isset($current_episode)) {
            $text .= "\n\nСерия $current_episode из " . $data["player"]["series"]["last"];
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
                    $factory->callback("Назад", [self::MENU => self::BACK, self::CODE => $anime[self::CODE]], Text::COLOR_BLUE),
                    $factory->callback("Вперёд", [self::MENU => self::FORWARD, self::CODE => $anime[self::CODE]]),
                ],
                [
                    $factory->callback("Смотреть", [self::MENU => self::WATCH, self::EPISODE => $stream], Text::COLOR_GRAY)
                ],
                [
                    $factory->callback("Выбрать серию", [self::MENU => self::SELECT_EPISODE, self::CODE => $anime[self::CODE]], Text::COLOR_BLUE)
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
            ->setAttachment($anime[self::VK_CACHE_PREVIEW])
            ->setKeyboard(@json_encode($patch_keyboard))
            ->setPeerId($data->getPeerId())
            ->setDontParseLinks(true);
    }

    /**
     * Выбрать желаемый эпизод\серию
     * @param Data|EventData $data
     * @param string $code
     * @param int $desiredEpisode
     * @return bool
     * @throws \Throwable
     */
    public static function selectEpisode(Data|EventData $data, string $code, int $desiredEpisode): bool
    {
        $anime = (new Session($data->getPeerId(), self::ANIME))->get($code);

        if ($anime[self::LAST_EPISODE] >= $desiredEpisode && $desiredEpisode !== 0) {
            Create::new(self::generateTemplate($data, $anime, $desiredEpisode));
            return true;
        }

        self::notice($data, self::WRONG_SELECTED_NOTICE);
        return false;
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
                    $factory->link("Смотреть на Anilibria", $link, []),
                    $factory->callback("Play",
                        [
                            AnilibriaService::MENU => AnilibriaService::PLAY,
                            AnilibriaService::CODE => $anime[AnilibriaService::CODE]
                        ], Text::COLOR_RED)
                ],
            ];
        });

        Create::new(
            (new Message())
                ->setMessage(self::generateTemplateText($anime))
                ->setAttachment(...Upload::attachments(new PhotoMessages($preview)))
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
            self::WRONG_SELECTED_NOTICE => "Ты чё цифры попутал 😠\nВыбери верный вариант. Я от тебя не отстану 😡",
            self::NOT_FOUND_ANIME_NOTICE => "Я ничево не смогла найти 🥺",
        };

        Create::new(
            (new Message())->setMessage($message)
                ->setPeerId($data->getPeerId())
        );
    }

    /**
     * Поиск тайтлов с последующей отправкой в диалог
     * @param string|null $anime_name
     * @return array|null
     * @throws \Exception
     */
    public static function searchTitle(string $anime_name = null): ?array
    {
        $anime = Method::searchTitles($anime_name);

        array_unshift($anime, "");
        unset($anime[0]);

        if ($anime === []) {
            return null;
        }

        $template = "";
        $found = [];
        foreach ($anime as $key => $value) {
            $name = $value["names"]["ru"] ?? $value["names"]["en"] ?? $value["names"]["alternative"];
            $code = $value["code"];

            $template .= "$key. $name\ncode: #$code\n";

            $found[$key] =
                [
                    self::CODE => $value[self::CODE],
                    "name" => $name
                ];
        }

        return [self::FOUND => $found, "template" => $template];

    }

}