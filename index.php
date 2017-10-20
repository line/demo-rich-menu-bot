<?php

// Using shell_exec for better understandability. Use cURL or other http client for production environment.

require_once __DIR__ . '/vendor/autoload.php';

$httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient(getenv('CHANNEL_ACCESS_TOKEN'));
$bot = new \LINE\LINEBot($httpClient, ['channelSecret' => getenv('CHANNEL_SECRET')]);
$signature = $_SERVER['HTTP_' . \LINE\LINEBot\Constant\HTTPHeader::LINE_SIGNATURE];

$events = $bot->parseEventRequest(file_get_contents('php://input'), $signature);
foreach ($events as $event) {

  if ($event instanceof \LINE\LINEBot\Event\MessageEvent) {
    if($event instanceof \LINE\LINEBot\Event\MessageEvent\TextMessage) {

      if($event->getText() === 'create') {
        $bot->replyMessage($event->getReplyToken(),new \LINE\LINEBot\MessageBuilder\TextMessageBuilder(createNewRichmenu(getenv('CHANNEL_ACCESS_TOKEN'))));
      }
      else if($event->getText() === 'list') {
        $result = getListOfRichmenu(getenv('CHANNEL_ACCESS_TOKEN'));

        if(isset($result['richmenus']) && count($result['richmenus']) > 0) {
          $builders = new \LINE\LINEBot\MessageBuilder\MultiMessageBuilder();
          $columns = Array();
          for($i = 0; $i < count($result['richmenus']); $i++) {
            $richmenu = $result['richmenus'][$i];
            $actionArray = array();
            array_push($actionArray, new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder (
              'upload image', 'upload::' . $richmenu['richMenuId']));
            array_push($actionArray, new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder (
              'delete', 'delete::' . $richmenu['richMenuId']));
            array_push($actionArray, new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder (
              'link', 'link::' . $richmenu['richMenuId']));
            $column = new \LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselColumnTemplateBuilder (
              null,
              $richmenu['richMenuId'],
              null,
              $actionArray
            );
            array_push($columns, $column);

            if($i == 4 || $i == count($result['richmenus']) - 1) {
              $builder = new \LINE\LINEBot\MessageBuilder\TemplateMessageBuilder(
                'Richmenu',
                new \LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselTemplateBuilder($columns)
              );
              $builders->add($builder);

              unset($columns);
              $columns = Array();
            }
          }
          $bot->replyMessage($event->getReplyToken(), $builders);
        }
        else {
          $bot->replyMessage($event->getReplyToken(),new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('No richmenu.'));
        }
      }
      else if($event->getText() === 'unlink') {
        $bot->replyMessage($event->getReplyToken(),new \LINE\LINEBot\MessageBuilder\TextMessageBuilder(unlinkFromUser(getenv('CHANNEL_ACCESS_TOKEN'), $event->getUserId())));
      }
      else if($event->getText() === 'check') {
        $bot->replyMessage($event->getReplyToken(),new \LINE\LINEBot\MessageBuilder\TextMessageBuilder(checkRichmenuOfUser(getenv('CHANNEL_ACCESS_TOKEN'), $event->getUserId())));
      }
      else if(substr($event->getText(),0, 8) === 'upload::') {
        $bot->replyMessage($event->getReplyToken(),new \LINE\LINEBot\MessageBuilder\TextMessageBuilder(uploadRandomImageToRichmenu(getenv('CHANNEL_ACCESS_TOKEN'), substr($event->getText(), 8))));
      }
      else if(substr($event->getText(),0, 8) === 'delete::') {
        $bot->replyMessage($event->getReplyToken(),new \LINE\LINEBot\MessageBuilder\TextMessageBuilder(deleteRichmenu(getenv('CHANNEL_ACCESS_TOKEN'), substr($event->getText(), 8))));
      }
      else if(substr($event->getText(),0, 6) === 'link::') {
        $bot->replyMessage($event->getReplyToken(),new \LINE\LINEBot\MessageBuilder\TextMessageBuilder(linkToUser(getenv('CHANNEL_ACCESS_TOKEN'), $event->getUserId(), substr($event->getText(), 6))));
      }
      else {
        $bot->replyMessage($event->getReplyToken(),new \LINE\LINEBot\MessageBuilder\TextMessageBuilder(
          '"create" - create new Richmenu to channel.' . PHP_EOL .
          '"list" - show all Richmenu created via API' . PHP_EOL .
          '"list > upload" - upload image to Richmenu. Image choosen randomly' . PHP_EOL .
          '"list > delete" - delete Richmenu' . PHP_EOL .
          '"list > link" - link Richmenu to user(you)' . PHP_EOL .
          '"unlink" - remove link to Richmenu of user(you)' . PHP_EOL .
          '"check" - show Richmenu ID linked to user(you)' . PHP_EOL
        ));
      }
    }
  }
}

