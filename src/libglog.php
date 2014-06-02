<?php

define("LIBGLOG_VERSION", "0.6.3");
define("LIBGLOG_REVISION", '$Rev$');

error_reporting(E_ALL);

if(!defined("GLOG_DO_SYSLOG")) define ("GLOG_DO_SYSLOG", true);
if(!defined("GLOG_SYSLOG")) define ("GLOG_SYSLOG","glog_syslog_".date("Y-m-d").".log.txt");
if(!defined("DATA_DIR")) die("Code: libglog-data-dir");
if(!defined("GLOG_FILE_PREFIX")) define("GLOG_FILE_PREFIX","gelog_");
if(!defined("GLOG_FILE_SUFFIX")) define("GLOG_FILE_SUFFIX",".log.txt");
if(!defined("GLOG_FILE_ENCODING")) define("GLOG_FILE_ENCODING", "UTF-8");
if(!defined("GLOG_WORK_ENCODING")) define("GLOG_WORK_ENCODING", "UTF-8");
if(!defined("GLOG_SEND_EMAIL_SUBJECT_DEFAULT")) define("GLOG_SEND_EMAIL_SUBJECT_DEFAULT", "Новая заявка");
if(!defined("GLOG_TEMPLATES_DIR")) define("GLOG_TEMPLATES_DIR",  "../templates/");
if(!defined("EMAIL")) define("EMAIL","stacmv+libglog@gmail.com");

if(!defined("DIAGNOSTICS_MODE")) define("DIAGNOSTICS_MODE",false);

if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0777, true);
if (!is_dir(DATA_DIR)) die("libglog: code: DATA_DIR");

if(!isset($CFG)) die("libglog: code: CFG"); // конфигурация лэндинга одлжна быть определена в вызывающей программе.

function glog_dosyslog($message) {								// Пишет сообщение в системный лог при включенной опции GLOG_DO_SYSLOG.

    if (GLOG_DO_SYSLOG) {
        if (!is_dir(dirname(GLOG_SYSLOG))) mkdir(dirname(GLOG_SYSLOG), 0777, true);
        // Блокируем файл
        $syslog = GLOG_SYSLOG;
        
        $data = array(
            @$_SERVER["REMOTE_ADDR"],
            date("Y-m-d\TH:i:s"),
            $message,
        );
        
        $message = implode("\t", $data) . "\n";
    
        if (file_put_contents($syslog, $message, FILE_APPEND) === false) {
            $Subject = "Ошибка: ".$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME'];
            $extraheader = "Content-type: text/plain; charset=UTF-8";
            $message= "Невозможно записать данные в системный лог '".$syslog."'!\nНе записанные  данные:\n".$message."\n";
            if ($_SERVER["HTTP_HOST"] == "localhost"){
                die("<h2>".__FUNCTION__.": ".$subject."</h2><p>".$message."</p>");
            }else{
            mail(EMAIL,$Subject,$message,$extraheader);
            };
        };

        return true;
    } else {
        return false;
    };
};
function glog_filter($records,$id) {						// Возвращает массив анкет, удовлетворяющих заданному фильтру.
    //	Фильтр:
    //		id - возвращается массив анкет с заданным id (по идее, массив всегда должен быть из одного элемента).

    $filtered_records =array();
    $pos = glog_find_id($records, $id);
    if ($pos!==false){
        $filtered_records[] = $records[$pos];
        if ($filtered_records[0]["id"]!==$id) {
            $filtered_records = array();
            glog_dosyslog(__FUNCTION__.": ERROR: Найденный id (".$filtered_records[0]["id"].") не совпадает с искомым ($id)!");
        };
    }else{
        glog_dosyslog(__FUNCTION__.": NOTICE: Не найдена запись с id = '$id' в списке записей.");
    };
    
    return $filtered_records;
};
function glog_filter_op($records,$op, $action) {			// Возвращает список анкет с которыми оператор $op выполнил действия $action.
/*
    $action: 
        (в скобках - значение state)
        ok (128) - анкета отправлялась в банк;
        delete (32) - анкета удалялась;
        cancel (0) - анкета возвращалась в пул;
        take (1) - анкета бралась в работу;
        input (0) - анкета введена с сайта;
        ok-final (128) - анкета отправлена в банк;
        delete-final (32) - анкета удалена;
        cancel-final (0) - анкета доступна для обработки (возвращена в пул);
        take-final (1) - анкета взята в работу.
        
        Для действий с окончанием "-final" проверяется только последнее действие истории.
*/
    $action2state = array('ok'=>128, 'delete'=>32, 'cancel'=>0, 'take'=>1, 'input'=>0);
    if (!$records || !$op || !$action) {return array();};
    
    $action = explode("-", $action);
    if (empty($action[1])) {$action[1] = "";};
    
    $f_records ="";
    for ($i=0;$i<count($records);$i++) {
        $ch = count($records[$i]['history']);
        if ($action[1] == "final") {
            // Смотрим последнюю строку истории работы с анкетой
            if (!isset($records[$i]['history'][$ch-1]['state'])) continue;
            $a = $records[$i]['history'][$ch-1]['state'];
            $o = $records[$i]['history'][$ch-1]['op'];
            if (($a == $action2state[$action[0]]) && ($o == $op)) {
                $f_records[] = $records[$i];			
            };
        } elseif ($action[0] == 'input') {
            // Смотрим первую строку истории - кто ввел анкету.
            $a = $records[$i]['history'][0]['state'];
            $o = $records[$i]['history'][0]['op'];
            if (($a == $action2state[$action[0]]) && ($o == $op)) {
                $f_records[] = $records[$i];			

            };
        } else {
            // Смотрим всю историю.
            for ($j=0;$j<$ch;$j++) {
                $a = $records[$i]['history'][$j]['state'];
                $o = $records[$i]['history'][$j]['op'];
                if (($a == $action2state[$action[0]]) && ($o == $op)) {
                    $f_records[] = $records[$i];			
                };
            };
        };
    };
    return $f_records;
};

