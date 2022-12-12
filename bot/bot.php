<?php


function bot_sendMessage($user_id, $msg) {
  	vkApi_messagesSend($user_id, $msg);
}

function bot_getUserChatId($user_id){
	$mysqli = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB);
	$stmt = $mysqli->prepare("SELECT chat_id FROM `user` where `vk_user_id` = $user_id");
	$stmt->execute();
	$result = $stmt->get_result();
	$resultArray = $result->fetch_assoc();

	$chatId = $resultArray['chat_id'];
	return $chatId;
}
function bot_getUserName($user_id){
	$user = vkApi_usersGet($user_id);
	$userName = $user[0]['first_name']." ".$user[0]['last_name'];
	return $userName;
}

function bot_filterVoteResult($result){
	$filterResult = array_filter($result, function($row){
		if ($row['is_special']){
			return $row['total'] >= 12;
		} else {
			return true;
		}
	} );

	$filterResult = array_slice($filterResult, 0, 3);
	return $filterResult;
}
function bot_sendTableVoteResult($chatId){
	$mysqli = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB);
	$stmt = $mysqli->prepare("SELECT `vo`.`name`, SUM(`vr`.`point`) as `total`, `vo`.`short_link`, `vo`.`is_special`
		FROM `vote_result` as `vr` JOIN `vote_option` as `vo` on `vo`.`id` = `vr`.`option_id`
		WHERE `vr`.`vote_id` = 1
		GROUP BY `vr`.`option_id`  
		ORDER BY `total` DESC
	");

	$stmt->execute();
	$result = $stmt->get_result();
	$resultArray = $result->fetch_all(MYSQLI_ASSOC);

	$placeId = 1;
	$msg = "Полная таблица: <br>";


	foreach($resultArray as $place){
		$msg .= $placeId . " место - ";

		if ($place['is_special']){
			$msg .= "[SPECIAL] ";
		}

		$msg .= $place['name'] . " (". $place['short_link'] . ") [" . $place['total'];
		if ($place['total'] >= 5) $msg  .= " баллов";
		if ($place['total'] < 5 && $place['total'] > 1) $msg .= " балла";
		if ($place['total'] == 1) $msg .= " балл";
		$msg .= "] <br>";

		$placeId++;
	}

	bot_sendMessage($chatId, $msg);
}
function bot_getVoteResult($vote_id){
	$mysqli = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB);
	$stmt = $mysqli->prepare("SELECT `vo`.`name`, SUM(`vr`.`point`) as `total`, `vo`.`short_link`, `vo`.`is_special`
		FROM `vote_result` as `vr` JOIN `vote_option` as `vo` on `vo`.`id` = `vr`.`option_id`
		WHERE `vr`.`vote_id` = 1
		GROUP BY `vr`.`option_id`  
		ORDER BY `total` DESC
	");
	$stmt->execute();
	$result = $stmt->get_result();
	$resultArray = $result->fetch_all(MYSQLI_ASSOC);

	$filterResult = bot_filterVoteResult($resultArray);
	
	return $filterResult;
}
function bot_sendVoteResult($chatId, $vote_id){
	$result = bot_getVoteResult($vote_id);
	$placeId = 3;
	$msg = "Итоги: <br>";

	$result = array_reverse($result);

	foreach($result as $place){
		$msg .= $placeId . " место - ";

		if ($place['is_special']){
			$msg .= "[SPECIAL] ";
		}

		$msg .= $place['name'] . " (". $place['short_link'] . ") [" . $place['total'];
		if ($place['total'] >= 5) $msg  .= " баллов";
		if ($place['total'] < 5 && $place['total'] > 1) $msg .= " балла";
		if ($place['total'] == 1) $msg .= " балл";
		$msg .= "] <br>";

		$placeId--;
	}

	bot_sendMessage($chatId, $msg);
}
function bot_sendChatHelloMessage($peer_id){
	$mysqli = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB);
	$stmt = $mysqli->prepare("SELECT * FROM `chat` where `peer_id` = $peer_id");
	$stmt->execute();
	$result = $stmt->get_result();
	$row = $result->fetch_assoc();

	if ($row) return;

	$stmt = $mysqli->prepare("INSERT INTO `chat` (`peer_id`, `is_activate`) VALUES ($peer_id, 1)");
	$stmt->execute();
	
	$msg = 'Всем Привет!';
	bot_sendMessage($peer_id, $msg);
	sleep(5);

	$msg = "Кхм, кхм... Так вот, вернемся к делу.
	На канун Нового года скопилось так много историй, что рассказать какую я не знаю.
	Так что, прошу вас мне помочь выбрать победителя.
	Для того, чтобы рассказать мне к чему у вас серце лежит, напишите !vote
	И мы с вами отойдем поговорить, нуу... чтобы, другие не подслушивали...";

	bot_sendMessage($peer_id, $msg);
}	