function createNewRichmenu($channelAccessToken) {
  $sh = <<< EOF
  curl -X POST \
  -H 'Authorization: Bearer $channelAccessToken' \
  -H 'Content-Type:application/json' \
  -d '{"size": {"width": 2500,"height": 1686},"selected": false,"name": "Controller","chatBarText": "Controller","areas": [{"bounds": {"x": 551,"y": 325,"width": 321,"height": 321},"action": {"type": "message","text": "up"}},{"bounds": {"x": 876,"y": 651,"width": 321,"height": 321},"action": {"type": "message","text": "right"}},{"bounds": {"x": 551,"y": 972,"width": 321,"height": 321},"action": {"type": "message","text": "down"}},{"bounds": {"x": 225,"y": 651,"width": 321,"height": 321},"action": {"type": "message","text": "left"}},{"bounds": {"x": 1433,"y": 657,"width": 367,"height": 367},"action": {"type": "message","text": "btn b"}},{"bounds": {"x": 1907,"y": 657,"width": 367,"height": 367},"action": {"type": "message","text": "btn a"}}]}' https://api.line.me/v2/bot/richmenu;
EOF;
  $result = json_decode(shell_exec(str_replace('\\', '', str_replace(PHP_EOL, '', $sh))), true);
  if(isset($result['richMenuId'])) {
    return $result['richMenuId'];
  }
  else {
    return $result['message'];
  }
}

function getListOfRichmenu($channelAccessToken) {
  $sh = <<< EOF
  curl \
  -H 'Authorization: Bearer $channelAccessToken' \
  https://api.line.me/v2/bot/richmenu/list;
EOF;
  $result = json_decode(shell_exec(str_replace('\\', '', str_replace(PHP_EOL, '', $sh))), true);
  return $result;
}

function checkRichmenuOfUser($channelAccessToken, $userId) {
  $sh = <<< EOF
  curl \
  -H 'Authorization: Bearer $channelAccessToken' \
  https://api.line.me/v2/bot/user/$userId/richmenu
EOF;
  $result = json_decode(shell_exec(str_replace('\\', '', str_replace(PHP_EOL, '', $sh))), true);
  if(isset($result['richMenuId'])) {
    return $result['richMenuId'];
  }
  else {
    return $result['message'];
  }
}

function unlinkFromUser($channelAccessToken, $userId) {
  $sh = <<< EOF
  curl -X DELETE \
  -H 'Authorization: Bearer $channelAccessToken' \
  https://api.line.me/v2/bot/user/$userId/richmenu
EOF;
  $result = json_decode(shell_exec(str_replace('\\', '', str_replace(PHP_EOL, '', $sh))), true);
  if(isset($result['message'])) {
    return $result['message'];
  }
  else {
    return 'success';
  }
}

function deleteRichmenu($channelAccessToken, $richmenuId) {
  if(!isRichmenuIdValid($richmenuId)) {
    return 'invalid richmenu id';
  }
  $sh = <<< EOF
  curl -X DELETE \
  -H 'Authorization: Bearer $channelAccessToken' \
  https://api.line.me/v2/bot/richmenu/$richmenuId
EOF;
  $result = json_decode(shell_exec(str_replace('\\', '', str_replace(PHP_EOL, '', $sh))), true);
  if(isset($result['message'])) {
    return $result['message'];
  }
  else {
    return 'success';
  }
}

function linkToUser($channelAccessToken, $userId, $richmenuId) {
  if(!isRichmenuIdValid($richmenuId)) {
    return 'invalid richmenu id';
  }
  $sh = <<< EOF
  curl -X POST \
  -H 'Authorization: Bearer $channelAccessToken' \
  -H 'Content-Length: 0' \
  https://api.line.me/v2/bot/user/$userId/richmenu/$richmenuId
EOF;
  $result = json_decode(shell_exec(str_replace('\\', '', str_replace(PHP_EOL, '', $sh))), true);
  if(isset($result['message'])) {
    return $result['message'];
  }
  else {
    return 'success';
  }
}

function uploadRandomImageToRichmenu($channelAccessToken, $richmenuId) {
  if(!isRichmenuIdValid($richmenuId)) {
    return 'invalid richmenu id';
  }
  $randomImageIndex = rand(1, 5);
  $imagePath = realpath('') . '/' . 'controller_0' . $randomImageIndex . '.png';
  $sh = <<< EOF
  curl -X POST \
  -H 'Authorization: Bearer $channelAccessToken' \
  -H 'Content-Type: image/png' \
  -H 'Expect:' \
  -T $imagePath \
  https://api.line.me/v2/bot/richmenu/$richmenuId/content
EOF;
  $result = json_decode(shell_exec(str_replace('\\', '', str_replace(PHP_EOL, '', $sh))), true);
  if(isset($result['message'])) {
    return $result['message'];
  }
  else {
    return 'success. Image #0' . $randomImageIndex . ' has uploaded onto ' . $richmenuId;
  }
}

function isRichmenuIdValid($string) {
  if(preg_match('/^[a-zA-Z0-9-]+$/', $string)) {
    return true;
  } else {
    return false;
  }
}

?>
