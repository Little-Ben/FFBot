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
$bot = new TelegramBot($logic->config->getData()["instances"]["telegram"]["apikey"]);

$logic->saveAllJsonNodesToDB();

$obj = new ArrayObject($logic->checkForNodeAlarms());
$it = $obj->getIterator();

while( $it->valid() ) {
    if ($it->current()[0]!="") {
        //echo $it->key() . "[0]=" . $it->current()[0] . "\n";  //chat_id
        //echo $it->key() . "[1]=" . $it->current()[1] . "\n";  //node_name
        //echo $it->key() . "[2]=" . $it->current()[2] . "\n";  //curr_state
        //echo $it->key() . "[3]=" . $it->current()[3] . "\n";  //node_id

        $node_state = ($it->current()[2] == 1) ? "online" : "OFFLINE";
        $bot->sendMessage($it->current()[0],"<b>Knotenalarm</b> \n"
        . "<a href='".$logic->config->getData()["map"]["meshviewer"]["url_nodeinfo"].$it->current()[3]."'>".$it->current()[1]."</a>"
        . " ist jetzt <code>"
        . $node_state . "</code>."
        );
        $logic->updateNodeState($it->current()[3],$it->current()[1],$it->current()[2]);
    }
    $it->next();
}


?>
