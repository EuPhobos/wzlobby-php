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

//Example function to compile "Message of the day" string adding some statistics.
function getMOTD($type='lobby'){
global $MOTD;

	//Return MOTD string for lobby
	if($type == 'lobby') return $MOTD[rand(0,count($MOTD)-1)];

	//Else return MOTD string in gamelist screen.
	$maps = storageGetMapTop();
	$hoster = storageGetHosterTop();
	
	$motd = '';
	
	if($maps) $motd .= "Today very popular map ".$maps['top']." hosted ".$maps['freq']." times\n";
	if($hoster) $motd.= "Today very active hoster player ".$hoster['top'].", they hosted ".$hoster['freq']." times";
	
	if(!$motd) return $MOTD[rand(0,count($MOTD)-1)];

	return $motd;
}

//Проеряет доступность публичного адреса игрока-хостера
function checkAvail($client){
	$address = $client['hostip'];
	$port = $client['hostport'];
	$clsock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	if ($clsock === false) {
		out("Не удалось выполнить socket_create(): причина: " . socket_strerror(socket_last_error()));
	}
	$result = @socket_connect($clsock, $address, $port);
	if ($result === false) {
		out("ERR:Master $address:$port unavailable");
		socket_close($clsock);
		return false;
	}
	socket_close($clsock);
	return true;
}

//Возвращает игры с удалённого мастер-лобби сервера
function getMasterGames(){
global $proxy, $GAMESTRUCT_VERSION;
	$masters = explode(',',$proxy['master']);
	$games = [];
	
	//Для каждого мастер-сервера
	foreach($masters as $master){

		$port = 0;
		//Если указан порт вручную
		if(strstr($master, ':'))list($master,$port) = explode(':',$master);
		if(!$port || $port > 65535) $port = $proxy['port'];
		$address = gethostbyname($master);
		
		$msock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if ($msock === false) {
			out("Не удалось выполнить socket_create(): причина: " . socket_strerror(socket_last_error()));
		}
		$result = @socket_connect($msock, $address, $port);
		if ($result === false) {
			out("ERR:Master $address:$port unavailable");
			socket_close($msock);
			continue;
		}
		$in = "list";
		socket_write($msock, $in, strlen($in));
		
		$num = unpack('N', socket_read($msock, 4, PHP_BINARY_READ))[1];
		
		
		for($i=0;$i<$num;$i++){
			$sver = trim(unpack('N', socket_read($msock, 4, PHP_BINARY_READ))[1]);
			if($GAMESTRUCT_VERSION == $sver){
				$game = [];
				$game = readGameStruct3($msock);
				$game['master'] = "t";
				$game['private']=(($game['private'])?'t':'f');
				$game['mapmod']=(($game['mapmod'])?'t':'f');
				$games[] = $game;
			}
		}

		socket_close($msock);
	}
	
//	print "DEBUG: getMasterGames()";
//	print_r($games);
	
	return $games;
}

//Не усложняем тут, просто возвращает GameID (нужно изменить эту логику в сл. версии gameStruct протокола)
function getGameId(){
	//в GameID используется беззнаковый 32битный, а это 4,294,967,295, однако в SQL они знаковые, а это половина.
	//Трюк заключается в том, что бы проверить, если месяц чётный, игры начинаются с большего числа
	//если не чётный то откатываются на начало отсчёта.
	//Это сделано для того, что бы игры между собой не конфликтовали, и была оптимизированная ротация GameID без лишних циклов и проверок,
	//Большое число - что бы не было переполнения GameID, даже если попытаются заспамить лобби созданием игры.
	//Ну и конечно же, хорошо бы настроить iptables/nftables на хосте, что бы предотвращать атаку спамом на лобби.
//	if(date("m")%2==0)$game = 1000000;
	//Если сменился месяц на чётный
//	if($last < $game) return ($game+1);
	//Если сменился месяц на чётный
//	if($last > ($game+1000000)) return ($game+1);


	//Хрень всё что выше, т.к. протокол должен предоставить GameID ДО авторизации клиента игры!
	//Делаем всё максимально просто, иначе могу заспамить тупо с телнета, без создания игры.
	if(($last=storageGetLastGameId())!==false)return ($last+1);
	out("ERR:Cannot get last gameId");
	return false;
	
	
}

