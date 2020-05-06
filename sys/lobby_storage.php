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
Using $gameid directly on any database is safe, because we are self unpack 4 bytes as ulong32 numeric.
$gameid cannot be a string or something else, SQL-injects or filesystem hacks - there is impossible.
*/


/*
This function run only once,
when lobby server start, at parent process.
*/

//Функция инициализации (!!!)не должна вызываться из главной логики!
//Вызывается только раз, при новом запуске (перезапуске) сервера в процессе-предке.
function storageInit(){
global $storage, $psql_write;
	if($storage['postgres']){
		if(psqlWrite()){
			$now = time();
			return pg_query_params($psql_write, "INSERT INTO games_v3 (gameid, name, created, updated, closed) VALUES ($1,$2,$3,$4,$5)", [0, 'init', 
				$now,$now,$now]);
		}
	}

	if($storage['filesystem']){
		//Check directory
		if(!is_dir($storage['path']) && !mkdir($storage['path'])){
			out("ERR:cannot create directory");
			return false;
		}

		//Check old files and clean it
		if(is_file($storage['path']."/0")){
			$f = glob($storage['path']."/*");
			foreach($f as $file){
				if(is_file($file)) unlink($file);
			}
		}

		//Check create file
		if(!file_put_contents($storage['path']."/0", json_encode(['id'=>0,'created'=>time(),'init'=>true]))){
			out("ERR:cannot create file");
			return false;
		}else{
			return true;
		}
	}
/*
	if($storage['sqlite']){
		if(sqliteWrite()){
			
		}
	}
*/
	return false;
}

//Создаём шаблон, для дальнейшего заполнения
function storageInsert($arr){
global $storage, $psql_write;

	if($storage['postgres']){
		if(psqlWrite()){
			return pg_query_params($psql_write, "INSERT INTO games_v3 (gameid, created) VALUES ($1,$2)", [$arr['gameid'], time()]) or out('Query failed: ' . pg_last_error());
		}
	}

	if($storage['filesystem']){
		if(!file_put_contents($storage['path']."/".$arr['gameid'], json_encode(['gameid'=>$arr['gameid'],'created'=>time()])))return false;
		$main=json_decode(file_get_contents($storage['path']."/0"));
		file_put_contents($storage['path']."/0", json_encode(['id'=>$arr['gameid'],'created'=>$main->{'created'},'updated'=>time(),'init'=>true]));
		return true;
	}
	
	
}

//Обновляем бд
function storageUpdate($id, $arr){
global $storage, $psql_write;

	if($storage['postgres']){
		if(psqlWrite()){
			return pg_query_params($psql_write, "UPDATE games_v3 SET 
				updated=$1,
				name=$2,
				dwsize=$3,
				dwmaxplayers=$4,
				dwcurrentplayers=$5,
				mapname=$6,
				hostname=$7,
				mods=$8,
				private=$9,
				mapmod=$10,
				
				gametype=$11,
				hostver=$12,
				gvmajor=$13,
				gvminor=$14,
				os=$15,
				netsum=$16,
				
				hostip=$17,
				hostport=$18,
				
				unavail=$19
				
				WHERE id=$20", [
					time(),
					$arr['name'],
					$arr['dwsize'],
					$arr['dwmaxplayers'],
					$arr['dwcurrentplayers'],
					$arr['mapname'],
					$arr['hostname'],
					$arr['mods'],
					$arr['private'],
					$arr['mapmod'],
					$arr['gametype'],
					$arr['hostver'],
					$arr['gvmajor'],
					$arr['gvminor'],
					$arr['os'],
					$arr['netsum'],
					$arr['hostip'],
					$arr['hostport'],
					$arr['unavail'],
					$id
			]) or out('Query failed: ' . pg_last_error());
		}
	}

	if($storage['filesystem']){
		$g=json_decode(file_get_contents($storage['path']."/$id"));
		$c=[];
		foreach($g as $key=>$value)$c[$key]=$value;
		$c['id'] = $id;
		$c['updated'] = time();
		$game=array_merge($c,$arr);
		if(!file_put_contents($storage['path']."/$id", json_encode($game))) return false;
		return true;
	}
	
}

//Проверяем созданный ранее пустой шаблон для игры, во избежании конфликтов с gameId
function storageGetNew($gameid){
global $storage, $psql_read;

	if($storage['postgres']){
		if(psqlRead()){
			$res = pg_query($psql_read, "SELECT id FROM games_v3 WHERE gameid = $gameid AND updated IS NULL AND closed IS NULL ORDER BY id DESC LIMIT 1") or out('Query failed: ' . pg_last_error());
			$ret = pg_fetch_array($res, null, PGSQL_ASSOC);
			return $ret;
		}
	}
	
	if($storage['filesystem']){
		$g=json_decode(file_get_contents($storage['path']."/$gameid"));
		$c=[];
		foreach($g as $key=>$value)$c[$key]=$value;
		$c['id'] = $gameid;
		return $c;
	}
	
	return false;
}


