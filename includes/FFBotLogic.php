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

include('JSON.php');
include('MySqlInterface.php');

class FFWPBotLogic {
    public $config;
    public $alarm;
    public $chat;
    private $db;
    private $version;

    public function __construct($configFile = './config/config.json') {
        $this->config = new JSON($configFile);
        $this->config->readFile();
        //echo "<br>".$configFile;
        //echo "<br>".$this->config->getFileName();
        //echo "<br>config:database:db=" . $this->config->getData()["database"]["db"];
        //echo "<br>config:database:user=" . $this->config->getData()["database"]["user"];
        //echo "<br>";

        $this->doLogDebug("config:database:db=" . $this->config->getData()["database"]["db"]);
        $this->doLogDebug("config:database:user=" . $this->config->getData()["database"]["user"]);
        $this->db = new MySqlInterface($this->config->getData()["database"]["db"],$this->config->getData()["database"]["user"],$this->config->getData()["database"]["pwd"]);
        if (strtolower($this->config->getData()["database"]["verbose"]) == "true") {
            $this->db->setVerboseMode(TRUE);
            $this->doLogDebug("config:database:verbose=true");
        }
        $this->version = "0.5.0";
        $this->doLogDebug("version=" . $this->version);

        if ($this->config->getData()["instances"]["telegram"]["bot-initialized"] == "0") {
            $this->doLogInit("[BOT] initBot gestartet");
            $this->initBot();
        }

        if ($this->config->getData()["database"]["initialized"] == "0") {
            $obj["message"]["from"]["id"]=1;
            $this->doLogInit("[DB] initDB gestartet");
            $this->initDB();
        }
    }

