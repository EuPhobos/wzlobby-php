<?php

//External address
$address = '92.43.0.37';
//TCP Port
$port = 9990;
//Handle game lobby protocol version
$GAMESTRUCT_VERSION = 3;

//Try change identity of forked child process
$changeIdentity = false;
$uid = 65534;
$gid = 65534;


$MOTD = [
"Hello on EuPhobos test lobby server",
"Welcome to EuPhobos test lobby server",
"You are on test lobby server",
"This is MOTD test message, replace it"
];

//Hide full lobby room
$hide['full'] = true;

//Do not public room with map-mod games
$hide['mapmod'] = false;

//Hide some stuck listed games
$hide['stuck'] = true;
$hide['stucktime'] = 3600;

//game version retriction
$version['strict'] = true;

//internal game version
//lib/netplay/netplay.cpp:180
//Old, but ok
$version['allowed'][0x20A0]=0x29;	//3.1.5
$version['allowed'][0x30A0]=8;		//3.2.3

//Ok
$version['release'][0x30B0]=9;		//3.3.0
//master (do not say "need upgrade" for master)
$version['master'][0x1000]=1;

//Proxy mode
// 'master' (string) [TODO not implemented yet]
// 'slave' (string)
// false (bool)
$proxy['mode'] = 'slave';

/*
Address of master servers, if we are slave
List the master servers separeted by comma
Server port 9990 - by default proxy port, or can be set manually
Example: 
'lobby.wz2100.net'
'lobby.wz2100.net:8880'
'lobby.wz2100.net:9990,lobby2.wz2100.net'
'lobby.wz2100.net:9990,lobby2.wz2100.net:9990'
'lobby.wz2100.net:9990,lobby.wz2100.net:9991,lobby.wz2100.net:9992'
etc
*/

$proxy['master'] = 'lobby.wz2100.net,lobby.euphobos.ru:8880';

//Default proxy port
$proxy['port'] = 9990;

/* Storage engines, select only one */
//The best way is using PostreSQL
//Create two users with readonly and read/write access to PgSQL database
//It can handle very massive data
//And using JSON had store some extra info (not implemented yet)
//DATA: Hosted current games, full statistics, extra json info
$storage['postgres']	= true;

//If host not support database like PgSQL use SQLite3 instead
//It can handle statistics
//DATA: Hosted current games, partial statistics
//ABONDED! I don't see the point in this database, because SQLite3 doesn't work very well with parallel queries
$storage['sqlite']		= false;

//If host's php without sqlite3 support, so use filesystem instead
//It's very simple method
//DATA: Hosted current games, no statistics
$storage['filesystem']	= false;

//Main files path, used by logs, filesystem storage and sqlite
//Don't close this path by slash at the end.
$storage['path'] = "/tmp/wzlobby";



/*
Create two users with readonly and read/write access to PgSQL database
Do not use same user for read and write!
Be safe, use separate logins for readonly and read/write access!
*/
//read/write user
$PSQL['user'] = 'wzlobby';
$PSQL['pass'] = '123wzlobby123';
//readonly user
$PSQL['usro'] = 'lobbyro';			//Read only user
$PSQL['pwro'] = '123wzlobbyro123';		//Read only pass

$PSQL['name'] = 'lobby';
$PSQL['host'] = '10.50.50.12';
$PSQL['port'] = 5432;


$logPath = $storage['path']."_".date("Y-m-d").".log";



?>
