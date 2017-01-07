<?php
/*
    This file is part of FFBot

    Copyright (C) 2016-2017 Benjamin Schmitt

    FFBot is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    FFBot is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with FFBot. If not, see <http://www.gnu.org/licenses/>.
*/

include('includes/FFBotLogic.php');
include('includes/TelegramBot.php');
setlocale(LC_ALL,"de_DE");

$logic = new FFWPBotLogic();

function outputHtmlForm($logic) {
        include('includes/template.html');
}

// If the form has been sent, we need to handle the data.
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $msg = file_get_contents('php://input');
    $obj=json_decode($msg,true);

    $retText = $logic->parseInput($obj["message"]["text"],"telegram",$obj);

    $bot = new TelegramBot($logic->config->getData()["instances"]["telegram"]["apikey"]);
    $bot->sendMessage($obj["message"]["from"]["id"],$retText,true);

}else{
    outputHtmlForm($logic);
    if (isset($_GET["command"])){
        echo $logic->parseInput(urldecode($_GET["command"]),"html") . "<br>";
    }
}

?>