    function parseInput($msgText, $type, $obj="") {
        $crlf = $this->config->getData()["instances"][$type]["linebreak"];
        $linkUrl = $this->config->getData()["instances"][$type]["url"];

        $msgTextOrig = $msgText;
        $msgArray = explode(" ", $msgText,2);
        $msgCmd = strtolower($msgArray[0]);
        $msgParam = $msgArray[1];

        $this->doLogInput($msgTextOrig,$obj);

        if (substr($msgCmd,0,1) == "/" ) {

            switch ($msgCmd) {

                case "/datum":
                    $retText = date("d.m.Y");
                    $this->doLogParse($msgCmd,$obj);
                    break;

                case "/info":
                    $filter = $msgParam;
                    $nodeCount = 0;
                    $clientCount = 0;
                    $offlineCount = 0;
                    $nodesJson = file_get_contents($this->config->getData()["map"]["meshviewer"]["url"]);
                    $nodes = json_decode($nodesJson,true);
                    foreach ($nodes["nodes"] as $node) {
                            if ((strpos(strtolower($node["nodeinfo"]["hostname"]),strtolower($filter)) !== false) OR ($filter == "")) {
                                    $nodeCount = $nodeCount + 1;
                                    $clientCount = $clientCount + $node["statistics"]["clients"];
                                    if ($node["flags"]["online"] == false) {
                                            $offlineCount = $offlineCount +1;
                                    }
                            }
                    }
                    $jsonDate = strftime("%d.%m.%Y %R",strtotime($nodes["timestamp"])+date('Z'));
                    $retText = "Knoten " . ($filter != "" ? "mit '$filter' im Namen" : "im Gesamtnetz") . ":" . $crlf;
                    $retText = $retText . " - Knoten: $nodeCount (online: " . ($nodeCount-$offlineCount) . ", offline: $offlineCount)" . $crlf;
                    $retText = $retText . " - Clients: $clientCount" . $crlf . "Datenstand: $jsonDate";
                    $this->doLogParse($msgCmd,$obj);
                    break;

                case "/kw":
                    $retText = "Kalenderwoche " . strftime("%V",time());
                    $this->doLogParse($msgCmd,$obj);
                    break;

                case "/link":
                    $retText = $linkUrl;
                    $this->doLogParse($msgCmd,$obj);
                    break;

                case "/alarmadd":
                    if ($obj) {
                        $chat_id = $obj["message"]["from"]["id"];
                        $node_pattern = strtolower(trim($this->db->escape($msgParam)));
                        if ($node_pattern != "") {
                            $sqlNodeId = "select min(node_id) MIN_NODE_ID from ffbot_current_nodes ";
                            $sqlNodeId = $sqlNodeId . "where lower(trim(node_id)) = '$node_pattern' ";
                            $sqlNodeId = $sqlNodeId . "or lower(trim(hostname)) like '%$node_pattern%'";
                            $retNodeId = $this->db->queryCell($sqlNodeId);

                            $sqlCntAlarm = "select count(*) from ffbot_alarms where trim(chat_id)=trim('$chat_id') and trim(node_id)='$retNodeId'";
                            $retCntAlarm = $this->db->queryCell($sqlCntAlarm);

                            if ($retCntAlarm == 0) {

                                //falls noch kein Alarm vorhanden, dann Alarm hinzufuegen zu Alarm-Tabelle
                                $sql = "insert into ffbot_alarms (chat_id,node_id) values (trim('$chat_id'),'$retNodeId')";
                                $cnt = $this->db->executeStatement($sql);

                                //pruefen, ob bereits Eintrag in lokaler Knotentabelle vorhanden, ggf ergaenzen
                                $sqlCntNode = "select count(*) from ffbot_node where trim(node_id)='$retNodeId'";
                                $retCntNode = $this->db->queryCell($sqlCntNode);
                                if ($retCntNode == 0) {
                                    $sql = "insert into ffbot_node (node_id,last_state) ";
                                    $sql = $sql . "select node_id,curr_state ";
                                                                    $sql = $sql . "from ffbot_current_nodes c ";
                                                                    $sql = $sql . "where c.node_id='$retNodeId' ";

                                    $cnt = $this->db->executeStatement($sql);
                                }

                                //abschliessend commiten
                                $this->db->commitTrans();

                                $sqlNodeName = "select hostname from ffbot_current_nodes ";
                                $sqlNodeName = $sqlNodeName . "where lower(trim(node_id)) = '$retNodeId' ";
                                $retNodeName = $this->db->unescape($this->db->queryCell($sqlNodeName));

                                $retText = "<b>Knotenalarm</b>" . $crlf . $cnt . " Alarm/e hinzugefügt:" . $crlf . "$retNodeName";

                            }else{
                                $retText = "Alarm bereits vorhanden ($node_pattern).";
                            }
                        }else
                            $retText = "Bitte zusätzlich einen eindeutigen (Teil-)Knotennamen " . $crlf . "oder die NODE_ID angeben.";
                    }else
                        $retText = "Diese Funktion ist nur ueber Telegram moeglich.";
                    $this->doLogParse($msgCmd,$obj);
                    break;

                case "/alarmdel":
                    if ($obj) {
                            $chat_id = $obj["message"]["from"]["id"];
                            $node_pattern = trim($this->db->escape($msgParam));

                        if ($node_pattern != "") {
                            $sqlNodeId = "select min(node_id) MIN_NODE_ID from ffbot_current_nodes ";
                            $sqlNodeId = $sqlNodeId . "where lower(trim(node_id)) = '$node_pattern' ";
                            $sqlNodeId = $sqlNodeId . "or lower(trim(hostname)) like '%$node_pattern%'";
                            $retNodeId = $this->db->unescape($this->db->queryCell($sqlNodeId));

                            $sql = "delete from ffbot_alarms where trim(chat_id)=trim('$chat_id') and trim(node_id)='$retNodeId'";
                            $retText = $this->db->executeStatement($sql);

                                //pruefen, ob Eintrag noch in Alarm-Tabelle vorhanden, falls nein in lokaler Knotentabelle loeschen
                                $sqlCntAlarm = "select count(*) from ffbot_alarms where trim(node_id)='$retNodeId'";
                                $retCntAlarm = $this->db->queryCell($sqlCntAlarm);
                                if ($retCntAlarm == 0) {
                                    $sql = "delete from ffbot_node ";
                                    $sql = $sql . "where node_id='$retNodeId' ";

                                    $cnt = $this->db->executeStatement($sql);
                            }

                            $this->db->commitTrans();

                            $sqlNodeName = "select hostname from ffbot_current_nodes ";
                            $sqlNodeName = $sqlNodeName . "where lower(trim(node_id)) = '$retNodeId' ";
                            $retNodeName = $this->db->unescape($this->db->queryCell($sqlNodeName));

                            $retText = "<b>Knotenalarm</b>" . $crlf  . $retText . " Alarm/e gelöscht:" . $crlf . "$retNodeName"; // . $cnt . " $sql";
                        }else
                            $retText = "Bitte zusätzlich einen eindeutigen (Teil-)Knotennamen " . $crlf . "oder die NODE_ID angeben.";

                    }else
                        $retText = "Diese Funktion ist nur ueber Telegram moeglich.";

                    $this->doLogParse($msgCmd,$obj);
                    break;

                case "/alarmlist":
                    if ($obj) {
                        $chat_id = $obj["message"]["from"]["id"];
                        $cnt = 0;
                        $sql = "select concat(c.hostname, ' (', a.node_id, ')') RES_CONCAT
                            from ffbot_alarms a
                            inner join ffbot_node n
                                on n.node_id=a.node_id
                            inner join ffbot_current_nodes c
                                on c.node_id=a.node_id
                            where chat_id='$chat_id'
                            order by c.hostname,a.node_id";

                        $result = $this->db->queryResult($sql);
                        if ($result) {
                            while($row = $result->fetch_assoc()) {
                                $retText = $retText . $this->db->unescape($row['RES_CONCAT']) . $crlf;
                                $cnt = $cnt +1;
                            }
                            $result->free();
                        }
                        $retText = "<b>$cnt aktive Knotenalarme</b>" . $crlf  . $retText;
                    }else
                        $retText = "Diese Funktion ist nur ueber Telegram moeglich.";

                    $this->doLogParse($msgCmd,$obj);
                    break;

                case "/alarmtest":
                    if ($obj) {
                        $chat_id = $obj["message"]["from"]["id"];
                        $alarm_test_node = $this->config->getData()["instances"]["telegram"]["alarm-test-node"];
                        $admin_id = $this->config->getData()["instances"]["telegram"]["bot-admin-id"];

                        if ($chat_id == $admin_id) {
                            $sql = "update ffbot_node set last_state=0 where node_id='" . $alarm_test_node . "'";
                            $this->doLogDebug($sql);
                            $cnt = $this->db->executeStatement($sql);
                            $this->db->commitTrans();
                            $retText = $cnt . " Alarm/e geändert.";
                        }else
                            $retText = "Diese Funktion kann nur der Bot-Admin ausführen.";

                    }else
                        $retText = "Diese Funktion ist nur ueber Telegram moeglich.";

                    $this->doLogParse($msgCmd,$obj);
                    break;

                case "/stats":
                    $nodeCount = 0;
                    $clientCount = 0;
                    $offlineCount = 0;
                    $onlineCount = 0;

                    //TODO: 1. Dimension des Arrays in config.json auslagern
                    $regions = array (
                            array('AZ-',0,0,0,0),
                            array('-BDH',0,0,0,0),
                            array('BIR-',0,0,0,0),
                            array('Bosen',0,0,0,0),
                            array('Colosseum',0,0,0,0),
                            array('Dansenberg',0,0,0,0),
                            array('Eisenberg',0,0,0,0),
                            array('Goellheim',0,0,0,0),
                            array('Jaenisch',0,0,0,0),
                            array('Katzweiler',0,0,0,0),
                            array('-KH',0,0,0,0),
                            array('KIB-',0,0,0,0),
                            array('KL-',0,0,0,0),
                            array('KUS-',0,0,0,0),
                            array('Mackenbach',0,0,0,0),
                            array('Otterbach',0,0,0,0),
                            array('Otterberg',0,0,0,0),
                            array('PS-',0,0,0,0),
                            array('-QB-Hauptstr',0,0,0,0),
                            array('-QB-Helle',0,0,0,0),
                            array('ROK-',0,0,0,0),
                            array('-SK-',0,0,0,0),
                            array('Ulmet',0,0,0,0),
                            array('WP-',0,0,0,0),
                            array('WTW',0,0,0,0),
                            array('Zellertal',0,0,0,0)
                    );

                    $c = count($regions);

                    $nodesJson = file_get_contents($this->config->getData()["map"]["meshviewer"]["url"]);
                    $nodes = json_decode($nodesJson,true);
                    foreach ($nodes["nodes"] as $node) {
                        $nodeName = strtolower($node["nodeinfo"]["hostname"]);

                        //Gesamtzahl ermitteln
                        $nodeCount = $nodeCount + 1;
                        $clientCount = $clientCount + $node["statistics"]["clients"];
                        if ($node["flags"]["online"] == false)
                            $offlineCount = $offlineCount +1;
                        else
                            $onlineCount = $onlineCount +1;

                        for($i = 0; $i < $c; $i++) {
                            if (strpos($nodeName,strtolower($regions[$i][0])) !== false) {
                                    $regions[$i][1] = $regions[$i][1] + 1;

                                    if ($node["flags"]["online"] == false)
                                            $regions[$i][3] = $regions[$i][3] +1;
                                    else
                                            $regions[$i][2] = $regions[$i][2] +1;

                                    $regions[$i][4] = $regions[$i][4] + $node["statistics"]["clients"];
                            }
                        }
                    }

                    $retText = "Filter        Kn   on  off Cli" . $crlf;
                    $retText = $retText . "-------------------------------" . $crlf;

                    for($i = 0; $i < $c; $i++) {
                        $retText = $retText . str_pad($regions[$i][0],14) . str_pad($regions[$i][1],5) . str_pad($regions[$i][2],4) . str_pad($regions[$i][3],4) . str_pad($regions[$i][4],4) . $crlf;
                    }

                    $jsonDate = strftime("%d.%m.%Y %R",strtotime($nodes["timestamp"])+date('Z'));
                    $retText = $retText . "-------------------------------" . $crlf;

                    $retText = $retText  . str_pad("Gesamt",14) . str_pad($nodeCount,5) . str_pad($onlineCount,4) . str_pad($offlineCount,4) . str_pad($clientCount,4) . $crlf;
                    $retText = $retText . $crlf . "Datenstand:    ". $jsonDate;
                    $retText = "<code>" . $retText . "</code>";

                    $this->doLogParse($msgCmd,$obj);
                    break;

                case "/sag":
                    $retText = $msgParam;

                    $this->doLogParse($msgCmd,$obj);
                    break;

                case "/start":
                    $retText = "Willkommen, ich bin <b>" . $this->config->getData()["bot"]["name"] . "</b>." . $crlf . $crlf;
                    $retText = $retText . "Gerne helfe ich Dir bei Fragen zum Netz von <b>" . $this->config->getData()["community"]["full_name"] . "</b>. ";
                    $retText = $retText . "Was ich alles kann, sag ich Dir unter /hilfe. Diese und weitere Funktionen findest Du auch in dem '/'-Symbol in der Eingabeleiste. " . $crlf . $crlf;

                    $retText = $retText . "Weitere Informationen zum Projekt findest Du unter " . $this->config->getData()["community"]["url"]. "." . $crlf . $crlf;
                    $retText = $retText . "Viel Spaß - und funk frei!";

                    $this->doLogParse($msgCmd,$obj);
                    break;

                case "/tag":
                    $retText = strftime("%A",time());

                    $this->doLogParse($msgCmd,$obj);
                    break;

                case "/version":
                    $retText = "FFBot V." . $this->version . $crlf;
                    $retText = $retText . "by Little.Ben" . $crlf;
                    $retText = $retText . "https://github.com/Little-Ben/FFBot" . $crlf;

                    $this->doLogParse($msgCmd,$obj);
                    break;

                case "/zeit":
                    $retText = date("H:i:s");

                    $this->doLogParse($msgCmd,$obj);
                    break;

                default:
                    $retText = "Hallo, ich bin " . $this->config->getData()["bot"]["name"] . "." . $crlf;
                    $retText = $retText . "Gerne informiere ich Dich über das Netz von " . $this->config->getData()["community"]["full_name"] . "." . $crlf . $crlf;

                    $retText = $retText . "<b>Das kannst Du mich fragen:</b>" . $crlf;
                    //$retText = $retText . "/sag Text - Papageifunktion" . $crlf;
                    $retText = $retText . "/info Knotenname - Infos zum Knoten" . $crlf;
                    $retText = $retText . "/stats - Zählungen" . $crlf;
                    $retText = $retText . "/link - URL zur Weitergabe" . $crlf;
                    $retText = $retText . "/version - Infos zu Version und Autor" . $crlf;
                    $retText = $retText . "/hilfe - diese Hilfe" . $crlf;

                    $retText = $retText . $crlf;
                    $retText = $retText . "<b>Knotenalarm</b>" . $crlf;
                    $retText = $retText . "Zusätzlich informiere ich Dich gerne, wenn einer Deiner Knoten seinen Status ändert." . $crlf . $crlf;
                    $retText = $retText . "Dazu gibt es folgende Kommandos:" . $crlf;
                    $retText = $retText . "/alarmAdd KN/ID - Alarm hinzufügen" . $crlf;
                    $retText = $retText . "/alarmDel KN/ID - Alarm entfernen" . $crlf;
                    $retText = $retText . "/alarmList - aktive Alarme zeigen" . $crlf;
                    $retText = $retText . $crlf;
                    $retText = $retText . "<i>KN/ID</i>: KN (Knotenname) ist der Name Deines Knotens oder ein eindeutiger Teilbereich daraus, ";
                    $retText = $retText . "ID ist seine eindeutige NODE_ID. Es kann entweder der KN oder die ID angegeben werden." . $crlf;
                    $retText = $retText . $crlf;
                    $retText = $retText . "Die Alarmierung benötigt ungefähr 5-10min. nach Statusänderung (online/offline)." . $crlf;
                    $retText = $retText . $crlf;
                    $retText = $retText . "Der Knotenalarm befindet sich in der Testphase." . $crlf;

                    $retText = $retText . $crlf;
                    $retText = $retText . "<b>Weitere nützliche Kommandos</b>" . $crlf;
                    $retText = $retText . "/tag - aktueller Wochentag" . $crlf;
                    $retText = $retText . "/zeit - aktuelle Uhrzeit" . $crlf;
                    $retText = $retText . "/datum - aktuelles Datum" . $crlf;
                    $retText = $retText . "/kw - aktuelle Kalenderwoche" . $crlf;
                    $this->doLogParse($msgCmd,$obj);

            } //switch

        }else{
            $retText = "gültige Kommandos findest Du unter /hilfe";
            $this->doLogParse("_invalid",$obj);
        }

        $this->doLogOutput($retText,$obj,$crlf);
        return $retText;
    }

