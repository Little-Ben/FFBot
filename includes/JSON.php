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

class JSON {
    public $fileName;
    public $dataArr;

    public function __construct($jsonFileName) {
        $this->setFileName($jsonFileName);
    }

    public function readFile() {
        $jsonInput = file_get_contents($this->fileName);
        $this->dataArr = json_decode($jsonInput,true);
    }

    public function getData() {
        return $this->dataArr;
    }

    public function setData($newDataArr) {
        $this->dataArr = $newDataArr;
    }

    public function writeFile() {
        //php >= 5.4 for JSON_PRETTY_PRINT and JSON_UNESCAPED_SLASHES
        $newJsonString = json_encode($this->dataArr, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($this->fileName, $newJsonString);
    }

    public function getFileName() {
        return $this->fileName;
    }

    public function setFileName($fileName) {
        $this->fileName = $fileName;
    }

}

?>
