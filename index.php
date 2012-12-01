<?php

include('functions.php');

mysql_connect('localhost','root','') or die(); 
mysql_select_db('test') or die(mysql_error());  

/*
* Для заполнения таблицы blocks,
* Используется один раз
*/
//generateBlocksTable();
// die();

/*
* Создаем пользователя
* Логин almaz
* Пароль almaz123
*/
createUser('almaz', 'almaz123');

/*
* Меняем пароль пользователя almaz
* Новый пароль almaz1234
*/
changePass('almaz', 'almaz1234');

/*
* Авторизация
*/
if(authUser('almaz', 'almaz1234')){
	print "authorization successful";
}else{
	print "wrong password";
}
