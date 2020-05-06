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

function psqlRead(){
global $psql_read, $PSQL;
	//Коннект к базе только чтение
	if(!$psql_read){
		$psql_read = pg_connect("host=".$PSQL['host']." port=".$PSQL['port']." dbname=".$PSQL['name']." user=".$PSQL['usro']." password=".$PSQL['pwro'])
			or out("Ошибка на сервере, невозможно подключиться к базе PgSQL");
	}
	return true;
}

function psqlWrite(){
global $psql_write, $PSQL;
	//Коннект к базе чтение/запись
	if(!$psql_write){
		$psql_write = pg_connect("host=".$PSQL['host']." port=".$PSQL['port']." dbname=".$PSQL['name']." user=".$PSQL['user']." password=".$PSQL['pass'])
			or out("Ошибка на сервере, невозможно подключиться к базе PgSQL");
		if(!$psql_write) return false;
	}
	return true;
}



?>