function glog_filter_state($records, $state) {			// Возвращает список анкет со текущим (последним) статусом $state.
    if (!$records|| !$state) {return array();};
    
    $f_records ="";
    for ($i=0;$i<count($records);$i++) {
        $ch = count($records[$i]['history']);
        if (!isset($records[$i]['history'][$ch-1]['state'])) continue;
        if ($records[$i]['history'][$ch-1]['state'] == $state) {
            $f_records[] = $records[$i];			
        };
    };
    return $f_records;
};
function glog_find_id($records, $id){							/* Возвращает (первую найденную) позицию анкеты с $id в списке $records. */

    if (!$records) {
        glog_dosyslog("WARNING: GLOG_FIND_ID(): Пустой список записей (**".serialize($records)."**).");
        return false;
    };
    if (!$id) {
        glog_dosyslog("WARNING: GLOG_FIND_ID(): Не задан id ($id).");
        return false;
    };
    $pos =false;
    for ($i=0;$i<count($records);$i++) {
        if (!isset($records[$i]['id'])) {
            glog_dosyslog("WARNING: GLOG_FIND_ID(): Не задан id (".@$records[$i]['id'].") у анкеты (предположительно за дату '".@$records[$i]['date']."')(**".@serialize($records[$i])."**).");
            continue;
        };
        if ($records[$i]['id'] == $id) {
                $pos = $i;
                break;
        };
    };
    return $pos;
};
function glog_getcount($curdate, $state=0, $use_cache=true) {	// Возвращает количество анкет с состоянием $state за дату $curdate.
    $cache_file = 'glog_getcount.cache';
    $result=0;
// С версии 0.5.1: расчитанные данные кешируются в файле 'glog_getcount.cache' в виде сериализованного массива.
//	Формат массива:
//		gc[$curdate][$state]['c'] - количество заявок со статусом $state за дату $curdate;
//		gc[$curdate][$state]['ts'] - timestamp - дата актуальности данных кеша, если меньше чем дата последней модификации файла-лога за $curdate, то данные кеша не используются, обновляются.
    
    if (($curdate==date("Y-m-d")) || !glog_rusdate($curdate)) $use_cache=false; // Не используем кэш для текущего дня, а также для виртуальных дат, например, "all", "toModerate".
    
    $cache = @file($cache_file);
    if ($cache != false) {
        $gc = @unserialize($cache[0]);
    } else {
        $gc = array();
    };
    //print_r($gc);
    if (!empty($gc) && isset($gc[$curdate][$state]['c']) && isset($gc[$curdate][$state]['ts']) && ($gc[$curdate][$state]['ts']==@filemtime(glog_get_filename($curdate))) && $use_cache) {
        $result = $gc[$curdate][$state]['c'];	
    } else {
        $records = glog_read($curdate,$state);
        $result = empty($records[0])?0:count($records);
    };

    // Сохраняем расчеты в кэш-файле
    $gc[$curdate][$state]['c'] = $result;
    $gc[$curdate][$state]['ts']=@filemtime(glog_get_filename($curdate));
            
    // Блокируем файл 
    $cache = fopen($cache_file,"a+");
    flock($cache, LOCK_EX);
    // сворачиваем массив в строку 
    $file_content = serialize($gc);
        
    // Перезаписываем файл и снимаем блокировку 
    ftruncate($cache,0);
    fwrite($cache, $file_content);
    fflush($cache);
    flock($cache, LOCK_UN);
    fclose($cache);	
        
    return $result;
};
function glog_get_dates($asc=false, $start_date="", $end_date="") {	    // Возвращает масив $dates[] - список дат, соответствующих найденным лог файлам в каталоге $dir.
    // $asc определяет порядок сортировки возвращаемого массива. $acs=true - по возрастанию. 
    // По умолчанию, $asc = false.
    
    // Даты, для которых есть лог файлы
    // Ищем лог файлы в текущем каталоге и запоминаем их даты в dates[] 
    $dates = array();
    
    $search_pattern = DATA_DIR . GLOG_FILE_PREFIX . "*" . GLOG_FILE_SUFFIX;
    $files = glob($search_pattern);
    
    if (!empty($files)){
        foreach($files as $file){
            if ($gedate = glog_is_glog($file)) {

                if ( $start_date && ($gedate < $start_date) ) continue;
                if (   $end_date && ($gedate > $end_date) ) continue;

                $dates[] = $gedate;
            }else{
                glog_dosyslog("NOTICE: ".__FUNCTION__.": file -'$file' не является файлом лога.");
            };
        };
    }else{
        glog_dosyslog("WARNING: ".__FUNCTION__.": Не обнаружены файлы с данными. шаблон поиска: '".$search_pattern."'.");
    };
            
    
    if ($dates){
        if ($asc) sort($dates);
        else rsort($dates);
    };

    return $dates;   
};
function glog_get_filename($curdate, $checkFileExists=false) {// Возвращает имя файла-лога за дату $curdate
// При checkFileExists=true возвращается false, если файл не существует.
    
    $result = DATA_DIR.GLOG_FILE_PREFIX.$curdate.GLOG_FILE_SUFFIX;
    
    if ($checkFileExists) {
        $result = (glog_is_glog($result)==$curdate)?$result:false;
    };
    
    return $result;
};
function glog_get_record($id, $curdate){
    $record = array();
    
    $records = glog_filter(glog_read($curdate,"all"),$id);	
    if (!empty($records)){
        $record = $records[0];
        if ($records[0]["id"]==$id) {
            $record = $records[0];
        }else{
            glog_dosyslog("ERROR: GET_ANKETA(): Найденный id (".$records[0]['id'].") не совпадает с искомым ($id)!");
        };
    }else{
        glog_dosyslog("WARNING: GET_ANKETA(): Анкета с id='$id' за дату '$curdate' не найдена.");
    };	
    
    if (count($records)>1) glog_dosyslog("WARNING: GET_ANKETA(): Сущестует несколько (".count($records).") анкет с id='$id' за дату '$curdate'!");
    
    return $record;
}