function storageGetGamesBetween($from=0, $to=0){
global $storage, $psql_read;

	if($storage['filesystem'])	return [false];
	if($storage['sqlite'])		return [false];
	if(!$from || !$to || !is_numeric($from) || !is_numeric($to) || $from > $to) return [false];
	
	if($storage['postgres']){
		if(psqlRead()){
			$games = [];
			$res = pg_query($psql_read, "SELECT * FROM games_v3 WHERE created BETWEEN $from AND $to") or out('Query failed: ' . pg_last_error());
			while($ret = pg_fetch_array($res, null, PGSQL_ASSOC)){$games[]=$ret;}
			return $games;
		}
	}
	
}

//Получаем все созданные и открытые на данный момент игры
function storageGetOpenGames(){
global $storage, $psql_read;
	if($storage['postgres']){
		if(psqlRead()){
			$games = [];
			$res = pg_query($psql_read, "SELECT * FROM games_v3 WHERE updated IS NOT NULL AND closed IS NULL") or out('Query failed: ' . pg_last_error());
			while($ret = pg_fetch_array($res, null, PGSQL_ASSOC)){$games[]=$ret;}
			return $games;
		}
	}
	
	if($storage['filesystem']){
		$games=[];
		$g=glob($storage['path']."/*");
		foreach($g as $file){
			$f=json_decode(file_get_contents($file));
			if($f->{'init'}) continue;
			$c=[];
			foreach($f as $key=>$value){
				$c[$key]=$value;
//				print "$key=>$value\n";
			}
			$c['private']=(($c['private'])?'t':'f');
			$c['mapmod']=(($c['mapmod'])?'t':'f');
			$games[]=$c;
		}
		return $games;
	}
	
	return false;
}

//Проверяем созданную "нами" игру во избежании конфликтов с gameId, для дальнейшего обновления в бд
function storageGetOpen($gameid){
global $storage, $psql_read;

	if($storage['postgres']){
		if(psqlRead()){
			$res = pg_query($psql_read, "SELECT id FROM games_v3 WHERE gameid = $gameid AND closed IS NULL ORDER BY id DESC LIMIT 1") or out('Query failed: ' . pg_last_error());
			$ret = pg_fetch_array($res, null, PGSQL_ASSOC);
			return $ret;
		}
	}
	
	if($storage['filesystem'] && is_file($storage['path']."/$gameid")){
		$g=json_decode(file_get_contents($storage['path']."/$gameid"));
		$c=[];
		foreach($g as $key=>$value)$c[$key]=$value;
		$c['id']=$gameid;
		return $c;
	}
	
	return false;
}

//Забор статистики
function storageGetMapTop(){
global $storage, $psql_read;

	$LASTDATE = (time()-86400);

	if($storage['filesystem'])	return false;
	if($storage['sqlite'])		return false;

	if($storage['postgres']){
		if(psqlRead()){
			$res = pg_query($psql_read, "SELECT mapname AS top, count(mapname) AS freq FROM games_v3 WHERE created BETWEEN $LASTDATE AND ".time()." GROUP BY top ORDER BY freq DESC LIMIT 1") or out('Query failed: ' . pg_last_error());
			$ret = pg_fetch_array($res, null, PGSQL_ASSOC);
			return $ret;
		}
	}
	
	return false;
}

//Получаем последний gameId от созданной игры
function storageGetLastGameId(){
global $storage, $psql_read;
	if($storage['postgres']){
		if(psqlRead()){
			$res = pg_query($psql_read, "SELECT gameid FROM games_v3 ORDER BY id DESC LIMIT 1") or out('Query failed: ' . pg_last_error());
			$ret = pg_fetch_array($res, null, PGSQL_ASSOC);
			return $ret['gameid'];
		}
	}
	
	if($storage['filesystem']){
		$main = json_decode(file_get_contents($storage['path']."/0"));
		return $main->{'id'};
	/*
		$f = glob($storage['path']."/0");
		rsort($f);
		return substr($f[0],strpos($f[0],'/')+1);
	*/
	}
	return false;
}

//Забор статистики
function storageGetHosterTop(){
global $storage, $psql_read;

	$LASTDATE = (time()-86400);

	if($storage['filesystem'])	return false;
	if($storage['sqlite'])		return false;

	if($storage['postgres']){
		if(psqlRead()){
			$res = pg_query($psql_read, "SELECT hostname AS top, count(hostname) AS freq FROM games_v3 WHERE created BETWEEN $LASTDATE AND ".time()." GROUP BY top ORDER BY freq DESC LIMIT 1") or out('Query failed: ' . pg_last_error());
			$ret = pg_fetch_array($res, null, PGSQL_ASSOC);
			return $ret;
		}
	}
	
	return false;
}


//Закрываем бд
function storageClose($id){
global $storage, $psql_write;
	if($storage['postgres']){
		if(psqlWrite()){
			$q = pg_query_params($psql_write, "UPDATE games_v3 SET closed=$1 WHERE id=$2", [time(),$id]);
		}
	}
	
	if($storage['filesystem']){
		unlink($storage['path']."/$id");
	}
}

?>
