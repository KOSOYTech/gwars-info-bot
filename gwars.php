<?php

// Запускаем таймер для подсчёта времени выполнения скрипта
$scriptstart = microtime(true);

// Подключаем модуль PHPQuery для простого парсинга страниц
include 'phpQuery.php';

// Подключаем Composer. Нужен для библиотеки PHPSpreadSheet
require 'vendor/autoload.php';

// Подключаем пространства имен классов библиотеки PHPSpreadSheet
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Универсальная функция отправки данных и получения вебстраниц при помощи CURL, в которую мы передаём адрес вебстраницы и, при неоходимости, POST-данные для отправки. Поскольку нам может не понадобиться отправка данных, мы присваеваем переменной POST нулевое значение по умолчанию
function request ($url, $post = 0) {
  $ch = curl_init(); // Инициализируем переменную, которая будет отвечать за CURL-сеанс
  curl_setopt($ch, CURLOPT_URL, $url ); // В настройке CURL-сеанса указываем целевой URL
  curl_setopt($ch, CURLOPT_HEADER, 0); // В настройке CURL-сеанса указываем, что нам не нужно получать заголовки страницы
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // В настройке CURL-сеанса указываем, что результат нужно будем вернуть в переменную, а не вывести его
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // В настройке CURL-сеанса указываем, что необходимо проследовать за редиректом, если он будет на целевой странице
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // В настройке CURL-сеанса указываем максимальное время в секундах для загрузки одной целевой страницы
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // В настройке CURL-сеанса указываем, что мы готовы доверять любого SSL-сертификату сайта
  curl_setopt($ch, CURLOPT_COOKIEJAR, dirname(__FILE__).'/cookie.txt'); // В настройке CURL-сеанса указываем, в какой файл будет сохранять COOKIE-файлы целевой страницы
  curl_setopt($ch, CURLOPT_COOKIEFILE,  dirname(__FILE__).'/cookie.txt'); // В настройке CURL-сеанса указываем, из какого файла будем брать COOKIE-файлы при обращении к целевой странице
  curl_setopt($ch, CURLOPT_POST, $post!==0 ); // В настройке CURL-сеанса указываем, что собираемся передавать POST-данные, если они у нас есть
  if ($post) curl_setopt($ch, CURLOPT_POSTFIELDS, $post); // В настройке CURL-сеанса указываем, что если у нас есть POST-данные, то мы переаём их целевой странице
  $data = curl_exec($ch); // Указываем, в какую переменную сохранять ответ от CURL-запроса
  curl_close($ch); // Закрываем сеанс CURL
  return $data; // Возвращаем переменную с результатом CURL-запроса
}

## СТАРТ блока для входа в игру

// Отправка первичного запроса для получения входных данных для авторизации в игре
$datastart = request('http://www.gwars.ru/login.php');

// Преобразование результата первичного CURL-запроса в PHPQuery документ для последующего парсинга
$datastart = phpQuery::newDocument($datastart);

// Составляем ассоциативный массив из полученных первичных данных, который затем будем использовать для авторизации в игре
$auth = array(
"brdata" => "d19858ee5e2fad60d73c9cee434faaa5", // Назначение неизвестно
"resl" => "2560x1440%4024%2C+Fri+Aug+10+2018+01%3A08%3A37+GMT%2B0300+%28%CC%EE%F1%EA%E2%E0%2C+%F1%F2%E0%ED%E4%E0%F0%F2%ED%EE%E5+%E2%F0%E5%EC%FF%29", // Общие данные о пользователе: разрешение, дата, время
"time" => "4117", // Предположительно текущее время
"date" => "10", // Текущее число месяца
"pwdmd5" => "Null.", // Хеш пароля в формате MD5 (предположительно не используется)
"pass1" => "", // Назначение неизвестно
"from" => "", // Назначение неизвестно
"loginkey" => $datastart->find('input[name="loginkey"]')->val(), // Предположительно временный ключ для авторизации
"loginkeymd" => $datastart->find('input[name="loginkeymd"]')->val(), // Преположительно дополнительный временный ключ для авторизации в MD5 формате
"login" => "ЗДЕСЬ ПИШИТЕ ВАШ НИК", // Логин
"pass" => "ЗДЕСЬ ПИШИТЕ ВАШ ПАРОЛЬ" // Пароль
);