/**
 * Возвращает текущий статус анкеты 
 *
 * Статус по умолчанию возвращается, если у записи нет истории или все статусы записи в спсике игнорируемых.
 * 
 * @param record $record запись
 * @param int $default_state статус по умолчанию
 * @param array $ignore_states список кодов статусов, которые не будут учитываться
 *
 * @result int последний статус записи с учетом игнорируемых статусов
 *
 */
function glog_get_state($record, $default_state = 0, array $ignore_states = array() ) { 
    $result = $default_state;
    
    if (!$record) return $result;
    
    $history = @$record['history'];
    if (!$history) return $result;
    
    $k = count($history)-1;
    
    if (isset($history[$k]["state"])) $result = $history[$k]["state"];
    if ( ! empty($ignore_states) ){
        while (in_array($result, $ignore_states) && ($k >= -1) ){ // Если $k < 0, значит нет подходящих статусов, например, все в списке игнорируемых.
            --$k;
            if (isset($history[$k]["state"])) $result = $history[$k]["state"];
        };
    }
    
    if ($k < 0) $result = $default_state;
    
    return $result;
};
function glog_is_glog($file) {									/* Проверяет, является ли файл $file логом анкет для GEMONEY и возвращает дату лога, выделенную из имени файла в формате "год-месяц-день".
    Иначе возвращает FALSE.*/
    
    $fname = basename($file);
    //echo $file."\<br>";
    if ((substr($fname,0,strlen(GLOG_FILE_PREFIX)) != GLOG_FILE_PREFIX) || (substr($fname, -1*strlen(GLOG_FILE_SUFFIX)) != GLOG_FILE_SUFFIX)) {
        return false;
    } elseif (!is_readable($file)) {
        return false;

    } elseif (!@checkdate($m=substr($fname,strlen(GLOG_FILE_PREFIX)+5,2),$d=substr($fname,strlen(GLOG_FILE_PREFIX)+8,2),$y=substr($fname,strlen(GLOG_FILE_PREFIX),4))) {
        //echo "m:$m; d:$d, y:$y;";
        return false;
    } else {
       return "$y-$m-$d";
    }; 
};
function glog_mark_record($record, $op, $state, $comment, $date = ""){// Помечает анкету, как необработанную, обрабатываемую, обработанную, удаленную и т.п.
/*	Фактически добаляет в историю анкеты запись, сформированную из параметров и
    записывает измененую анкету в файл.
    Возвращает измененную анкету.
*/
      if ($date === "") $date = date("Y-m-d\TH:i:s");
    if (empty($record["date"])) $record["date"] = $date;
    
    $curdate = substr($record['date'],0,10);
    if ($record) {
        $history['date'] = $date;
        $history['op'] = $op;
        $history['state'] = $state;
        $history['comment'] = $comment;
        
        // Предотвращаем дублирование записей истории
        $last = isset($record['history'][count($record['history'])-1])?$record['history'][count($record['history'])-1]:array("date"=>"","op"=>"","state"=>"","comment"=>"");
        if (($last['date']==$history['date']) && ($last['op']==$history['op']) && ($last['state']==$history['state']) && ($last['comment']==$history['comment'])) {
            // Это дубль. Не пишем в файл повторно.
        } else {
            $record['history'][] = $history;
            glog_writesafe($curdate, $record);
        }; 
    };
    return $record;
};
function glog_prepare_data($record){						// Возвращает данные анкеты в формате, подходящем для функции glog_render;
    
    $data = array();
    if (empty($record)) return $data;
    
    // Подготовка данных анкеты для шаблона
    foreach($record as $key_level_1=>$value_level_1){
        if (is_array($value_level_1)){
            foreach ($value_level_1 as $key_level_2=>$value_level_2){
                if (is_array($value_level_2)){
                    foreach($value_level_2 as $key_level_3=>$value_level_3){
                        $data[$key_level_1 . "_" . $key_level_2 . "_" . $key_level_3] =  filter_var( (string) $value_level_3, FILTER_SANITIZE_STRING);
                    };
                }else{
                    
                    if ($key_level_1 == "formdata") $key_level_1 = "form";
                    
                    $data[$key_level_1 . "_" . $key_level_2] =  filter_var( (string) $value_level_2, FILTER_SANITIZE_STRING);
                };
            };
        }else{
            $data[$key_level_1] =  filter_var( (string) $value_level_1, FILTER_SANITIZE_STRING);
        };
    };
    return $data;	
};
function glog_read($curdate, $state) {						// Читает файл DATA_DIR/glog$curdate.txt и возвращает список анкет со статусом $state за нужную дату.
//	В процессе чтения удаляются дуюликаты записей (ID которых совпадает)
//
//	$state: - битовое поле
//		"all": все заявки;
//		0:	Заявка заполнена на сайте, не обработана оператором;
//		1:  Заявка взята оператором №$state на обработку;
//		2:	Заявка на модерации (дополнительной проверке); 
//		16:	Тестовая заявка;
//		32: заявка удалена
//		128: Заявка отправлена в банк
    
    if (is_numeric($state)) $state = (int) $state;
    $records = array();

	// ---------- 2013-03-20 (0.5.3) добавлена поддержка списка дат в виде массива
	if ($curdate=="all" || is_array($curdate) ) {
        if (DIAGNOSTICS_MODE) glog_dosyslog("NOTICE: GLOG_READ(): вызов функции. Параметры: curdate='". (is_array($curdate) ? implode(", ",$curdate) : $curdate) . "', state='$state'. ");
        if ($curdate == "all") $dates = glog_get_dates(true);
        else $dates = $curdate;
        
        glog_dosyslog("NOTICE: GLOG_READ(): glog_get_dates вернула даты: **".implode(",",$dates)."**.");
        foreach($dates as $k=>$v){
            if (glog_getcount($v,$state,true)>0) { // используем кеш glog_getcount.cache
                $records = array_merge($records, glog_read($v,$state));
            };
        };
        if (DIAGNOSTICS_MODE)  glog_dosyslog("NOTICE: GLOG_READ(): вызов функции. Параметры: curdate='$curdate', state='$state'. Прочитано:".(isset($records[0])?count($records):0)." анкет.");
        return $records;
    };
    
    // ----------
    $IDs = ""; //Список существующих в файле ID.
    $doubles_found = false; // будут true, если найдутся дубликаты анктет, их надо будет удалить.
    
    $filename = DATA_DIR.GLOG_FILE_PREFIX.$curdate.GLOG_FILE_SUFFIX;
    if (glog_is_glog($filename) !== false) {
        $log = file($filename);
       
        if (!$log) {return false;};
        $filtered_records = array();
        for ($i=0;$i<count($log);$i++) {
            $record = @Unserialize($log[$i]);
            $id = @$record['id'];
            if ( ! $id ) {
                glog_dosyslog(__FUNCTION__.": ERROR: Found record without id in file '".$filename."': '".$log[$i]."'. Should be discarded.");
                continue;
            }
            if (empty($IDs[$id])) {
                $IDs[$id] = $id;
            } else {
                //пропускаем дубликат
                $doubles_found = true;
                glog_dosyslog(__FUNCTION__.": ERROR: Found record with non-unique id (".$id.") in file '".$filename."': '".$log[$i]."'. Should be discarded.");
                continue;
            }; 
            $history = $record["history"];
            $last_state = $history[count($history)-1]["state"];
            if ($state==="all") {
                $filtered_records[] = $record;
            } elseif ($last_state === $state) {
                $filtered_records[] = $record;
            };	
        };

        if (DIAGNOSTICS_MODE) glog_dosyslog("NOTICE: GLOG_READ(): вызов функции. Параметры: curdate='$curdate', state='$state'. Прочитано:".(isset($filtered_records[0])?count($filtered_records):0)." анкет.");
        return $filtered_records;
    } else {
        glog_dosyslog("WARNING: GLOG_READ(): Не найден glog-файл '$curdate'.");
        return array();
    }; 
};		
function glog_render($template_file, $data){
    $HTML = "";
    
    if (file_exists($template_file)){
        $template = file_get_contents($template_file);
        if (empty($template)){
            glog_dosyslog(__FUNCTION__.": ERROR: Файл шаблона пустой - '".$template_file."'.");
            $template = "<div class='anketa'><fieldset><legend>ID:%%id%% от %%curdate%%</legend><p>Формат представления анкеты не задан.</p></fieldset></div>";
        };
        // parse template.
        $template = str_replace("\r\n", "\n", $template);
        $template = str_replace("\r", "\n", $template);
        
        // Подстановка данных
        foreach($data as $k=>$v){
            $template = str_replace("%%".$k."%%", $v, $template);
        };
            
        $template = preg_replace("/%%[^%]+%%/","",$template); // удаляем все placeholders для которых нет данных во входных параметрах.
        $HTML = $template;
        glog_dosyslog(__FUNCTION__.": NOTICE: Успешно применен шаблон '".$template_file."'.");
    
    }else{
		$HTML = "<p><b>Ошибка!</b> Файл шаблона не найден".(DIAGNOSTICS_MODE ? " - '".$template_file."'" : "")."</p>";
            glog_dosyslog(__FUNCTION__.": ERROR: Файл шаблона не найден - '".$template_file."'.");
    };
    
    return $HTML;	
};
function glog_rusdate($date, $withTime = false) {				/* Принимает дату в формате "гггг-мм-дд" и возвращает в формате "дд.мм.гггг" */
    
    if (preg_match("/\d\d\.\d\d\.\d{4}/", $date)) return $date; // дата уже в формате дд.мм.гггг
    if ($date == "all") return "";
    if ($date == "toModerate") return "";
    $m = (int) substr($date,5,2); $m = str_pad($m, 2, "0", STR_PAD_LEFT);
    $d = (int) substr($date,8,2); $d = str_pad($d, 2, "0", STR_PAD_LEFT);
    $y = (int) substr($date,0,4);
    if (!checkdate($m,$d,$y)) {
        return false;
    } else {
    
        if ($withTime){
            $h = substr($date,11,2); $h = str_pad($h, 2, "0", STR_PAD_LEFT);
            $i = substr($date,14,2); $i = str_pad($i, 2, "0", STR_PAD_LEFT);
            $s = substr($date,17,2); $s = str_pad($s, 2, "0", STR_PAD_LEFT);
            
            return "$d.$m.$y $h:$i:$s";
        }else{
            return "$d.$m.$y";
        }
    }; 
};
function glog_send($record, $mode){
    global $CFG; // настройки отправки анкет задаются в settings.php
    
    $success_str = "Сайт: автоматическая отправка не произведена.";
    $state = 0;

    if (empty($mode)){
        glog_dosyslog(__FUNCTION__.": ERROR: Не задан обязательный параметр - режим отправки анкеты.");
    }else{
       
        $mode = strtok($mode, " ");
        while ($mode){
       
            switch($mode){
                case "email":
                    if (empty($CFG["SEND"][$mode."_to"])){
                        glog_dosyslog(__FUNCTION__.": ERROR: Не задан e-mail для отправки анкет в настройках.");
                        $success_str = "Сайт: автоматическая отправка не удалась из-за некорректных настроек адресатов.";
                        $state = 0;
                    }else{
                    
                        // Ругаемся на плохие настройки
                        if (empty($CFG["SEND"][$mode."_subject"])){                
                            glog_dosyslog(__FUNCTION__.": ERROR: Не задана тема письма для отправки анкет в настройках.");
                        };
                        
                        if (empty($CFG["SEND"][$mode."_template"])){
                            glog_dosyslog(__FUNCTION__.": ERROR: Не задан шаблон письма для отправки анкет в настройках.");
                            $success_str = "Сайт: автоматическая отправка не удалась из-за некорректных настроек шаблона.";
                            $state = 0;
                        }elseif(!is_readable(GLOG_TEMPLATES_DIR.$CFG["SEND"][$mode."_template"].".htm")){
                            glog_dosyslog(__FUNCTION__.": ERROR: Файл шаблона письма не доступен для чтения (возможноне существует): '".GLOG_TEMPLATES_DIR.$CFG["SEND"][$mode."_template"].".htm'.");
                            $success_str = "Сайт: автоматическая отправка не удалась из-за некорректных настроек файла шаблона.";
                            $state = 0;
                        }else{
                            
                            // Пытаемся отправить письма
                            
                            $template = file_get_contents(GLOG_TEMPLATES_DIR.$CFG["SEND"][$mode."_template"].".htm");
                            
                            if (empty($template)) glog_dosyslog(__FUNCTION__.": ERROR: Шаблон письма пуст - ".GLOG_TEMPLATES_DIR.$CFG["SEND"][$mode."_template"].".htm");
                            
                            
                            // parse template.
        
                            $template = str_replace("\r\n", "\n", $template);
                            $template = str_replace("\r", "\n", $template);
                            
                            // Подстановка данных формы
                            foreach($record["formdata"] as $k=>$v){
                                if ($k==$record["full_phone_field"]) $v = "+7 (".substr($v,0,3).") ".substr($v,3,3)."-".substr($v,6,2)."-".substr($v,8,2);
                                $template = str_replace("%%form_".$k."%%", $v, $template);
                            };
                            
                            // Подстановка данных источника
                            foreach($record["src"] as $k=>$v){
                                $template = str_replace("%%src_".$k."%%", $v, $template);
                            };
                            
                            // Подстановка данных анкеты
                            $template = str_replace("%%id%%", $record["id"], $template);
                            $template = str_replace("%%date%%", $record["date"], $template);    
                            $template = str_replace("%%IP%%", $record["IP"], $template);
                            $template = str_replace("%%host%%", $record["host"], $template);    
                            
                            $template = preg_replace("/%%[^%]+%%/","",$template); // удаляем все placeholders для которых нет данных во входных параметрах.
                            
                            
                            $to = explode(",",$CFG["SEND"][$mode."_to"]); foreach($to as $k=>$v) $to[$k] = trim($v);
                            $from = @$CFG["SEND"][$mode."_from"] or $from = EMAIL;
                            $subject = @$CFG["SEND"][$mode."_subject"] or  $subject = GLOG_SEND_EMAIL_SUBJECT_DEFAULT;
                            // $subject = "=?UTF-8?B?".base64_encode($subject)."?=";
                            $headers = "MIME-Version: 1.0\r\n"; 
                            $headers .= "content-type: text/html; charset=UTF-8\r\nFROM: ".$from."\r\nREPLY-TO: ".$from;
                            
                            $message = $template;

                            
                            $success_email = array(); // список email'ов, на которые успешно отправлена анкета.
                            foreach($to as $email_to){
                                if (mail($email_to, $subject, $message, $headers)){
                                    glog_dosyslog(__FUNCTION__.": OK: Анкета id:".@$record["id"]." от ".substr($record["date"],0,10)." отправлена на '".$email_to."'.");
                                    $success_email[] = $email_to;
                                }else{
                                    glog_dosyslog(__FUNCTION__.": ERROR: Не удалось отправить анкету id:".@$record["id"]." от ".substr($record["date"],0,10)." на '".$email_to."'.");
                                }
                                if (count($to) > 2) sleep(.5); // если адресатов больше 2, ждем по .5 секунды между отправками писем.
                            };
                            
                            if (!empty($success_email)){
                                $success_str = "Сайт: Анкета отправлена по e-mail на ".(count($success_email) == count($to) ? "адреса" : count($success_email) . " из " . count($to) . " адресов") . ": " . implode(", ", $success_email);
                                $state = 128;
                            }else{
                                $success_str = "Сайт: Анкета не отправлена по e-mail";
                                $state = 0;
                            };
                        };
                    };
                    
                    // Специальная отметка об отправке анкеты, чтобы не парсить историю.
                if (empty($record["sent"])) $record["sent"] = array();
                if (empty($record["sent"][$mode])) $record["sent"][$mode] = "";
                if ($state == 128) $record["sent"][$mode] = time();
                
                
                    $record = glog_mark_record($record, $record["host"], $state, $success_str.".IP:".@$_SERVER["SERVER_ADDR"]);
                                            
                    break;
                case "fake":
                    $state = 128;
                    $success_str = "Заявка помечена как отправленная";
                    if (empty($record["sent"])) $record["sent"] = array();
                    if (empty($record["sent"][$mode])) $record["sent"][$mode] = "";
                    if ($state == 128) $record["sent"][$mode] = time();
                
                    $record = glog_mark_record($record, $record["host"], $state, $success_str.".IP:".@$_SERVER["SERVER_ADDR"]);
                    break;
                default:
                    if (function_exists("send_".$mode)){
                        $record = call_user_func("send_".$mode, $record);
                    }else{
                        glog_dosyslog(__FUNCTION__.": ERROR: Не задана функция отправки для режима ".$mode.". Анкета не будет отправлена.");
                    };                    
                    break;
            }; // switch
            
            $mode = strtok(" ");
        }; // while
    };  
    
    return $record;
};
function glog_track_click($anketa, $tracker, $aid){                               // Передает данные о продаже лида в партнерскую программу Апельсин и другие внешние системы, ставит куки для "пикселей" внешних систем
    global $CFG; // конфигурация лэндинга
    
    $track_modes = $CFG["TRACK"]["click_modes"];
    $result = array();
    if ($track_modes){
        $mode = strtok($track_modes, " ");
        while($mode) {
            if ( strtolower($tracker) == strtolower($mode) ){
                $func = "track_click_".$mode;
                if (function_exists($func)){
                    glog_dosyslog(__FUNCTION__.": NOTICE: Регистрация клика в '".$mode."'...");
                    $anketa = call_user_func($func, $anketa, $aid);
                }else{
                    glog_dosyslog(__FUNCTION__.": NOTICE: Регистрация клика в '".$mode."' не возможна, т.к. не определена функция '".$func."'.");
                };
            }
            $mode = strtok(" ");
        }
    }else{
        glog_dosyslog(__FUNCTION__.": NOTICE: Не заданы режимы отслеживания / оповещения внешних систем.");
    };            
   
    return $anketa;
};
function glog_track_lead($anketa, $track_mode="", $track_what="lead"){                               // Передает данные о продаже лида в партнерскую программу Апельсин и другие внешние системы, ставит куки для "пикселей" внешних систем
    global $CFG; // конфигурация лэндинга
    
    $track_modes = @$CFG["TRACK"]["modes"];
           
    if ($track_modes){
        $mode = strtok($track_modes, " ");
        while($mode) {
        
            if ( ($mode == $track_mode) || ! $track_mode ){ // если задан track_mode, то он должен быть в числе допустимых (определенных в $CFG).
            
                $func = "track_lead_".$mode;
                if (function_exists($func)){
                    glog_dosyslog(__FUNCTION__.": NOTICE: Оповещение '".$mode."'...");
                    $anketa = call_user_func($func, $anketa, $track_what);
                }else{
                    glog_dosyslog(__FUNCTION__.": NOTICE: Оповещение '".$mode."' не возможно, т.к. не определена функция '".$func."'.");
                }
            };
            $mode = strtok(" ");
        }
    }else{
        glog_dosyslog(__FUNCTION__.": NOTICE: Не заданы режимы отслеживания / оповещения внешних систем.");
    };            
   
    return $anketa;
};

