<?php
/*
Source: PHP User Contributed Notes at https://www.php.net/manual/ru/function.socket-accept.php#80691
License: CCA3 at https://www.php.net/manual/en/cc.license.php
Modify by EuPhobos
*/

$__server_listening = true; 
error_reporting(E_ALL);
set_time_limit(0);
ob_implicit_flush();
declare(ticks = 1);

$pid = become_daemon();

print "pid: $pid\n"; 


/* nobody/nogroup, change to your host's uid/gid of the non-priv user */
if($changeIdentity)change_identity($uid, $gid);

/* handle signals */
pcntl_signal(SIGTERM, 'sig_handler');
pcntl_signal(SIGINT, 'sig_handler');
pcntl_signal(SIGCHLD, 'sig_handler'); 

server_loop($address, $port); 

function change_identity($uid, $gid){
	if(!posix_setgid($gid)){
		out("Unable to setgid to $gid!");
		exit;
	}

	if(!posix_setuid($uid)){
		out("Unable to setuid to $uid!");
		exit;
	}
} 

/**
  * Creates a server socket and listens for incoming client connections
  * @param string $address The address to listen on
  * @param int $port The port to listen on
  */
function server_loop($address, $port){
GLOBAL $__server_listening;

	if(($sock = socket_create(AF_INET, SOCK_STREAM, 0)) < 0){
		out("failed to create socket: ".socket_strerror($sock));
		exit();
	}

	if(($ret = socket_bind($sock, $address, $port)) < 0){
		out("failed to bind socket: ".socket_strerror($ret));
		exit();
	}

	if(($ret = socket_listen($sock, 0)) < 0){
		out("failed to listen to socket: ".socket_strerror($ret));
		exit();
	}

	socket_set_nonblock($sock);

	out("waiting for clients to connect");

	while($__server_listening){
		$connection = @socket_accept($sock);
		if($connection === false){
			usleep(100);
		}elseif($connection > 0){
			handle_client($sock, $connection);
		}else{
			out("error: ".socket_strerror($connection));
			die;
		}
	}
} 

/**
  * Signal handler
  */
function sig_handler($sig){
	switch($sig){
		case SIGTERM:
		case SIGINT:
			exit();
		break;

		case SIGCHLD:
			pcntl_waitpid(-1, $status);
		break;
	}
} 

/**
  * Handle a new client connection
  */
function handle_client($ssock, $csock){
GLOBAL $__server_listening;

	$pid = pcntl_fork();

	if ($pid == -1){
		/* fork failed */
		out("fork failure!");
		die;
	}elseif ($pid == 0){
		/* child process */
		$__server_listening = false;
		socket_close($ssock);
		interact($csock);
		socket_close($csock);
	}else{
		socket_close($csock);
	}
} 

/**
  * Become a daemon by forking and closing the parent
  */
function become_daemon(){
	$pid = pcntl_fork();

	if ($pid == -1){
		/* fork failed */
		out("fork failure!");
		exit();
	}elseif ($pid){
		/* close the parent */
		if(!storageInit()){out("ERR:Storage init false"); exit();}
		if(@$psql_write)pg_close($psql_write);
		exit();
	}else{
		/* child becomes our daemon */
		posix_setsid();
		chdir('/');
		umask(0);
		return posix_getpid();
	}
}
?>