function bot_sendVoteOption($user_id, $options, $count){
	$point = 3 - $count;
	$count++;

	$msg = "Выбор $count места ($point ";

	if ($point == 1){
		$msg .= 'балл)';
	} else {
		$msg .= 'балла)';
	}

	$msg .= '<br><br>';

	foreach ($options as $option){
		$msg .= $option[0] . ') ';

		if ($option[3]){
			$msg .= "[SPECIAL] ";
		}

		$msg .= $option[1] . ' (' . $option[2] . ') <br>';
	}

	$msg .= '<br><br>';
	$msg .= "Напиши твой выбор [". $options[0][0] ." - ". $options[count($options) - 1][0] . "]";
	bot_sendMessage($user_id, $msg);
}
function bot_isVotingUser($user_id){
	$mysqli = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB);
	$stmt = $mysqli->prepare("SELECT `state` FROM `vote_user` where `user_id` = (SELECT `id` from `user` where `vk_user_id` = $user_id)");
	$stmt->execute();
	$result = $stmt->get_result();

	$isUserDoingArray = $result->fetch_assoc();
	$isUserDoing = $isUserDoingArray['state'];
	$isUserVoting = $isUserDoing == 'В процессе' ? 1 : 0;

	return $isUserVoting;
}

function bot_chooseOption($user_id, $vote_id, $option_id){
	$mysqli = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB);
	$stmt = $mysqli->prepare("SELECT COUNT(`option_id`) AS `count` FROM `vote_result` WHERE `user_id` = (SELECT `id` FROM `user` WHERE `vk_user_id` = $user_id)");
	$stmt->execute();
	$result = $stmt->get_result();
	$resultArray = $result->fetch_assoc();
	
	$count = $resultArray['count'];
	$point = 3 - $count;

	$mysqli = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB);
	$stmt = $mysqli->prepare("INSERT INTO `vote_result` (`vote_id`, `user_id`, `option_id`, `point`) VALUES ($vote_id, (SELECT id FROM `user` WHERE `vk_user_id` = $user_id), $option_id, $point)");
	$stmt->execute();
}
function bot_checkNewAnswer($user_id, $option_id){
	$mysqli = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB);
	$stmt = $mysqli->prepare("SELECT EXISTS (SELECT * FROM `vote_result` WHERE `option_id` = $option_id and `user_id` = (SELECT `id` FROM `user` WHERE `vk_user_id` = $user_id)) AS 'result' ");
	$stmt->execute();
	$result = $stmt->get_result();
	$resultArray = $result->fetch_assoc();

	$isAnswerExist = $resultArray['result'];

	return !$isAnswerExist;
}
function bot_getCountOptions($vote_id){
	$mysqli = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB);
	$stmt = $mysqli->prepare("SELECT COUNT(`name`) as `count` FROM `vote_option` WHERE `vote_id` = $vote_id ");
	$stmt->execute();
	$result = $stmt->get_result();
	$resultArray = $result->fetch_assoc();
	$count = $resultArray['count'];

	return $count;
}

