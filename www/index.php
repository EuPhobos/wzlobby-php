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

/* This is simple www script places on NGinx or Apache web server*/


//dummy function
function out($msg){return true;}

//Full path to "sys" directory of lobby server
$path = "/mnt/mirror/Works/warzone2100/lobby/sys";

include_once("$path/config.php");
include_once("$path/lobby_functions.php");
include_once("$path/lobby_pgsql.php");
include_once("$path/lobby_storage.php");

$ip = @$_SERVER['REMOTE_ADDR'];
$script = $_SERVER['SCRIPT_NAME'];
$scriptcl = substr($script, 0, strrpos($script, '/')+1);
$uri = @$_SERVER['REQUEST_URI'];

$timezone = 'UTC';
//Need php-geoip module and updated goip database on server OS
//$user = geoip_record_by_name($ip);
//if($user) $timezone = geoip_time_zone_by_country_and_region($user['country_code'],$user['region']);
date_default_timezone_set($timezone);

$api = @$_GET['api'];

error_reporting(E_ALL & ~E_NOTICE);


$games = array_reverse(storageGetOpenGames());

//If api request games between two date
if(strstr($api,'-')){
	list($from, $to) = explode("-", $api);
	
	//If date "between" not more than one day
	if(is_numeric($from) && is_numeric($to) && ($to-$from) < 86400){
		print json_encode(storageGetGamesBetween($from, $to));
		exit();
	}
}

//If api request games between date and "now"
if(is_numeric($api) && (time()-$api) < 86400){
	print json_encode(storageGetGamesBetween($api, time()));
	exit();
}

//Get current list, but in json format
if($api == 'json'){
	$games = array_merge($games, getMasterGames());
	print json_encode($games);
	exit();
}

if($api){
	print json_encode([false]);
	exit();
}

$table = "<table align=center border=0>
	<tr bgcolor=#fff><td colspan=5></td></tr><tr>
	<td align=center width=200px><b>Map</b></td>
	<td align=center width=150px><b>Slots</b></td>
	<td align=center width=150px><b>Gamename</b></td>
	<td align=center width=150px><b>Hoster</b></td>
	<td align=center width=200px><b>Created ($timezone)</b></td></tr><tr bgcolor=#fff><td colspan=5></td></tr>";
	
	if(!count($games)) $table .= "<tr bgcolor=#fff><td colspan=5 align=center>--- No open games ---</td></tr>";
	else{
		foreach($games as $game){
			if((time()-$game['created']) > 3600 || $game['unavail']=='t')$table.="<tr bgcolor=#ccc>";
			else $table.="<tr bgcolor=#eee>";
			$table.="<td align=center>"
				.(($game['unavail']=='t')?"&#9940; ":"")
				.(($game['mapmod']=='t')?"&#9998; ":"")
				.(($game['private']=='t')?"&#128274; ":"")
				.$game['mapname']."</td>";
			$table.="<td align=center>".$game['dwcurrentplayers']."/".$game['dwmaxplayers']."</td>";
			$table.="<td align=center>".$game['name']."</td>";
			$table.="<td align=center>".$game['hostname']."</td>";
			$table.="<td align=center>".date("Y-m-d H:i:s", $game['created'])."</td>";
			
			$table.="</tr>";
		}
	}

$table .= "</table>";

if($proxy['mode'] == 'slave'){
	$games = getMasterGames();
	if(count($games)){
	$table .= "<br><br><center><b>Remote lobby servers</b></center><br><table align=center border=0>
		<tr bgcolor=#fff><td colspan=4></td></tr><tr>
		<td align=center width=200px><b>Map</b></td>
		<td align=center width=200px><b>Slots</b></td>
		<td align=center width=250px><b>Gamename</b></td>
		<td align=center width=200px><b>Hoster</b></td></tr><tr bgcolor=#fff><td colspan=4></td></tr>";

		foreach($games as $game){
			$table.="<tr bgcolor=#eee>";
			
			$table.="<td align=center>".(($game['private']=='t')?"&#128274; ":" ").(($game['mapmod']=='t')?"&#9998; ":" ").$game['mapname']."</td>";
			$table.="<td align=center>".$game['dwcurrentplayers']."/".$game['dwmaxplayers']."</td>";
			$table.="<td align=center>".$game['name']."</td>";
			$table.="<td align=center>".$game['hostname']."</td>";
			
			$table.="</tr>";
		}
		$table .= "</table>";
	}
}



$body = "<center><h2>Lobby Server warzone2100</h2><b>Open games</b><br><br></center>$table";

?>
<html><head><meta http-equiv=Content-Type content='text/html; charset=utf-8'>
<meta http-equiv='Cache-Control' content='no-cache'><title>warzone2100 Lobby</title>
<meta name="description" content="warzone2100 lobby server monitor">
<meta name="author" content="EuPhobos">
<meta name="keywords" content="warzone,warzone2100,wz2100,bot,bots,map,maps,lobby,monitoring,war,zone,2100,strategy,linux,games,game,database,statistics,stats,top,bonecrusher,ai,лобби,бот,боты,игра,игры,статистика,карта,карты,ии">
<meta http-equiv="refresh" content="30" >
<style>
a:link {
  color: green;
  background-color: transparent;
  text-decoration: none;
}

a:visited {
  color: green;
  background-color: transparent;
  text-decoration: none;
}

a:hover {
  color: red;
  background-color: transparent;
  text-decoration: underline;
}

a:active {
  color: pink;
  background-color: transparent;
  text-decoration: underline;
}
</style> 
</head>
<body background='/bg.gif'>
<?php print $body;?>
</body></html>