// Конвертируем массив передаваемых POST-данных в формат CP1251, который принимает целевая страница
foreach ($auth as $key => $value)
{
  $auth[$key] = iconv("UTF-8", "CP1251", $value);
}

// Удаляем переменную, поскольку мы уже взяли из неё все необходимые данные
unset($datastart);

// ДЛЯ ОТЛАДКИ. Проверка того, какие POST-данные мы отправляем на целевую страницу
// print_r($auth);

// Делаем CURL-запрос на вход в игру с полученными ранее данными
$output = request('https://www.gwars.ru/login.php', $auth);

// ДЛЯ ОТЛАДКИ. Проверка того, зашли мы в игру или нет (какой ответ вернула целевая страница)
// $output = iconv('windows-1251', 'UTF-8', $output);
// echo $output; 

## КОНЕЦ блока для входа в игру

## СТАРТ блока составления таблицы Excel по данным синдиката

// Отправка CURL-запроса для получения страницы с данными о бойцах синдиката
$sindikat = request('http://www.gwars.ru/syndicate.php?id=6428&page=stats');

// Смена кодировки ответа на корректную
$sindikat = iconv('windows-1251', 'UTF-8', $sindikat);

// Преобразование результата первичного CURL-запроса в PHPQuery документ для последующего парсинга
$sindikatdoc = phpQuery::newDocument($sindikat);

// Поиск всех ников игроков, присутствующих на целевой странице
$sindikatname = $sindikatdoc->find('.gw-container > table:nth-child(12) tr > td:nth-child(2):not(.greenbg)');

// Поиск всех ячеек с количеством проведённым боёв
$sindikatbattles = $sindikatdoc->find('.gw-container > table:nth-child(12) tr > td:nth-child(3):not(.greenbg)');

// Создание массива со значениями из ранее найденных ячеек проведённых боёв
foreach ($sindikatbattles as $key => $element){
  $arsindbat[$key] = pq($element)->text();
}

// Поиск всех ячеек с количеством убийств
$sindikatkills = $sindikatdoc->find('.gw-container > table:nth-child(12) tr > td:nth-child(4):not(.greenbg)');

// Создание массива со значениями из ранее найденных ячеек убийств
foreach ($sindikatkills as $key => $element){
  $arsindkil[$key] = pq($element)->text();
}

// Поиск всех ячеек с количеством очков RP
$sindikatrp = $sindikatdoc->find('.gw-container > table:nth-child(12) tr > td:nth-child(5):not(.greenbg)');

// Создание массива со значениями из ранее найденных ячеек очков RP
foreach ($sindikatrp as $key => $element){
  $arsindrp[$key] = pq($element)->text();
}

// Поиск всех ячеек с количеством заработанного синдикатного опыта
$sindikatexp = $sindikatdoc->find('.gw-container > table:nth-child(12) tr > td:nth-child(6):not(.greenbg)');