function glog_write($curdate, $record){ 					/* Записывает анкету в лог за дату $curdate, который должен существовать.
    Если анкета с таким id существует, она заменяется. 
    $curdate - дата файла-лога;
    $record - анкета (данные формы, история изменений, дата, id,..)
    DATA_DIR - каталог с лог-файлами.
    
    Возвращает true в случае успеха и false в случае неудачи.
*/
    $lock_suffix = ".glog_write_lock";
    
    $file = DATA_DIR.GLOG_FILE_PREFIX.$curdate.GLOG_FILE_SUFFIX;
    /* Блокируем файл */
    $new_log = fopen($file,"a+");
    //if (!flock($new_log, LOCK_EX)){
        $lock_method = "own";
        $wait_till = time() + 20; // ждем освобождения файла 20 секунд.
        while(file_exists($file.$lock_suffix)){
            if (time() > $wait_till){
                glog_dosyslog(__FUNCTION__.": Превышен таймаут блокировки файла $file.");
                return false;
            };
        };            
        touch($file.$lock_suffix);
    //}else{
      //  $lock_method = "sys";
      //  die("here2");
    //};
        
    
    /* читаем лог-файл, получаем распакованный массив */
    
    $records = glog_read($curdate,"all");
       
    /* ищем позицию анкеты с заданным id */
    $pos = glog_find_id($records, $record['id']);
    
    /* читаем лог-файл, получаем массив строк */
    if (!empty($records)){
        foreach ($records as $k=>$v) {
            $log[$k] = Serialize($v).".\n";
        };
    };
    
    if (!isset($log)) {$log = array();}; //Файла нет, будем создавать новый.
        
    /* вставляем нашу анкету в определенную ранее позицию */
    if ($pos === false ) { //Анкета не найдена в файле, т.е. новая, или файл новый.
        $log[] = Serialize($record).".\n";
    } else {
        if (DIAGNOSTICS_MODE) {
            $Subject = "Диагностика: ".$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME'];
            $extraheader = "Content-type: text/plain; charset=windows-1251";
            $message= "Обнаружен дубликат анкеты.\nДанные анкеты:\n".$log[$pos]."\nперезаписаны данными:\n".Serialize($record);
            @mail(EMAIL,$Subject,$message,$extraheader);
        };
        $log[$pos] = Serialize($record).".\n";
    };
     
    /* сворачиваем массив в строку */
    $file_content = implode("", $log);
        
    /* Перезаписываем файл и снимаем блокировку */
    ftruncate($new_log,0);
    fwrite($new_log, $file_content);
    fflush($new_log);
    if ($lock_method == "sys"){
        flock($new_log, LOCK_UN);
    }else{ //own
        if (file_exists($file.$lock_suffix)){
            @unlink($file.$lock_suffix);
        }else{
            glog_dosyslog(__FUNCTION__.": ERROR: не найден признак блокировки файла $file.");
        };
    };
    fclose($new_log);

    return true;
};
function glog_writesafe ($curdate, $record, $email=EMAIL) {	/* Записывает анкету в лог за дату $curdate.
    Если запись не выполнена, сообщение об ошибке и незаписанные данные, во избежание потери,
    отправляются на e-mail.
*/

    glog_dosyslog("NOTICE: GLOG_WRITEGLOGSAFE(): Попытка записи анкеты id='".$record['id']."' за дату '$curdate'.");
    // Ниже используется тот факт, что glog_rusdate возвращает false для неправильно заданных дат. 
    if (!glog_rusdate($curdate)) { // Если $curdate - виртуальная дата, например "toModerate" или "all", то берем дату из самой анкеты.
        if (isset($record['date'])) {
            $curdate = substr($record['date'],0,10);
            if (!glog_rusdate($curdate)) { // в анкете записана дата в неправильном формате.
                $curdate = date("Y-m-d");
                $record['date'] = date("Y-m-d\TH:i:s");
            };
        }else{
            $curdate = date("Y-m-d");
            $record['date'] = date("Y-m-d\TH:i:s");
        };
        glog_dosyslog("NOTICE: GLOG_WRITEGLOGSAFE(): Скорректирована дата файла для анкеты с id='".$record['id']."' на дату '$curdate'.");
    };
    

    if (glog_write($curdate, $record) == false)	{
        glog_dosyslog("ERROR: GLOG_WRITEGLOGSAFE(): Ошибка сохранения записи с id='".$record['id']."' в файле на дату '$curdate'.");
        
        $Subject = "Ошибка: ".$_SERVER['HTTP_HOST'];
        $extraheader = "Content-type: text/plain; charset=windows-1251";
        $message= "Невозможно открыть файл ".DATA_DIR."/glog".date("Y-m-d").".txt для обновления лога.\nURL, вызвавший ошибку: ".$_SERVER["QUERY_STRING"].".\nНе записанные данные:\n\n".Serialize($record);
        mail($email,$Subject,$message,$extraheader);
    };
    return true;
};
// ----------------
function glog_get_state_comment($anketa, array $ignore_states = array() ) { // Возвращает коментарий оператора последнего (текущего) статуса анкеты.
    $result = false;
    
    if (!$anketa) return $result;
    
    $history = @$anketa['history'];
    if (!$history) return $result;
    
    $k = count($history)-1;
    if (isset($history[$k]["comment"])){
        $result = $history[$k]["comment"];
        $state = @$history[$k]["state"];
    }
    if ( ! empty($ignore_states) ){
        while (in_array($state, $ignore_states) && ($k >= -1) ){ // Если $k < 0, значит нет подходящих статусов, например, все в списке игнорируемых.
            --$k;
            if (isset($history[$k]["comment"])){
                $result = $history[$k]["comment"];
                $state = @$history[$k]["state"];
            }
        };
    }
    
    $matches = array();
    preg_match("/\(([^\)]*)\)/",$result, $matches);
    $result = isset($matches[1])?$matches[1]:$result;
    
    return $result;
};
function glog_get_state_name($state) { //возвращает наименование статуса анкеты по его коду.
    global $glog_states; // статусы, устанавливаемые в клиентском коде
    $result = "";
    
    if (!empty($glog_states)){
        if (!empty($glog_states[$state])){
            $result = $glog_states[$state];
        }else{
            $result = "Статус " . $state; 
        };
    }else{
        switch ($state){
            case 0: $result = "Не обработана"; break;
            case 1: $result = "В работе"; break;
            case 2: $result = "На модерации"; break;
            case 4: $result = "Не заполнена"; break;
            case 32: $result = "Удалена"; break;
            case 64: $result = "Не приянята"; break;
            case 128: $result = "Отправлена"; break;
            default: $result = "Не известно ($state)";
        };    
    };
    return $result;
};

