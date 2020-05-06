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
Главная функция запущенная в параллельном процессе-потомке, обрабатывает входящие соединения
и содержит основную логику сервера
---
Main function, process incoming connections, running in child forked process
*/

function interact($socket){
global	$GAMESTRUCT_VERSION, $gameid, $psql_read, $psql_write, $version, $proxy;

    do {
		$disconnect = false;
		//First try or die
		if (false === ($bin = socket_read($socket, 4,  PHP_BINARY_READ))) {
			out("Unable exec socket_read(): with: ".socket_strerror(socket_last_error($socket)));
			break;
		}

		//Getting IP address of incoming client
		if(!@$hostip) socket_getpeername($socket, $hostip);
		
		//If empty string incoming, close connection, end process
		//Если больше ничего не приходит, это подозрительно, закрываем соединение
		if(!strlen($bin)){
			out("Empty input, closing socket");
			break;
		}
		
		//If string too short, nothing doing
		if(strlen($bin) < 4) continue;
		
		//Пробуем распаковать первые 4 байта в 32-х битный беззнаковый
		$sver = trim(unpack('N', $bin)[1]);
		
		//If we get binary version of gameStruct, cotinue process as binary protocol
		//Если первые 4 байта это 32битная версия gameStruct протокола, обрабатываем как бинарный протокол
		//получаем gameStruct.
		if($sver == $GAMESTRUCT_VERSION){
			$gameStruct = readGameStruct3($socket);
			
			$gameid = $gameStruct['gameid'];
			
			$gameStruct['hostip'] = $hostip;

			//This need to be fixed in wz2100 game engine, game need to be a dynamicly tcp port
			//TODO хоть в игре это и не динамический порт, он должен быть настраиваемым!
			//Исправить в игре, и после исправить тут
			$gameStruct['hostport'] = 2100;
			
//			print "DEBUG: gameStruct\n";
//			print_r($gameStruct);
			
			//Update opened game process (like change slots, password or gamename)
			if(!$addg){
				$storageStruct = storageGetOpen($gameStruct['gameid']);
				if(!$storageStruct){
					out("ERR:Unable find id of opened lobby");
					break;
				}
				
				$id = $storageStruct['id'];
				if(!storageUpdate($id, $gameStruct)) out("ERR:Storage upadte");
				out("UPD:update database id=$id");
			}
		}
		
		//If first 4 byte contain a text command
		//Если первые 4 байта содержут голый текст-комманду
		//---
		
		//Game client request a new gameID
		//Выдать свободный gameID для созданного лобби в игре
		if (trim($bin) == 'gaId') {
		
			$gameid = getGameId();

			//Пропускаем нультерминатор
			$null = socket_read($socket, 1,  PHP_BINARY_READ); //Nulltermination
			
			out("RCVD:gaId");
			
			//Send to game client gameID in 32-bit serialized
			//Отправляем хостеру gameID в 32-битной последовательности
			$send = pack('N', $gameid);
			out("SEND:$gameid");
			
			//Create new game template in DB
			//Создаём новый пустой шаблон в хранилище, для новой gameStruct
			if(!storageInsert(['gameid'=>$gameid])) out("ERR:Storange insert");
			
			socket_write($socket, $send, strlen($send));
			continue;
		}

		//Game client send to this server a gameStruct in binary protocol
		//Игра приняла gameID и передаёт серверу gameStruct
		if (trim($bin) == 'addg') {
		
			//Пропускаем нультерминатор
			$null = socket_read($socket, 1,  PHP_BINARY_READ); //Nulltermination
		
			out("RCVD:addg");
			
			//Нужна обработка gameStruct
			$addg = true;
			
			continue;
		}

		//Game client want a list of games in binary protocol
		//Обрабатываем запрос на список открытых игр
		if(trim($bin) == 'list'){
		
			$null = socket_read($socket, 1,  PHP_BINARY_READ); //Nulltermination
			
			out("RCVD:list");

			
			$games = [];
			
			//If proxy mode is enabled, obtain games from master lobby server
			//Получаем игры от мастера
			if($proxy['mode'] == 'slave'){
				$games = getMasterGames();
			}

			//Obtain local hosted open games
			//Получаем все открытые игры и все gameStruct
			if(($local_games=storageGetOpenGames())!==false){
				$games = array_merge($games, $local_games);
			}
			else out("ERR:Failed get open games");
			
			//Filter open games, see function in lobby_functions.php
			if(count($games))$games = filterOpenedGames($games);

//			print "DEBUG: list\n";
//			print_r($games);
			
			//Serialized number of listed games
			//Сериализуем кол-во игр, которое будем передавать 
			$send = pack('N', count($games));
			foreach($games as $game){$send .= serializeGameStruct3($game);}
			
			$send .= pack('N', 200); //OK code
			$msg = getMOTD('list');
			$send .= pack('N', strlen($msg)).$msg;

			socket_write($socket, $send, strlen($send));
			out("SEND:gamelist to $hostip");
		}

		//Now process binary gameStruct from hosted game client and decide to accept or reject his game
		//Обрабатываем полученный gameStruct и решаем, разрешить ли хостить игру.
		if(@$addg){
			$addg = false;
			$send = '';
			$gameStruct['unavail'] = 'f';

			//Trying connect to hoster client room by his public ip
			//Проверяем доступен ли хостер по его публичному IP
			if(!checkAvail($gameStruct)){
				$gameStruct['unavail'] = 't';
				$send = pack('N', 408); //Request timeout
				out("SEND:408");
				$msg = "Your address ".$gameStruct['hostip'].":".$gameStruct['hostport']." is unavailable";
			//Check his version of a game client
			//Проверяем разрешённые версии игры
			}elseif(!$version['strict'] 
				|| @$version['release'][$gameStruct['gvmajor']] == $gameStruct['gvminor'] 
				|| @$version['master'][$gameStruct['gvmajor']]){
				
				$send = pack('N', 200); //OK code
				out("SEND:200");
				$msg = getMOTD();
			}elseif($version['strict'] && @$version['allowed'][$gameStruct['gvmajor']] == $gameStruct['gvminor']){
				$send = pack('N', 400); //Need upgrade (426 more compatible actually)
				out("SEND:400");
				$msg = "You need upgrade the game!";
			}else{
				$send = pack('N', 403); //Forbidden
				out("SEND:403");
				$msg = "Forbidden! You need upgrade the game!";
				$disconnect = true;
			}

			//Check new game template in our DB
			//Проверяем, что новый пустой шаблон в базе есть.
			$storageStruct = storageGetNew($gameStruct['gameid']);

			//If not - somthing wrong on the server side.
			if(!$storageStruct){
				$send = pack('N', 500); //Internal server error
				out("SEND:500");
				$msg = "Internal server error, sorry";
				$send .= pack('N', strlen($msg)).$msg;
				socket_write($socket, $send, strlen($send));
				break;
			}

			$id = $storageStruct['id'];
			
			//Update empty new template with client gameStruct info
			storageUpdate($id, $gameStruct);
			
			$send .= pack('N', strlen($msg)).$msg;
			socket_write($socket, $send, strlen($send));
			
			if($disconnect) break;
		}
/*
		TELNET (or so) commands
*/
		//Stringify to json
		//Get all lists in JSON format
		if(trim($bin) == 'json'){
			out("RCVD:json");
			$games = storageGetOpenGames();
			if($proxy['mode'] == 'slave') $games = array_merge($games, getMasterGames());
			$send = json_encode($games);
			socket_write($socket, $send, strlen($send));
			out("SEND:json list to $hostip");
		}
		
		//Get local games lists in JSON format
		if(trim($bin) == 'ljsn'){
			out("RCVD:ljsn");
			$send = json_encode(storageGetOpenGames());
			socket_write($socket, $send, strlen($send));
			out("SEND:json list to $hostip");
		}
		
		//Get remote master lobby games lists in JSON format
		if(trim($bin) == 'mjsn'){
			out("RCVD:mjsn");
			$send = '';
			if($proxy['mode'] == 'slave'){
				$send = json_encode(getMasterGames());
			}
			socket_write($socket, $send, strlen($send));
			out("SEND:master json list to $hostip");
		}
		
		//Close connection with client
		//(this not stop the server, just close connection with client/telnet/nc or so)
		if(trim($bin) == 'exit' || trim($bin) == 'quit' || trim($bin) == 'stop'){
			out("STOP:$hostip");
			break;
		}
		
		//Statistics in JSON format
		if(trim($bin) == 'stat'){
			$out = [];
			$out['map'] = storageGetMapTop();
			$out['hoster'] = storageGetHosterTop();
			$send = json_encode($out);
			socket_write($socket, $send, strlen($send));
			out("SEND:json stat to $hostip");
		}
		
	} while (true);
	
	out("Connection lost");
	
	//If game template has been opened, close it in our DB
	if(@$id){
		out("STOP:closing storage database id=$id");
		storageClose($id);
	}

	//Stop DB engine
	if(@$psql_write)pg_close($psql_write);
	if(@$psql_read)pg_close($psql_read);
	
}

?>
