#!/usr/bin/php
<?php
/*
        This file is part of Warzone 2100 lobby server created by EuPhobos.

        Warzone 2100 lobby server is free software; you can redistribute it and/or modify
        it under the terms of the GNU General Public License as published by
        the Free Software Foundation; either version 2 of the License, or
        (at your option) any later version.

        Warzone 2100 lobby server is distributed in the hope that it will be useful,
        but WITHOUT ANY WARRANTY; without even the implied warranty of
        MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
        GNU General Public License for more details.

        You should have received a copy of the GNU General Public License
        along with Warzone 2100; if not, write to the Free Software
        Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
*/


/*
php-cli >= 5.4 || php-cli >= 7.0
[php-psql]
[php-sqlite]

Лобби сервер для игры warzone2100.

Лицензия GPLv2
*/



function out($msg){global $logPath, $gameid;print "$gameid:$msg\n";file_put_contents($logPath, time().":$gameid:$msg\n", FILE_APPEND);}
$id = 0;$gameid = 0;
include("sys/config.php");
include("sys/lobby_functions.php");
include("sys/lobby_main.php");

if(($storage['postgres']+$storage['sqlite']+$storage['filesystem'])!=1){out("Select one storage engine");exit();}

include("sys/lobby_pgsql.php");
include("sys/lobby_sqlite.php");
include("sys/lobby_storage.php");
include("sys/socket_engine.php");





?>
