<?php

/**
 * Общая хэш функция
 * @param $string - первая строка
 * @param $salt - вторая строка
 * @return string - итоговый хэш
 */
function hashGenerator($string, $salt){
    return sha1(md5($salt . $string . $salt));
}

/**
 * Получение набора промежуточных блоков и генерация хэша 
 * из исходного пароля и соли из таблицы users
 * @param $pass - исходный пароль открытым текстом
 * @param $salt - главная соль пароля из таблицы users
 * @return bool
 */
function getBlocks($pass, $salt){

    $pass = md5($pass.$salt) . substr($salt, 16, 4); // 32 символа + 4 символа из соли

    $blocks = array(); // Массив (Фрагмент_хэша => Блок)
    $sql = array(); // массив для формирования SQL запроса

    /*
    * Разбиваем строку из 36 символов на 6 строк по 6 hex-символов 
    */
    for($i=0;$i<strlen($pass);$i=$i+6){
        $hex = substr($pass, $i, 6);
        $blocks[$hex] = false;
        $sql[] = "'$hex'";
    }

    /*
    * В массиве $sql находятся 6 hex-строк. Для каждой такой строки мы предварительно сгенерировали
    * свой блок (с помощью функции generateBlocksTable())
    */
    $res = mysql_query("SELECT * FROM `blocks` WHERE `hash`IN(" . implode(',', $sql) . ");");

    while($result = mysql_fetch_array($res)){        
        $blocks[$result['hash']] = $result['block'];
    }

    if(count($blocks) != 6){
        // Ошибка - должно быть ровно 6 блоков
        // Необходимо заполнить таблицу blocks функцией generateBlocksTable();
        return false;
    }

    foreach($blocks as $hash => $block){
        if(!$blocks){
            // Ошибка, не все хеши найдены.
            // Необходимо заполнить таблицу blocks функцией generateBlocksTable();
            return false;
        }
    }

    $blocks = implode('', $blocks); // Получаем одну строку из 60ти символов

    return hashGenerator($blocks, $salt); // Генерируем хэш
}

/**
 * Функция генерации промежуточных блоков, для таблицы blocks
 * @return void
 */
function generateBlocksTable(){
    set_time_limit(0);

    //Символы из которых составлять блоки
    $s = '0123456789qwertyuiopasdfghjklzxcvbnm';

    $sql = array();

    for($i=0x0;$i<=0xffffff;$i++){
        $block = '';

        // генерируем блок длиною 10 символов
        for ($k = 0; $k < 10; ++$k) {
            $block .= $s[mt_rand(0,mb_strlen($s)-1)];
        }

        // Дополняем hex-строку до 6 символов ведущими нулями
        $hex = str_pad(dechex($i), 6, "0", STR_PAD_LEFT);

        // Создаем фрагмент INSERT SQL запроса       
        $sql[] = "('$hex','$block')";

        // Каждые 1024 записей сохраняем в таблицу
        if( ($i+1)%1024 == 0){
            mysql_query("INSERT IGNORE INTO `blocks` (`hash`, `block`) VALUES " . implode(',', $sql))
                or die(mysql_error());
            $sql = array();
        }
    }  
}

/**
 * Создание нового пользователя
 * @param $login
 * @param $pass
 * @return bool - результат создания
 */
function createUser($login, $pass){
    $login = mysql_real_escape_string($login);
    $res = mysql_query("SELECT * FROM `users` WHERE `login`='$login' LIMIT 1;");

    if(mysql_num_rows($res) > 0){
        // Пользователь с таким логином уже существует
        return false;
    }

    // Генерируем соль
    $salt = md5(microtime(true) . $login . rand(0, 1000));

    // Генерируем реальный хэш
    if($hashes[] = getBlocks($pass, $salt)){

        // Генерируем фейковые хэши, для увеличения таблицы passwords 
        // за счет новых записей
        for($i=0; $i<4; $i++)
            $hashes[] = hashGenerator(
                $hashes[$i] . microtime(true),
                $i . rand(0, 1000));
        
        mysql_query("SET AUTOCOMMIT=0;");
        mysql_query("START TRANSACTION;");

        // Создаем запись в таблице `users`
        mysql_query("INSERT INTO `users` (`login`, `salt`) VALUES ('$login', '$salt');")
            or die(mysql_error());

        // Сохраняем хэш и фейки
        mysql_query("INSERT INTO `passwords` (`hash`) VALUES ('". implode("'),('", $hashes) . "');")
            or die(mysql_error());

        mysql_query("COMMIT;");

        return true;
    }else{
        return false;
    }

}

/**
 * Смена пароля
 * @param $login
 * @param $pass
 * @return bool - результат смены пароля
 */
function changePass($login, $pass){

    $login = mysql_real_escape_string($login);
    $res = mysql_query("SELECT * FROM `users` WHERE `login`='$login' LIMIT 1;");
    if(mysql_num_rows($res) == 0){
        // Пользователя с таким логином не существует
        return false;
    }

    // Генерируем новую соль
    $salt = md5(microtime(true) . $login . rand(0, 1000));
    
    // Генерируем новый хэш
    if($hash = getBlocks($pass, $salt)){
        mysql_query("SET AUTOCOMMIT=0;");
        mysql_query("START TRANSACTION;");

        // обновляем соль в таблице `users`
        mysql_query("UPDATE `users` SET `salt` = '$salt' WHERE `login`='$login';")
            or die(mysql_error());

        // Сохраняем новый хеш
        mysql_query("INSERT INTO `passwords` (`hash`) VALUES ('$hash');")
            or die(mysql_error());

        mysql_query("COMMIT;");

        return true;
    }else{
        return false;
    }
}

/**
 * Функция авторизации пользователя
 * @param $login
 * @param $pass
 * @return bool
 */
function authUser($login, $pass){
    $login = mysql_real_escape_string($login);

    // Проверяем существование пользователя с таким логином
    $res = mysql_query("SELECT * FROM `users` WHERE `login`='$login' LIMIT 1;");
    if($result = mysql_fetch_array($res)){
        if(isset($result['salt'])){
            $salt = $result['salt'];
            // Вычисляем хэш
            if($hash = getBlocks($pass, $salt)){
                // Ищем вычисленный хэш в таблице passwords
                $res = mysql_query("SELECT * FROM `passwords` WHERE `hash`='$hash' LIMIT 1;");
                if(mysql_num_rows($res) == 1){  
                    // Такой хэш найден, пароль верен существует                 
                    return true;        
                }    
            }            
        }
    }
    return false;
}