function glog_export($anketas, $format="php", $fields="", $params="") { //  Возвращает данные анкет в виде таблицы
// format = php | php-serial | json | tsv
    global $ERROR;
    
    $log = array();
    

    if (!$anketas || empty($anketas)) {
        $ERROR[] = __FUNCTION__.": пустой список анкет.";
        return ($format == "php" ? array() : "");
    };
    
    if (empty($fields)){
        $fields = array(
            "id" => "id",
            "Дата" => "date",
            "Ф.И.О." => "full_name",
            "Телефон" => "full_phone",
            "Регион" => "region",
            "Пол" => "sex",
            "Возраст" => "age",
            "Статус" => "state"
        );
    };
    
    if (!empty($params["state"])){
        $state = $params["state"];
    }else{
        $state = "all";
    };
                
    if ( ($state==32) || ($state==2) ) { // для отчета по удаленным анкетам
        if (isset($fields["Статус"])){
            $fields["Причина удаления"] = "comment";
        }else{
            die("ERROR: ".__FUNCTION__.": Mandatory item in fields array not found.");
        };
    };		
    
    foreach ($anketas as $anketa) {
        $srca = @$anketa["src"];
        $aid = @$srca["aid"];
        $fid = @$srca["fid"];
        
        if ( ! empty($params["aid"]) && ( $params["aid"] !== $aid ) ) continue; // отборр лидов заданного партнера

        $date = substr(@$anketa["date"],0,10);
        $time = substr(@$anketa["date"],11);
        $campaign = @trim(stripslashes(@$srca["campaign"])); //if(!$campaign) $campaign = "&nbsp;";
        $keyword = @trim(stripslashes(@$srca["keyword"])); //if(!$keyword) $keyword = "&nbsp;";
        $refsite = @trim(stripslashes(@$srca["refsite"])); //if(!$refsite) $refsite = "&nbsp;";
        $matches = array();
        if (@preg_match("/([A-Z]{2})$/",@$srca["src"],$matches) == 1){
            $gorod = $matches[1];
        } else {
            $gorod = "";
        };            
        $region = trim(@$anketa["formdata"][$anketa["region_field"]]); //if(!$region) $region = "&nbsp;";

        if ( ($state==32) || ($state==2) ){ // для отчета по удаленным анкетам
            $comment = glog_get_state_comment($anketa);
            if ($state==2){
                $comment = substr($comment, strpos($comment, ":")+1); // удаляем имя оператора из комментария.
            }
        }
            
        $cur_state = glog_get_state_name(glog_get_state($anketa));
        
        
        $id = @$anketa["id"];
        $sex = @$anketa["formdata"][$anketa["sex_field"]];
        $age = glog_get_age($anketa);
        
        $data = array();
        foreach($fields as $k=>$v){
            if( function_exists("export_field") ){
                $data[$k] = call_user_func("export_field", $anketa, $v);
            };
            
            if (empty($data[$k])){            
                if (isset($anketa["formdata"][$v])){  							// явно заданное поле формы
                    $data[$k] = $anketa["formdata"][$v];
                }elseif(isset($anketa["formdata"][@$anketa[$v . "_field"]])){		// косвенно заданное поле формы
                    $data[$k] = $anketa["formdata"][$anketa[$v . "_field"]];
                }elseif(isset($anketa[$v])){ 									// свойство анкеты
                    $data[$k] = $anketa[$v];
                
                }else{
                    $data[$k] = "н/д";
                };
                
                if(isset($$v)){												// специально вычисленное выше значение
                    $data[$k] = $$v;
                };
            };
        };
        
        
         
        // Перекодирование
        if(GLOG_WORK_ENCODING != GLOG_FILE_ENCODING){
            foreach($data as $k=>$v) if ($k!="Статус") $data[$k] = iconv(GLOG_FILE_ENCODING, GLOG_WORK_ENCODING, $v);
        };
        // ---------------
        
        switch ($format){
            case "php":
            case "php-serial":
            case "json":
            case "html":
                $log[] = $data;
                break;
            case "tsv":
                if (empty($log)){
                    $header = implode("\t",array_keys($data));
                    if(GLOG_WORK_ENCODING != GLOG_FILE_ENCODING){
                        $header = iconv(GLOG_FILE_ENCODING, GLOG_WORK_ENCODING, $header);
                    };
                    $log[] = $header; // вставляем шапку таблицы первой строкой
                };
                $log[] = implode("\t",array_values($data));
                break;
        };

    };
    
    switch ($format){
        case "php":
            // do nothing
            break;
        case "php-serial":
            $log = serialize($log);
            break;
        case "json":
            $log = json_encode($log);
            break;
        case "tsv":
            $log = implode("\n",$log);
            break;
        case "html":
            if ( ! empty($log) ){
                $HTML = "<table class='leads'>
                            <thead><tr><th>#</th><th>" . implode("</th><th>", array_keys($log[0])) . "</th></tr></thead>";
                foreach($log as $k=>$v){
                    $HTML .= "<tr><td>".($k+1)."</td><td>" . implode("</td><td>", array_values($v)) . "</td></tr>";
                };
                $HTML .= "</table>";
            }else{
                $HTML = "<div class='alert alert-info'>Нет заявок за выбранный период.</div>";
            }
            $log = array("count"=>count($log), "HTML"=>$HTML);
            break;
    };

    return $log;
    
};
// ----------------
function glog_get_age($anketa, $add_units = false) { 				// Возвращает текущий возраст в формате строки "n" ($add_units = false) или "n лет" ($add_units = true). Принимает анкету.
    
    $age = "";
    
    if (!empty($anketa["age_field"]) && !empty($anketa["formdata"][$anketa["age_field"]])){
        $age = $anketa["formdata"][$anketa["age_field"]];    
    }else{
        if(!empty($anketa["birthdate_field"])){
            $birthdate = @$anketa["formdata"][$anketa["birthdate_field"]];
            $byear = @substr($birthdate,0,4);
            $bmonth = @substr($birthdate,5,2);
            $bday = @substr($birthdate,8,2);
        }else{
            $byear = @$anketa["formdata"][$anketa["birth_year_field"]];
            $bmonth = @$anketa["formdata"][$anketa["birth_month_field"]];
            $bday = @$anketa["formdata"][$anketa["birth_day_field"]];
        };

        if ($byear || $bmonth || $bday){
            $age = (date('Y')-$byear);
            if ((int)$bmonth > (int)date('m')){
                $age--;
            } elseif (((int)$bmonth == (int)date('m')) && ((int) $bday > (int) date('d'))) {
                $age--;
            };
        };
    };
    
    if ($add_units){
        switch (substr($age,-1,1)) {
            case 1:
                $suf = "год";
                break;
            case 2:
            case 3:
            case 4:
                $suf = "года";
                break;
            case 5:
            case 6:
            case 7:
            case 8:
            case 9:
            default:
                $suf = "лет";
        };
        $age = $age." ".$suf;
    };
    return $age;
};
function glog_codify($str){                                         // Возвращает строку в виде, пригодном для использования в именах файлов, url, css-классах, ... .
	$result = glog_translit($str);
    
	$result = str_replace(array("+","&"," ",",",":",";",".",",","/","\\","(",")","'","\""),array("_plus_","_and_","-","-","-","-"),$result); 
    
	$result = strtolower($result);
    
	$result = urlencode($result);
	
	return $result;
};
function glog_translit($s) {                                        //Возвращает транслитирированную строку.
    $result = $s;

    $result = str_replace(array("а","б","в","г","д","е","ё","з","и","й","к","л","м","н","о","п","р","с","т","у","ф","х","ы","э"), array("a","b","v","g","d","e","e","z","i","j","k","l","m","n","o","p","r","s","t","u","f","h","y","e"), $result);
    $result = str_replace(array("А","Б","В","Г","Д","Е","Ё","З","И","Й","К","Л","М","Н","О","П","Р","С","Т","У","Ф","Х","Ы","Э"), array("A","B","V","G","D","E","E","Z","I","J","K","L","M","N","O","P","R","S","T","U","F","H","Y","E"), $result);
	
	$result = str_replace(array("ж","ц","ч","ш","щ","ю","я","ъ","ь"), array("zh","ts","ch","sh","sch","yu","ya"),$result);
	$result = str_replace(array("Ж","Ц","Ч","Ш","Щ","Ю","Я","Ъ","Ь"), array("ZH","TS","CH","SH","SCH","YU","YA"),$result);

	return $result;
};
function glog_show_phone($phone_cleared){ 						    // Форматирует номер телефона (только цифры) к  виду (123) 456-78-90
	return "(" . substr($phone_cleared, 0, 3) . ") " . substr($phone_cleared, 3, 3) . "-" . substr($phone_cleared, 6, 2) . "-" . substr($phone_cleared, 8, 3);
}
function glog_clear_phone($phone){                              	// возвращает телефон в формате 9031234567 - только цифры
	$phone_cleared = "";
	for($i=0,$l=strlen($phone); $i<$l; $i++){
		if ( ($phone{$i} >= '0') && ($phone{$i} <= '9') ){
			$phone_cleared .= $phone{$i};
		};
	};
	return $phone_cleared;
}


// ----------------
if (!function_exists("dump")){
    function dump($var, $title="") {						// Печатает дамп переменной, окруженной тегами PRE
        if ($title) echo "$title : \n";
        echo "<pre>";
        var_dump($var);
        echo "</pre>"; 
    };
};
// ----------------

?>