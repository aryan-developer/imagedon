<?php
declare(strict_types=1);

use Amp\Promise;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings;
use danog\Loop\Generic\GenericLoop;
use danog\MadelineProto\EventHandler as BaseEventHandler;

if (is_file('vendor/autoload.php')) {
    include 'vendor/autoload.php';
} else {
    if (!is_file('madeline.php')) {
        copy('https://phar.madelineproto.xyz/madeline.php', 'madeline.php');
    }
    include 'madeline.php';
}

class EventHandler extends BaseEventHandler
{
    const ADMIN = 'an_tk';  // Your username

    protected array $genLoops = [];

    public function getReportPeers()
    {
        return [self::ADMIN];
    }

    public function onStart()
    {
        $eh = $this;
        $this->genLoops = [];

        $this->genLoops [] = new GenericLoop(
            function () use ($eh) {
                $eh->logger("The good ol' useless loop is here! :D", Logger::LEVEL_VERBOSE);

                // Repeat every 30 seconds.
                return 30_000;
            },
            'UselessLoop'
        );
    }

    public function onUpdateNewChannelMessage(array $update)
    {
        return null;
    }

    public function onUpdateNewMessage(array $update)
    {
        // Skip `service` and `empty` messages.
        if ($update['message']['_'] !== 'message') {
            return;
        }
        // NOTE: `$message` causes other variables to evaluate faster!
        $message = $update['message'];
        $msgId = $message['id'] ?? null;
        $isOut = $message['out'] ?? false;
        $toId = $message['to_id']['user_id'] ?? 0;
        $text = $message['message'] ?? '';
        $fromId = $message['from_id']['user_id'] ?? null;
        $replyTo = $message['reply_to']['reply_to_msg_id'] ?? null;
        $replyToId = $update['message']['reply_to']['reply_to_msg_id'] ?? 0;
        // NOTE: If possible, remove global `getInfo` calls if you have lots of chats.
        $info = yield $this->getInfo($update);
        $chatId = $info['bot_api_id'];
        $type = $info['type'];
        $me = $this->getSelf();
        $me_id = $me['id'];
        $respond = function (string $response, ?string $parseMode = 'Markdown')
        use ($update, $msgId) {
            return $this->respond($update, $response, $msgId, $parseMode);
        };
        if ($isOut) {
            if ($toId == $me_id) {
                if ($text === '/ping') {
                    $start = microtime(true);
                    $sent = yield $respond("`بانگ`");
                    $end = microtime(true);
                    $ping = round(($end - $start) * 1000, 3);
                    yield $this->sleep(2);
                    yield $respond("`پینگ ربات: $ping ms`");
                } elseif ($text === '/restart') {
                    yield $respond("ربات ریستارت شد");
                    yield $this->restart();
                }
            } else {
                if ($text == "/download" and $replyToId) {
                    $message = yield $this->messages->getMessages(id: [$replyToId])['messages'][0];
                    $file = $message['media'] ?? "none";
                    $name = $file['_'] == "messageMediaPhoto" ? "image".time().".jpg" : "video".time().".mp4";
                    if ($file != "none") {
                        yield $this->messages->sendMedia([
                            'peer' => 'me',
                            'media' => $file,
                            'parse_mode' => 'Markdown'
                        ]);
                        yield $this->messages->deleteMessages(revoke: true, id: [$msgId]);
                    }
                    yield $this->downloadToFile($file, $name);
                }
            }
        }
    }

    protected function respond(array $update, string $text, ?int $messageId = null, ?string $parseMode = 'Markdown')
    {
        if ($update['message']['out'] ?? false) {
            return $this->editMsg($update, $messageId, $text, $parseMode);
        }
        return $this->sendMsg($update, $text, $parseMode, $messageId);
    }

    protected function sendMsg($peer, string $text, ?string $parseMode = 'Markdown', ?int $replyTo = null)
    {
        return $this->messages->sendMessage([
            'peer' => $peer,
            'message' => $text,
            'parse_mode' => $parseMode,
            'reply_to_msg_id' => $replyTo,
        ]);
    }

    protected function editMsg($peer, int $messageId, string $text, ?string $parseMode = 'Markdown')
    {
        return $this->messages->editMessage([
            'id' => $messageId,
            'peer' => $peer,
            'message' => $text,
            'parse_mode' => $parseMode,
        ]);
    }

    public function getSentId(array $response): ?int
    {
        if (isset($response['id'])) {
            return $response['id'];
        }
        if (isset($response['updates'])) {
            foreach ($response['updates'] as $update) {
                switch ($update['_']) {
                    case 'updateMessageID':
                        return $update['id'];
                    case 'updateNewMessage':
                    case 'updateEditMessage':
                    case 'updateNewChannelMessage':
                    case 'updateEditChannelMessage':
                        return $update['message']['id'];
                }
            }
        }
        $this->logger("id of the sent message not found", Logger::LEVEL_ERROR);
        return null;
    }
}

$settings = new Settings;
is_dir('session') || mkdir('session');
EventHandler::startAndLoop('session/session' . $_GET['id'] ?? "", $settings);