// Создание массива со значениями из ранее найденных ячеек с количеством заработанного синдикатного опыта
foreach ($sindikatexp as $key => $element){
  $arsindexp[$key] = pq($element)->text();
  $timesan = $arsindexp[$key];
  $timesan = iconv('UTF-8', 'ASCII//TRANSLIT', $timesan);
  $arsindexp[$key] = preg_replace("/[^0-9]/","",$timesan);
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

foreach ($sindikatname as $key => $element){
  $sheet->setCellValue('A1', "Персонаж");
  $sheet->setCellValue('B1', "Бои");
  $sheet->setCellValue('C1', "Убийства");
  $sheet->setCellValue('D1', "RP");
  $sheet->setCellValue('E1', "Опыт");
  $kfs = $key + 2;
  $sheet->setCellValue('A' . $kfs, pq($element)->text());
  $sheet->setCellValue('B' . $kfs, $arsindbat[$key]);
  $sheet->setCellValue('C' . $kfs, $arsindkil[$key]);
  $sheet->setCellValue('D' . $kfs, $arsindrp[$key]);
  $sheet->setCellValue('E' . $kfs, $arsindexp[$key]);
}

$writer = new Xlsx($spreadsheet);
$writer->save('Sindikat.xlsx');

$dataya = request('https://yandex.ru');
$dataya = phpQuery::newDocument($dataya);
$aktnovost = $dataya->find('.news__item-content');
$aktnovostwithm = "";
foreach ($aktnovost as $element){
  $aktnovostwithm .= "• " . pq($element)->text() . "\n";
}
$chars = ['₽']; // символы для удаления
$aktnovostwithm = str_replace($chars, '', $aktnovostwithm); // PHP код

$weather = $dataya->find('div[class="weather__temp"]')->text();

$charsw = ['−']; // символы для удаления
$weather = str_replace($charsw, '-', $weather); // PHP код

$kurs = $dataya->find('.inline-stocks__item_id_2002 span[class="inline-stocks__value_inner')->text();
$kurs = floatval(preg_replace("/[^-0-9\.]/",".",$kurs));
$kurseun = $kurs * 1.083 * 0.83;
$kurseun = round($kurseun, 2);
$kurseungb = 130000;
$kursgb = $kurseungb / $kurseun;
$kursgb = floor($kursgb);
$kursrubeun = round(1 / $kurseun, 2);
$kursgbrub = round($kurseun / $kurseungb, 4);

$dataanek = request('http://www.mk.ru/anekdoti/');
$dataanek = phpQuery::newDocument($dataanek);
$anekday = $dataanek->find('.big_listing > li:first-child > p[class="text"]')->text();

$dataaf = request('http://aforisimo.ru/random-aforizm.html');
$dataaf = phpQuery::newDocument($dataaf);
$afday = $dataaf->find('#quotation')->text();

$datagosti = request('http://www.gwars.ru/me.php?block=guests');
$datagosti = iconv('windows-1251', 'UTF-8', $datagosti);
$datagosti = phpQuery::newDocument($datagosti);
$gosti = $datagosti->find('#friendsbody a b');

// Получаем страницу поиска по протоколам передач предметов и денег по ключевому слову "донат"
$datadonate = request('http://www.gwars.ru/usertransfers.php?id=1013448&filter=%E4%EE%ED%E0%F2');

// Конвертируем полученную страницу в корректный формат
$datadonate = iconv('windows-1251', 'UTF-8', $datadonate);

// Преобразуем полученную страницу в тип PHPQuery для последующего парсинга нужных частей
$datadonate = phpQuery::newDocument($datadonate);

// Получаем содержимое каждой строчки перевода
$donatealltags = $datadonate->find(".gw-container > nobr:contains('от')");

// Находим все имена задонативших игроков
$donatenames = $datadonate->find(".gw-container > nobr:contains('от') > a > b");

// Объявляем массив, который будем использовать для хранения имен
$donatenamearray = array();

// Объявляем массив, который будем использовать для хранения сумм донатов
$donatemoneyarray = array();

// Разбираем в массив каждую строчку перевода, обрезая начало строки до суммы и находя сумму
foreach ($donatealltags as $key => $tag) {
	$donatetagsarrray[$key] = pq($tag)->text();
    $donatetagsarrray[$key] = substr($donatetagsarrray[$key], 42);
 	preg_match("/^[0-9]+/", $donatetagsarrray[$key], $matches);
	array_push($donatemoneyarray, $matches[0]);
}

// Объявляем многомерный массив, который будем использовать для хранения сумм и имен
$donateallarray = array();

// Собираем многомерный массив из имен и сумм
foreach ($donatenames as $key => $name) {
	$donatenamearray[$key] = pq($name)->text();
  	$donateallarray[$key][0] = $donatenamearray[$key];
   	$donateallarray[$key][1] = $donatemoneyarray[$key];
}

// Объявляем ассициативный массив, где ключом будет являться имя, а сумма - значением
$donateexclusarray = array();

// Перебираем многомерный массив и, если находится повторение имени, то плюсуем сумму доната к имени в ассоциативном массиве
foreach ($donateallarray as $key => $donate) {
	if (!array_key_exists($donateallarray[$key][0], $donateexclusarray)) {
    	$donateexclusarray[$donateallarray[$key][0]] = $donateallarray[$key][1];
	}
	else {
    	$donateexclusarray[$donateallarray[$key][0]] = $donateexclusarray[$donateallarray[$key][0]] + $donateallarray[$key][1];
    }
}

// Отсортировываем ассоциативный массив по убыванию донатов
arsort($donateexclusarray);

// Создаем строку для вывода топ-донатов
$donatestring = '';
foreach ($donateexclusarray as $name => $donate) {
  if ($donateexclusarray[$name] > 1000) {
	$donatestring = $donatestring . '- ' . $name . ' : ' . $donate . ' гб.' . PHP_EOL . '';  
  }
}

//$donatestring = implode(PHP_EOL, $donateexclusarray);
//var_dump($donateexclusarray);

$donate_name_array = array();
foreach ($donatenames as $key => $element){
  $donate_name_array[$key] = pq($element)->text();
}
foreach ($donatealltags as $key => $tag){
  $donatetag = pq($tag);
  $donatemoneysub[$key] = substr($donatetag, 75, 100);
  $donatemoney[$key] = explode(" от ", $donatemoneysub[$key]);
}
//echo $donatenamearray[0];
    //echo $donate_name_array[0];
//echo $donatemoney[0][0];
function donatesshow ($donate_name_array, $donatemoney) { 
  
	foreach ($donate_name_array as $key => $element){
        //echo $element;
      //echo $donatemoney[$key][0];
	}
}
donatesshow($donate_name_array, $donatemoney);

function not_empty_show ($check_empty) {
	$check = (empty($check_empty->text())) ? 'Вы будете первым' : $check_empty->text();
	return $check;
}

$donates_name_show_checked = not_empty_show($donatenames);

$gosti_checked = not_empty_show($gosti);

// Генерация надписи в зависимости от текущего часа
switch (date('G')) {
    case 0:
        $normaltime = "полночь (здравствуй новый день)";
        break;
    case 1:
        $normaltime = "час ночи";
        break;
    case 2:
        $normaltime = "2 часа ночи";
        break;
    case 3:
        $normaltime = "3 часа ночи";
        break;
    case 4:
        $normaltime = "4 часа ночи";
        break;
    case 5:
        $normaltime = "5 часов утра";
        break;
    case 6:
        $normaltime = "6 часов утра";
        break;
    case 7:
        $normaltime = "7 часов утра";
        break;
    case 8:
        $normaltime = "8 часов утра";
        break;
    case 9:
        $normaltime = "9 часов утра";
    break;
    case 10:
        $normaltime = "10 часов утра";
        break;
    case 11:
        $normaltime = "11 часов утра";
        break;
    case 12:
        $normaltime = "полдень";
    break;
    case 13:
        $normaltime = "час дня";
        break;
    case 14:
        $normaltime = "2 часа дня";
        break;
    case 15:
        $normaltime = "3 часа дня";
        break;
    case 16:
        $normaltime = "4 часа дня";
        break;
    case 17:
        $normaltime = "5 часов дня (tea time)";
        break;
    case 18:
        $normaltime = "6 часов вечера";
        break;
    case 19:
        $normaltime = "7 часов вечера";
    break;
    case 20:
        $normaltime = "8 часов вечера";
        break;
    case 21:
        $normaltime = "9 часов вечера";
        break;
    case 22:
        $normaltime = "10 часов вечера";
    break;
    case 23:
        $normaltime = "11 часов вечера";
        break;
}

// Формируем массив, который будем отправлять как POST-запрос для изменения информации "О себе" нашего персонажа
$authizm = array(
"type" => "pinfo", // Предположительно тип посылаемой информации
"save_about" => "1", // Предположтельно флаг о том, что нужно сохранить посланную информацию "О себе"
"lopata" => "ЗДЕСЬ ПИШИТЕ СВОЕ ЗНАЧЕНИЕ КУКИ LOPATA", // Назначение неизвестно
"about" => "[s]Ганжавоин[/s] [i]Прохожий, подскажи время.[/i] Сейчас где-то [u]" . $normaltime . "[/u] с чем-то? ;) Ну так вот тебе [red]актуальные новости[/red] этого часа:\n\n[q]" . $aktnovostwithm . "[/q]\nИнтересует погода? В [blue]Москве[/blue] сейчас [green]" . $weather . "[/green] градусов\n\n[blue]Анекдот дня:[/blue]\n\n[q]" . $anekday . "[/q]\n[red]Афоризм дня:[/red]\n\n[q]" . $afday . "[/q]\n [green]Примерный курс GW-валюты к этому часу:[/green]\n\n[q]1 EUN ~ " . $kurseun . " руб.\n1 EUN ~ " . $kurseungb . " гб.\n1 руб. ~ " . $kursrubeun . " EUN\n1 руб. ~ " . $kursgb . " гб.\n1 гб. ~ " . $kursgbrub . " руб.[/q]\n[b]За последний час мою страницу просматривали:[/b]\n\n[q]" . $gosti_checked . "[/q]\n\n[red][b]ТОП ПОЖЕРТВОВАНИЙ:[/red][/b]\n\n[b]Переведите мне от 1000 гб с пометкой 'Донат' и вы попадете в таблицу ниже (обновляется каждый час). Переводы суммируются :)[/b]\n\n[q]" . $donatestring . "[/q]\n\n Хотите себе такую же интерактивную информацию 'О себе'? Могу создать и настроить её для вас по цене от [b]39 EUN[/b]. Возможна настройка под ваши пожелания. К примеру, можно подгружать информацию о новых сериях ваших любимых сериалов.\n\nБыл направлен запрос пресс-секретарю администрации игры по поводу легитимности использования подобного рода скриптов и был получен ответ о том, что подобная автоматизация [u]разрешена[/u] и [u]не нарушает[/u] правил игры.\n" // Неопсредственно само содержимое информации "О себе"
);

// Конвертируем массив передаваемых POST-данных в формат CP1251, который принимает целевая страница
foreach ($authizm as $key => $value)
{
  $authizm[$key] = iconv("UTF-8", "CP1251", $value);
}
//echo $authizm["about"];

// Отправка запроса на изменение информации "О себе"
$osebe = request('http://www.gwars.ru/info.edit.php', $authizm);

// Отправка запроса на выход из игры
$freeper = request('http://www.gwars.ru/logout.php');

// ДЛЯ ОТЛАДКИ. Показать страницу после отправки запроса на изменение информации "О себе"
// Изменение кодировки полученной страницы
// $osebe = iconv('windows-1251', 'UTF-8', $osebe);
// Вывод полученной страницы
// echo $osebe;

// Вывод надписи для посторонних глаз
echo "Здесь не на что смотреть! А ну-ка отвернитесь от экрана!</br></br>";

// Выводим информацию о времени выполнения скрипта
echo 'Скрипт был выполнен за ' . (microtime(true) - $scriptstart) . ' секунд';

?>