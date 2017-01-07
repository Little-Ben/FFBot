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

class TelegramBot {
    protected $authKey;
    protected $baseUrl;

    public function __construct($auth_key) {
        $this->authKey = $auth_key;
        $this->baseUrl = "https://api.telegram.org/bot";
    }

    public function sendMessage($chat_id, $text, $wait=false) {

    if ($wait == true)
        $this->sendChatAction($chat_id,"typing");

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$this->baseUrl . $this->authKey . "/sendMessage");
        curl_setopt($ch, CURLOPT_POST, 1);
        //curl_setopt($ch, CURLOPT_POSTFIELDS, "chat_id=" . $chat_id . "&text=" . $text );
        curl_setopt($ch, CURLOPT_POSTFIELDS, "chat_id=" . $chat_id . "&text=" . $text . "&parse_mode=HTML" );
        // receive server response ...
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec ($ch);
        curl_close ($ch);
    }

    public function sendChatAction($chat_id, $action) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$this->baseUrl . $this->authKey . "/sendChatAction");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "chat_id=" . $chat_id . "&action=" . $action );
        // receive server response ...
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec ($ch);
        curl_close ($ch);
    }
}

?>

