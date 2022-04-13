<?php

namespace App\Services\Messengers;

use App\Contracts\MessengerContract;
use Discord\Discord;
use Discord\Exceptions\IntentException;
use Discord\Parts\Channel\Message;
use Discord\WebSockets\Event;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DiscordManager implements MessengerContract
{
    const STATUS_CHAT_STARTED = 1;
    private mixed $botToken;
    private mixed $webhookUrl;

    public function __construct()
    {
        $this->botToken = env('DISCORD_BOT_TOKEN');
        $this->webhookUrl = env('DISCORD_WEBHOOK_URL');
    }

    /**
     * @return string
     */
    public static function gateway(): string
    {
        return 'Discord';
    }

    /**
     * @return string
     */
    public static function name(): string
    {
        return 'Discord';
    }

    /**
     * @return string
     */
    public static function description(): string
    {
        return 'Discord is...';
    }

    /**
     * @return integer
     */
    public static function getNewStatusId(): int
    {
        return self::STATUS_CHAT_STARTED;
    }

    /**
     * @param Request $request
     *
     */
    public function handlerWebhookInvoice(Request $request): mixed
    {
        try {
            $discord = new Discord([
                'token' => $this->botToken,
            ]);
        } catch (IntentException $e) {
        }

        $discord->on('ready', function (Discord $discord) {
            // Listen for messages.
            $discord->on(Event::MESSAGE_CREATE, function (Message $message, Discord $discord) {
                return "{$message->author->username}: {$message->content}";
            });
        });

        $discord->run();

        return;
    }

    /**
     * @param array $message
     *
     * @return Response
     */
    public function sendMessage(array $message): Response
    {
        return Http::post($this->webhookUrl, [
            'content' => $message['content'],
            'embeds' => [
                [
                    'title' => $message['title'],
                    'description' => $message['description'],
                    'color' => '7506394',
                ],
            ],
        ]);
    }


}