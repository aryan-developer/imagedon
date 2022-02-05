<?php

use danog\MadelineProto\APIWrapper;
use danog\MadelineProto\EventHandler;

if (\function_exists('memprof_enable')) {
    \memprof_enable();
}
if (!\file_exists('madeline.php')) {
    \copy('https://phar.madelineproto.xyz/madeline.php', 'madeline.php');
}
include 'madeline.php';

class MyEventHandler extends EventHandler
{

    const ADMIN = 'aryan_developer';

    public function getReportPeers(): array
    {
        return [self::ADMIN];
    }

    public function onUpdateNewChannelMessage(array $update): Generator
    {
        $this->onUpdateNewMessage($update);
    }

    public function onUpdateNewMessage(array $update): \Generator
    {
        try {
            $message_id = $update['message']['id'] ?? 0;
            $replyToId = $update['message']['reply_to']['reply_to_msg_id'] ?? 0;
            $info = yield $this->getInfo($update);
            $type = $info['type'];
            $text = $update['message']['message'];
            $from_id = $update['message']['from_id']['user_id'] ?? 0;
            $out = $update['message']['out'];
            $to = $update['message']['to_id']['user_id'] ?? 0;
            $me = $this->getSelf();
            $me_id = $me['id'];
            if ($out and $text == "بصب دان بشه" and $replyToId and $type == "user") {
                $ier = yield $this->messages->getMessages([
                    'peer' => $update,
                    'id' => [
                        $replyToId
                    ]
                ]);
                $file = $ier['messages'][0]['media'] ?? "none";
                if ($file != "none") {
                    $name = $ier['messages'][0]['media']['_'] == "messageMediaPhoto" ? "image.jpg" : "video.mp4";
                    $nameUp = $ier['messages'][0]['media']['_'] == "messageMediaPhoto" ? "inputMediaUploadedPhoto" : "inputMediaUploadedDocument";
                    yield $this->downloadToFile($file, $name);
                    yield $this->messages->sendMedia(
                        [
                            'peer' => 'me',
                            'media' => [
                                '_' => $nameUp,
                                'file' => $name
                            ]
                        ]
                    );
                    unlink($name);
                }
            }
        } catch (Throwable $e) {
        }
    }
}

$settings = [
    'logger' => [
        'logger_level' => 4,
    ],
    'serialization' => [
        'serialization_interval' => 30
    ],
    'connection_settings' => [
        'media_socket_count' => [
            'min' => 20,
            'max' => 1000,
        ]
    ],
    'upload' => [
        'allow_automatic_upload' => true // IMPORTANT: for security reasons, upload by URL will still be allowed
    ]
];
$MadelineProto = new \danog\MadelineProto\API('imagedon.madeline', $settings);
$MadelineProto->phoneLogin($MadelineProto->readline('Enter your phone number: '));
$authorization = $MadelineProto->completePhoneLogin($MadelineProto->readline('Enter the phone code: '));
if ($authorization['_'] === 'account.password') {
    $authorization = $MadelineProto->complete2falogin($MadelineProto->readline('Please enter your password (hint '.$authorization['hint'].'): '));
}
if ($authorization['_'] === 'account.needSignup') {
    $authorization = $MadelineProto->completeSignup($MadelineProto->readline('Please enter your first name: '), readline('Please enter your last name (can be empty): '));
}
$MadelineProto->startAndLoop(MyEventHandler::class);