//Фильтруем игры, отсеиваем то, что не нужно выводить в листинг лобби
function filterOpenedGames($games){
global $hide;
	$out = [];
	
//	var_dump($games);
	
	foreach($games as $game){

		//Don't show unavailable hoster games
		if($game['unavail'] == 't') continue;
		//Don't show if last update of lobbyroom has more than 1 hour ago
		if($game['master'] != 't' && $hide['stuck'] && (time()-$game['updated']) > $hide['stucktime']) continue;
		//Don't show, if room has no more left free slot for player
		if($hide['full'] && $game['dwcurrentplayers'] == $game['dwmaxplayers']) continue;
		
		if($hide['mapmod'] && $game['mapmod'] == 't') continue;
		
		$out[]=$game;
	}
	return $out;
}

/*
$mh = fopen("bin.bin", 'w');


fclose($mh);
socket_close($sock);
*/

//Подготваливаем gameStruct к отправке на клиент
function serializeGameStruct3($game){
global $GAMESTRUCT_VERSION;
	$struct = pack('N', $GAMESTRUCT_VERSION);
	$struct.= str_pad($game['name'], 64);
	$struct.= pack('N', $game['dwsize']);
	$struct.= pack('N', 0);						//dwFlags
	$struct.= str_pad((($game['master']=='t')?$game['host']:$game['hostip']), 40);
	$struct.= pack('N', $game['dwmaxplayers']);
	$struct.= pack('N', $game['dwcurrentplayers']);
	$struct.= pack('N', $game['gametype']);
	$struct.= pack('N', 0);						//dwUserFlags1
	$struct.= pack('N', 0);						//dwUserFlags2
	$struct.= pack('N', 0);						//dwUserFlags3
	$struct.= str_pad(" ", 40);					//secondaryHosts0
	$struct.= str_pad(" ", 40);					//secondaryHosts1
	$struct.= str_pad("Extra", 159);			//Extra
	$struct.= str_pad($game['mapname'], 40);
	$struct.= str_pad($game['hostname'], 40);
	$struct.= str_pad($game['hostver'], 64);
	$struct.= str_pad($game['mods'], 255);
	$struct.= pack('N', $game['gvmajor']);
	$struct.= pack('N', $game['gvminor']);
	$struct.= pack('N', (($game['private']=='t')?1:0));
	$struct.= pack('N', (($game['mapmod']=='t')?1:0));
	$struct.= pack('N', 0);						//modsnum
	$struct.= pack('N', $game['gameid']);
	$struct.= pack('N', 0);						//limits
	$struct.= pack('N', $game['os']);
	$struct.= pack('N', $game['netsum']);
	return $struct;
}