    function saveAllJsonNodesToDB($tableName = "ffbot_current_nodes") {
        $nodesJson = file_get_contents($this->config->getData()["map"]["meshviewer"]["url"]);
        $nodes = json_decode($nodesJson,true);
        $sql = "";
        $nodeId = "n/a";
        $nodeCurrState = -1;

        $this->db->beginTrans();
        $this->db->executeStatement("delete from " . $tableName);

        foreach ($nodes["nodes"] as $node) {
            $hostName = $this->db->escape(($node["nodeinfo"]["hostname"]));
            $nodeId = strtolower($this->db->escape($node["nodeinfo"]["node_id"]));

            if ($node["flags"]["online"] == false)
                $nodeCurrState = 0;
            else
                $nodeCurrState = 1;

            $sql = $sql . ",('$nodeId','$hostName',$nodeCurrState)\n";
        }

        $sql = "insert into " . $tableName . "(node_id,hostname,curr_state) values\n" . substr($sql,1);

        $this->db->executeStatement($sql);

        if ($this->db->getAffectedRows() > 0 ) {
            $this->doLogRead("imported successfully " . $this->db->getAffectedRows() . " nodes from json to db");
            $this->db->commitTrans();
        }else{
            $this->db->rollbackTrans();
            $this->doLogError("nodes.json import ERROR");
        }
    }