function bot_filterVoteOption($user_id, $count){
	if ($count < 3){
		$mysqli = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB);
		$stmt = $mysqli->prepare("SELECT * FROM vote_option WHERE id NOT IN (SELECT `option_id` FROM `vote_result` WHERE `user_id` = (SELECT `id` FROM `user` WHERE `vk_user_id` = $user_id))");
		$stmt->execute();
		$result = $stmt->get_result();
		$resultArray = $result->fetch_all();

		bot_sendVoteOption($user_id, $resultArray, $count);
	} else {
		$mysqli = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB);
		$stmt = $mysqli->prepare("UPDATE `vote_user` SET `state` = 3 WHERE `user_id` = (SELECT `id` FROM `user` WHERE `vk_user_id` = $user_id)");
		$stmt->execute();

		$msg = "Выбор сделан!";
		bot_sendMessage($user_id, $msg);

		sleep(1);

		$username = bot_getUserName($user_id);
		$chatId = bot_getUserChatId($user_id);
		
		$msg = $username . " " . "сделал свой выбор!";

		bot_sendMessage($chatId, $msg);
	}


}
function bot_startUserVote($user_id){
	$mysqli = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB);
	$stmt = $mysqli->prepare("UPDATE `vote_user` SET `state` = 2 WHERE `user_id` = (SELECT `id` FROM `user` WHERE `vk_user_id` = $user_id)");
	$stmt->execute();
}

function bot_formVoteOption($user_id){
	if (!bot_isVotingUser($user_id)) return;

	$mysqli = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB);
	$stmt = $mysqli->prepare("SELECT COUNT(`option_id`) AS `count` FROM `vote_result` WHERE `user_id` = (SELECT `id` FROM `user` WHERE `vk_user_id` = $user_id)");
	$stmt->execute();
	$result = $stmt->get_result();
	$resultArray = $result->fetch_assoc();
	
	$count = $resultArray['count'];

	bot_filterVoteOption($user_id, $count);
}

function bot_sendUsersHelloMessage($user_id){
	$msg = "Еще раз здравствуй!
	Как будет обстоять дело:
    Напиши !start
	Я спрошу у тебя 3 раза, за кого ты хочешь проголосовать 
	Сначала твой голос будет стоить 3 очка, потом 2, потом 1
	После чего наше с тобой обсуждение закончится";

	bot_sendMessage($user_id, $msg);
}
function bot_checkNewUser($user_id){
	$mysqli = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB);
	$stmt = $mysqli->prepare("SELECT EXISTS (SELECT * FROM `user` where `vk_user_id` = $user_id) as 'result'");
	$stmt->execute();
	$result = $stmt->get_result();
	$isUserExist = $result->fetch_assoc();

	return !$isUserExist['result'];
}

function bot_createNewUser($user_id, $peer_id){
	$mysqli = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB);
	$stmt = $mysqli->prepare("INSERT INTO `user` (`vk_user_id`,`chat_id`) VALUES ($user_id, $peer_id) ");
	$stmt->execute();

	$mysqli = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DB);
	$stmt = $mysqli->prepare("INSERT INTO `vote_user` (`vote_id`, `user_id`, `state`) VALUES (1, (SELECT `id` from `user` where `vk_user_id` = $user_id), 1) ");
	$stmt->execute();

	bot_sendUsersHelloMessage($user_id);
}

function _bot_uploadPhoto($user_id, $file_name) {
	$upload_server_response = vkApi_photosGetMessagesUploadServer($user_id);
	$upload_response = vkApi_upload($upload_server_response['upload_url'], $file_name);

	$photo = $upload_response['photo'];
	$server = $upload_response['server'];
	$hash = $upload_response['hash'];

	$save_response = vkApi_photosSaveMessagesPhoto($photo, $server, $hash);
	$photo = array_pop($save_response);

	return $photo;
}

function _bot_uploadVoiceMessage($user_id, $file_name) {
	$upload_server_response = vkApi_docsGetMessagesUploadServer($user_id, 'audio_message');
	$upload_response = vkApi_upload($upload_server_response['upload_url'], $file_name);

	$file = $upload_response['file'];

	$save_response = vkApi_docsSave($file, 'Voice message');
	$doc = array_pop($save_response);

	return $doc;
}