//This sequence is hardcodded in warzone2100/lib/netplay/netplay.cpp 
//from function NETsendGAMESTRUCT()
function readGameStruct3($socket){
	$gameStruct = [];
	
	//Lobby room name
	$bin = socket_read($socket, 64, PHP_BINARY_READ);
	$gameStruct['name'] = trim($bin);
	
	//lib/netplay/netplay.cpp:2718 sizeof(gamestruct.desc)
	$bin = socket_read($socket, 4, PHP_BINARY_READ);
	$gameStruct['dwsize'] = trim(unpack('N', $bin)[1]);
	
	//lib/netplay/netplay.cpp:2723 dwFlags allways zero (not used, not needed)
	$bin = socket_read($socket, 4, PHP_BINARY_READ);
	$gameStruct['dwflags'] = trim(unpack('N', $bin)[1]);
	
	//Host ip ... 40 bytes.. realy?? IP can pass using only 4 byte (32bits) ... 
	//This string is empty while creating room.
	$bin = socket_read($socket, 40, PHP_BINARY_READ);
	$gameStruct['host'] = trim($bin);
	
	//Maximum slots avail for players to connect (this need only 1 byte as maximum 255 slots)
	$bin = socket_read($socket, 4, PHP_BINARY_READ); //Can pass maximum 4 294 967 295 slots (why?!)
	$gameStruct['dwmaxplayers'] = trim(unpack('N', $bin)[1]);
	
	//Current used slot (including AI)
	$bin = socket_read($socket, 4, PHP_BINARY_READ);
	$gameStruct['dwcurrentplayers'] = trim(unpack('N', $bin)[1]);
	
	//According to data/mp/addon.lev this is type of the "map scenario"
	//Compaing or skirmish (this is useless, i don't known why it is here)
	//Allways 14 because of the MP skirmish (game don't has a cooperative company to play)
	//Also seems to the broken, because 14 is Skirmish T1, but while selecting T2/T4 it's
	//always return 14 "Skirmish T1" (used, but not needed)
	$bin = socket_read($socket, 4, PHP_BINARY_READ);
	$gameStruct['gametype'] = trim(unpack('N', $bin)[1]);
	
	// src/multiopt.cpp:328 all dwUserFlags allways zero (not used, not needed)
	$bin = socket_read($socket, 4, PHP_BINARY_READ);
	$gameStruct['dwuserflags1'] = trim(unpack('N', $bin)[1]);
	$bin = socket_read($socket, 4, PHP_BINARY_READ);
	$gameStruct['dwuserflags2'] = trim(unpack('N', $bin)[1]);
	$bin = socket_read($socket, 4, PHP_BINARY_READ);
	$gameStruct['dwuserflags3'] = trim(unpack('N', $bin)[1]);
	
	//Game doesn't support backup hosting, always empty (not used, not needed)
	$bin = socket_read($socket, 40, PHP_BINARY_READ);
	$gameStruct['secondaryhosts0'] = trim($bin);
	$bin = socket_read($socket, 40, PHP_BINARY_READ);
	$gameStruct['secondaryhosts1'] = trim($bin);
	
	//lib/netplay/netplay.cpp:2729 String always be "Extra" (not used, not needed)
	$bin = socket_read($socket, 159, PHP_BINARY_READ);
	$gameStruct['extra'] = trim($bin);
	
	//Map name
	$bin = socket_read($socket, 40, PHP_BINARY_READ);
	$gameStruct['mapname'] = trim($bin);
	
	//Nickname of the hoster player
	$bin = socket_read($socket, 40, PHP_BINARY_READ);
	$gameStruct['hostname'] = trim($bin);
	
	//String version of the hoster game client
	$bin = socket_read($socket, 64, PHP_BINARY_READ);
	$gameStruct['hostver'] = trim($bin);
	
	//Comma separated string of all mods
	//FIXME in wz2100 game
	//This is very bad realisation connection of mods by name from the string
	//there no any checksum or any check by hash or so..
	$bin = socket_read($socket, 255, PHP_BINARY_READ);
	$gameStruct['mods'] = trim($bin);
	
	//Major internal version of game
	$bin = socket_read($socket, 4, PHP_BINARY_READ);
	$gameStruct['gvmajor'] = trim(unpack('N', $bin)[1]);
	//Minor internal version of game
	$bin = socket_read($socket, 4, PHP_BINARY_READ);
	$gameStruct['gvminor'] = trim(unpack('N', $bin)[1]);
	
	//This is actually boolean, can only contain 0 or 1
	//info: if game is closed by passwod or not
	//why this used ulong32 ?!
	$bin = socket_read($socket, 4, PHP_BINARY_READ);
	$gameStruct['private'] = trim(unpack('N', $bin)[1]);
	
	//This is actually boolean, can only contain 0 or 1
	//info: if hosted map is modifyed, and may change standart rules of the game
	//why this used another ulong32 ?!!
	$bin = socket_read($socket, 4, PHP_BINARY_READ);
	$gameStruct['mapmod'] = trim(unpack('N', $bin)[1]);
	
	//lib/netplay/netplay.cpp:2739 Allways be zero
	//Another useless ulong32 (not used, not needed)
	$bin = socket_read($socket, 4, PHP_BINARY_READ);
	$gameStruct['modsnum'] = trim(unpack('N', $bin)[1]);
	
	//Lobby server internal gameId
	$bin = socket_read($socket, 4, PHP_BINARY_READ);
	$gameStruct['gameid'] = trim(unpack('N', $bin)[1]);
	
	//lib/netplay/netplay.cpp:2741
	//Always be zero (not used, not needed)
	$bin = socket_read($socket, 4, PHP_BINARY_READ);
	$gameStruct['limits'] = trim(unpack('N', $bin)[1]);
	
	//Operation System fingerprint
	$bin = socket_read($socket, 4, PHP_BINARY_READ);
	$gameStruct['os'] = trim(unpack('N', $bin)[1]);
	
	//lib/netplay/netplay.cpp:2749 NETCODE_VERSION_MAJOR << 16 | NETCODE_VERSION_MINOR
	//Checksum of Major+Minor internal version of the game
	//I don't known why.. This is useless too.
	$bin = socket_read($socket, 4, PHP_BINARY_READ);
	$gameStruct['netsum'] = trim(unpack('N', $bin)[1]);
	
/*	
	if($gameStruct['os'] == 7825774) $gameStruct['os'] = "Win OS";
	if($gameStruct['os'] == 7168355) $gameStruct['os'] = "Mac OS";
	if($gameStruct['os'] == 7104878) $gameStruct['os'] = "Linux/FreeBSD";
*/
	return $gameStruct;
}

?>