    function checkForNodeAlarms() {
        $i=0;
        $result = $this->db->queryResult("
            select
                a.chat_id,
                a.node_id,
                c.hostname,
                n.last_state,
                c.curr_state
            from ffbot_alarms a
            inner join ffbot_node n
                on a.node_id=n.node_id
            inner join ffbot_current_nodes c
                on a.node_id=c.node_id
                and c.curr_state<>n.last_state
            order by node_id,chat_id");

        if ($result) { //if result
            while($row = $result->fetch_assoc()){
                //echo $row['chat_id'] . ";" . $this->db->unescape($row['hostname']) . ";" . $row['curr_state'] . "\n";

                $retArr[$i][0]=$row['chat_id'];
                $retArr[$i][1]=$this->db->unescape($row['hostname']);
                $retArr[$i][2]=$row['curr_state'];
                $retArr[$i][3]=$this->db->unescape($row['node_id']);
                $this->doLogNotify($retArr[$i][0]."/".$retArr[$i][1]."/".$retArr[$i][2]."/".$retArr[$i][3],$retArr[$i][0]);
                $i+=1;

            }
            $result->free();
        } //if result
        else
        {
            // if result is empty (no alarms),
            // create empty element (needs to be check in executing function)
            $retArr[$i][0]="";
            $retArr[$i][1]="";
            $retArr[$i][2]="";
            $retArr[$i][3]="";
        }
        return $retArr;
    }

    function updateNodeState($node_id,$name,$curr_state) {
        $sql = "update ffbot_node set last_state='$curr_state' where node_id='$node_id'";
        echo $sql;
        $this->db->executeStatement($sql);

        if ($this->db->getAffectedRows() > 0 )
            $this->db->commitTrans();
        else
            $this->db->rollbackTrans();
    }

    function doLog($msgText,$obj) {
        $logfile = $this->config->getData()["bot"]["logfile"];
        if ($obj) {
            $chat_id = $obj["message"]["from"]["id"];
        }else{
            $chat_id = "WEB     ";
        }
        $chat_id = str_pad($chat_id,10);
        $d = new DateTime();
        $inhalt = $d->format("Y-m-d H:i:s") . "\t " . $chat_id . "\t " . $msgText . "\n";

        $handle = fopen ($logfile, "a");
        fwrite ($handle, $inhalt);
        fclose ($handle);
    }

    function doLogDebug($msgText) {
        $loglevel = "x".strtoupper($this->config->getData()["bot"]["loglevel"]);
        $obj["message"]["from"]["id"]=1;
        if (strpos($loglevel,"D") > 0)
            $this->doLog("debug\t " . $msgText,$obj);
    }

    function doLogError($msgText) {
        $obj["message"]["from"]["id"]=1;
        $this->doLog("ERROR\t " . $msgText,$obj);
    }

    function doLogInit($msgText) {
        $obj["message"]["from"]["id"]=1;
        $this->doLog("init\t " . $msgText,$obj);
    }

    function doLogInput($msgText,$obj) {
        $loglevel = "x".strtoupper($this->config->getData()["bot"]["loglevel"]);
        if (strpos($loglevel,"I") > 0)
            $this->doLog("input\t " . $msgText,$obj);
    }

    function doLogNotify($msgText,$id) {
        $loglevel = "x".strtoupper($this->config->getData()["bot"]["loglevel"]);
        $obj["message"]["from"]["id"]=$id;
        if (strpos($loglevel,"N") > 0)
            $this->doLog("notify\t " . $msgText,$obj);
    }

    function doLogParse($msgText,$obj) {
        $loglevel = "x".strtoupper($this->config->getData()["bot"]["loglevel"]);
        if (strpos($loglevel,"P") > 0)
            $this->doLog("parse\t " . substr($msgText,1),$obj);
    }

    function doLogRead($msgText) {
        $loglevel = "x".strtoupper($this->config->getData()["bot"]["loglevel"]);
        $obj["message"]["from"]["id"]=1;
        if (strpos($loglevel,"R") > 0)
            $this->doLog("read\t " . $msgText,$obj);
    }

    function doLogOutput($msgText,$obj,$crlf) {
        $msgTextOut = substr(str_replace("\t","[TAB]",str_replace($crlf,"[CRLF]",$msgText)),0,145);
        $loglevel = "x".strtoupper($this->config->getData()["bot"]["loglevel"]);
        if (strpos($loglevel,"O") > 0)
            $this->doLog("output\t " . $msgTextOut,$obj);
    }

    function getUrl($url) {
        $content = file_get_contents($url);
        return $content;
    }

    function initBot() {
        $telegramApiKey = $this->config->getData()["instances"]["telegram"]["apikey"];
        $telegramBotUrl = $this->config->getData()["instances"]["telegram"]["bot-backend-url"];

        //Webhook registrieren
        $this->doLogInit("[BOT] Telegram, Webhook registrieren: " . $telegramBotUrl);
        $content = $this->getUrl("https://api.telegram.org/bot". $telegramApiKey ."/setWebhook?url=" . urlencode($telegramBotUrl));
        $this->doLogInit("[BOT] Telegram, setWebhook, Ergebnis: " . $content);

        //TODO: Result-JSON parsen
        if (strpos("x".$content,'"ok":true') > 0) {
            $data=$this->config->getData();
            $data["instances"]["telegram"]["bot-initialized"] = "1";
            $this->config->setData($data);
            $this->config->writeFile();
            $this->doLogInit("[CONFIG] setze instances.telegram.bot-initialized=1");
            $this->config->readFile();
        }
    }

    function initDB() {
        //only change tabPrefix for debugging purposes of initDB
        //the rest of the code needs 'ffbot' as tabPrefix !!!
        $tabPrefix = "ffbot";

        $sql = "CREATE TABLE IF NOT EXISTS " . $tabPrefix . "_alarms ( ";
        $sql = $sql . "chat_id varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL, ";
        $sql = $sql . "node_id varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL ";
        $sql = $sql . ") ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ";
        if (!$this->db->queryResult($sql)) {
            $this->doLogError("[DB] Tabelle " . $tabPrefix . "_alarms anlegen: Fehler " . $this->db->error());
            die('Error: ' . $this->db->error());
        }else
            $this->doLogInit("[DB] Tabelle " . $tabPrefix . "_alarms anlegen: erfolgreich");

        $sql = "CREATE TABLE IF NOT EXISTS " . $tabPrefix . "_current_nodes ( ";
        $sql = $sql . "node_id varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL, ";
        $sql = $sql . "hostname varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL, ";
        $sql = $sql . "curr_state int(11) DEFAULT NULL ";
        $sql = $sql . ") ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ";
        if (!$this->db->queryResult($sql)) {
            $this->doLogError("[DB] Tabelle " . $tabPrefix . "_current_nodes anlegen: Fehler " . $this->db->error());
            die('Error: ' . $this->db->error());
        }else
            $this->doLogInit("[DB] Tabelle " . $tabPrefix . "_current_nodes anlegen: erfolgreich");

        $sql = "CREATE TABLE IF NOT EXISTS " . $tabPrefix . "_node ( ";
        $sql = $sql . "node_id varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL, ";
        $sql = $sql . "last_state int(11) DEFAULT NULL ";
        $sql = $sql . ") ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ";
        if (!$this->db->queryResult($sql)) {
            $this->doLogError("[DB] Tabelle " . $tabPrefix . "_node anlegen: Fehler " . $this->db->error());
            die('Error: ' . $this->db->error());
        }else
            $this->doLogInit("[DB] Tabelle " . $tabPrefix . "_node anlegen: erfolgreich");

        $sql = "CREATE TABLE IF NOT EXISTS " . $tabPrefix . "_settings ( ";
        $sql = $sql . "s_key varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL, ";
        $sql = $sql . "s_value varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL ";
        $sql = $sql . ") ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ";
        if (!$this->db->queryResult($sql)) {
            $this->doLogError("[DB] Tabelle " . $tabPrefix . "_settings anlegen: Fehler " . $this->db->error());
            die('Error: ' . $this->db->error());
        }else
            $this->doLogInit("[DB] Tabelle " . $tabPrefix . "_settings anlegen: erfolgreich");

        //falls leere settings-Tabelle, lege Zeile mit Version=1 an
        $sqlCntSettings = "select count(*) from " . $tabPrefix . "_settings";
        $retCntSettings = $this->db->queryCell($sqlCntSettings);
        if ($retCntSettings == 0) {
            $sql = "insert into " . $tabPrefix . "_settings(s_key,s_value) values ('version','1') ";
            $this->db->beginTrans();
            $this->db->executeStatement($sql);
        }

        //falls settings-Tabelle eine Version enthaelt, ist db initialisiert, Config-flag setzen
        $sqlSettingsVersion = "select s_value from " . $tabPrefix . "_settings where s_key='version'";
        $retSettingsVersion = $this->db->queryCell($sqlSettingsVersion);
        $this->doLogInit("[DB] Settings Version=" . $retSettingsVersion);
        if (trim($retSettingsVersion) != "") {

            //DB-Transaktion commiten
            $this->db->commitTrans();

            //nodes.json erstmalig abziehen
            $this->saveAllJsonNodesToDB($tabPrefix . "_current_nodes");

            //Config-Flag setzen
            $data=$this->config->getData();
            $data["database"]["initialized"] = "1";
            $this->config->setData($data);
            $this->config->writeFile();
            $this->doLogInit("[CONFIG] setze database.initialized=1");
            $this->config->readFile();
        }else{
            $this->db->rollbackTrans();
            $this->doLogError("[CONFIG] ungueltige DB-Version in Settings, bitte DB-Struktur manuell pruefen oder " . $tabPrefix. "* Tabellen loeschen (drop) und neu starten");
        }
    }


} //Ende Klasse


?>
