<?php
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

define('CALLBACK_API_EVENT_CONFIRMATION', 'confirmation');
define('CALLBACK_API_EVENT_MESSAGE_NEW', 'message_new');
define('CALLBACK_API_EVENT_MESSAGE_REPLY', 'message_reply');

require_once 'config.php';
require_once 'global.php';

require_once 'api/vk_api.php';
require_once 'api/yandex_api.php';

require_once 'bot/bot.php';


if (!isset($_REQUEST)) {
    return;
}

callback_handleEvent();

function callback_handleEvent() {
	$event = _callback_getEvent();

	try {
		switch ($event['type']) {
		//Подтверждение сервера
		case CALLBACK_API_EVENT_CONFIRMATION:
			_callback_handleConfirmation();
			break;

		//Получение нового сообщения
		case CALLBACK_API_EVENT_MESSAGE_NEW:
			_callback_handleMessageNew($event['object']);
			break;
		case CALLBACK_API_EVENT_MESSAGE_REPLY:
			_callback_okResponse();
			break;
		default:
			_callback_response('Unsupported event');
			break;
		}
	} catch (Exception $e) {
		log_error($e);
	}
}

function _callback_getEvent() {
  	return json_decode(file_get_contents('php://input'), true);
}

function _callback_handleConfirmation() {
  	_callback_response(CALLBACK_API_CONFIRMATION_TOKEN);
}

function _callback_handleMessageNew($data) {
	if (!$data || !$data['message'] || !$data['message']['from_id']) return;
	$text = $data['message']['text'];
	$user_id = $data['message']['from_id'];
	$peer_id = $data['message']['peer_id'];
	$command = ['!start', '!vote'];

	switch ($text){
		case "!start":
			if ($user_id != $peer_id) bot_sendChatHelloMessage($peer_id);
			if ($user_id == $peer_id) {
				bot_startUserVote($user_id);
				bot_formVoteOption($user_id);
			}
			break;
		case "!vote":
			if ($user_id == $peer_id) return;
			if (bot_checkNewUser($user_id)) bot_createNewUser($user_id, $peer_id);
			break;
		case "!result":
			bot_sendVoteResult($peer_id, 1);
			break;
		case "!table":
			bot_sendTableVoteResult($peer_id);
			break;
	}

	if (!in_array($text, $command)) {
		if (bot_isVotingUser($user_id) && is_numeric($text) && $text <= bot_getCountOptions(1) && bot_checkNewAnswer($user_id, $text)){
			bot_chooseOption($user_id, 1, $text);
			bot_formVoteOption($user_id);
		}
	}

	_callback_okResponse();
}

function _callback_okResponse() {
	header("HTTP/1.1 200 OK");
	_callback_response("ok");
  
}

function _callback_response($data) {
	echo $data;
	exit();
}

?>