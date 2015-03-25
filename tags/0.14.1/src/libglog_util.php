<?php

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
function glog_isodate($date = "", $withTime = false) {				/* Принимает дату в формате "дд.мм.гггг" и возвращает в формате "гггг-мм-дд" */
    
    if ( ! $date ) $date = date("Y-m-d");
    
    if (preg_match("/\d{4}\-\d\d\-\d\d/", $date)) return $date; // дата уже в формате iso
    
    if ( is_numeric($date) ){ // unix timestamp
        if ($withTime){
            return date("c", $date);
        }else{
            return date("Y-m-d");
        };
    };
    
    $m = (int) substr($date,3,2); $m = str_pad($m, 2, "0", STR_PAD_LEFT);
    $d = (int) substr($date,0,2); $d = str_pad($d, 2, "0", STR_PAD_LEFT);
    $y = (int) substr($date,6,4);
    if (!checkdate($m,$d,$y)) {
        return false;
    } else {
    
        if ($withTime){
            $h = substr($date,11,2); $h = str_pad($h, 2, "0", STR_PAD_LEFT);
            $i = substr($date,14,2); $i = str_pad($i, 2, "0", STR_PAD_LEFT);
            $s = substr($date,17,2); $s = str_pad($s, 2, "0", STR_PAD_LEFT);
            
            return "$y-$m-$d $h:$i:$s";
        }else{
            return "$y-$m-$d";
        };
    }; 
};

function glog_rusdate($date="", $withTime = false) {				/* Принимает дату в формате "гггг-мм-дд" и возвращает в формате "дд.мм.гггг" */
    
    if ( ! $date ) $date = date("Y-m-d");
    
    if (preg_match("/\d\d\.\d\d\.\d{4}/", $date)) return $date; // дата уже в формате дд.мм.гггг
    if ($date == "all") return "";
    if ($date == "toModerate") return "";
    
    if ( is_numeric($date) ){ // unix timestamp
        $date = date("c", $date);
    };
    
    $m = (int) substr($date,5,2); $m = str_pad($m, 2, "0", STR_PAD_LEFT);
    $d = (int) substr($date,8,2); $d = str_pad($d, 2, "0", STR_PAD_LEFT);
    $y = (int) substr($date,0,4);
    if (!checkdate($m,$d,$y)) {
        return false;
    } else {
    
        if ($withTime){
            if (strlen(substr($date, 11)) == 4){ // время без секунд
                $h = substr($date,11,2); $h = str_pad($h, 2, "0", STR_PAD_LEFT);
                $i = substr($date,14,2); $i = str_pad($i, 2, "0", STR_PAD_LEFT);
                
                return "$d.$m.$y $h:$i";
            }else{
                $h = substr($date,11,2); $h = str_pad($h, 2, "0", STR_PAD_LEFT);
                $i = substr($date,14,2); $i = str_pad($i, 2, "0", STR_PAD_LEFT);
                $s = substr($date,17,2); $s = str_pad($s, 2, "0", STR_PAD_LEFT);
                
                return "$d.$m.$y $h:$i:$s";
            };
        }else{
            return "$d.$m.$y";
        }
    }; 
};
function glog_weekday($day_no="", $short = false, $lang="RU"){                                 // Возвращает наименгование для недели по его номеру (0 - вс, 6 - сб )

    $day_names = glog_weekdays($lang);
    
    if ( ! $day_no){
        $day_no = date("w");
    };
    
    if ( isset($day_names[$day_no]) ){
        return $day_names[$day_no];
    }else{
        glog_dosyslog(__FUNCTION__.": ERROR: ". get_callee() . ": Bad day number '".$day_no."'.");
        return "";
    } 
    
}
function glog_weekdays($short = false, $lang="RU"){

    if ( $short ) {
        return array(
            1 => "пн",
            2 => "вт",
            3 => "ср",
            4 => "чт",
            5 => "пт",
            6 => "сб",
            0 => "вс"
        );
    }else{
        return array(
            1 => "понедельник",
            2 => "вторник",
            3 => "среда",
            4 => "четверг",
            5 => "пятница",
            6 => "суббота",
            0 => "воскресенье"
        );
    };
};

function glog_get_age($anketaORbirthdate, $add_units = false) { 				// Возвращает текущий возраст в формате строки "n" ($add_units = false) или "n лет" ($add_units = true). Принимает анкету.
    
    $age = "";
    
    if ( is_string($anketaORbirthdate) ){
        $birthdate = $anketaORbirthdate;
        
        $birthdate = glog_isodate($birthdate);
        
        $byear  = substr($birthdate,0,4);
        $bmonth = substr($birthdate,5,2);
        $bday   = substr($birthdate,8,2);
        
    }else{
        $anketa = $anketaORbirthdate;
   
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
        };
    };

    if ($byear || $bmonth || $bday){
        $age = (date('Y')-$byear);
        if ((int)$bmonth > (int)date('m')){
            $age--;
        } elseif (((int)$bmonth == (int)date('m')) && ((int) $bday > (int) date('d'))) {
            $age--;
        };
    };
    
    
    if ( $add_units ) $age = glog_get_age_str($age);    
    
    return $age;
};
function glog_get_age_str($age){    // возвращает строку вида "n лет"
    
    return glog_get_num_with_unit($age, "год","года", "лет");

}
function glog_get_num_with_unit($num, $unit1="", $unit2_4="",$unit5_9=""){    // возвращает строку вида "n чего-нибудь"
    
    switch (substr($num,-1,1)) {
        case 1:
            $suf = $unit1;
            break;
        case 2:
        case 3:
        case 4:
            $suf = $unit2_4;
            break;
        case 5:
        case 6:
        case 7:
        case 8:
        case 9:
        default:
            $suf = $unit5_9;
    };
    
    
    return trim($num." ".$suf);
}
function glog_codify($str){                                         // Возвращает строку в виде, пригодном для использования в именах файлов, url, css-классах, ... .
	
    if ( ! is_string($str) ){
        glog_dosyslog(__FUNCTION__.get_callee().": ERROR: Parameter str should be string, ".gettype($str)." given.");
    };
    
    $result = glog_translit($str);
    
	$result = str_replace(array("+","&"," ",",",":",";",".",",","/","\\","(",")","'","\""),array("_plus_","_and_","-","-","-","-"),$result); 
    
	$result = strtolower($result);
    
	$result = str_replace("%","_prc_", urlencode($result));
	
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
function glog_show_array_count($arr, $sort=true){ 

    $unique_arr = array_unique($arr);
    $cu = count($unique_arr);
    $ca = count($arr);

    $id = uniqid("id");
       
    if ($ca>0){
        $HTML = '<a href="#" id="'.$id.'_link" onclick="var el = document.getElementById(\''.$id.'\'); if (el.style.display == \'none\') el.style.display=\'block\'; else el.style.display=\'none\'; return false;">'.($ca == $cu ? $ca : $cu . "/" . $ca).'</a>';
        $HTML .= "<div id='".$id."' style='display:none;'>";
        if ($sort) sort($arr);
        for($i=0; $i<$ca;$i++){
            if ($i && $arr[$i] == $arr[$i-1]){
                $HTML .= "<br>" . "<span style='color:#ccc'>".$arr[$i]."</span>";
            }else{
                $HTML .= "<br>" . $arr[$i];
            }
        }
        $HTML .= "</div>";
    }else{
        $HTML = $ca;
    }
    return $HTML;
}

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

function glog_file_read($file_name, $title="" ){
    $res = "";
    
    if (file_exists($file_name)){
        $res = @file_get_contents($file_name);
        
        if ( ! $res ){
            if ($res === ""){
                glog_dosyslog(__FUNCTION__ . ": WARNING: Файл '" . $file_name . "' пустой.");
            }else{
                glog_dosyslog(__FUNCTION__ . ": ERROR: Ошибка чтения " . $file_name );
            };
        };
    }else{
        glog_dosyslog(__FUNCTION__ . ": WARNING: Файл '" . $file_name . "' не существует.");
    }

    return $res;
}
function glog_file_read_as_array($file_name){
    $res = array();
    
    if (file_exists($file_name)){
        $res = @file($file_name, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ( ! $res ){
            if ($res === array()){
                glog_dosyslog(__FUNCTION__ . ": WARNING: Файл '" . $file_name . "' пустой.");
            }else{
                glog_dosyslog(__FUNCTION__ . ": ERROR: Ошибка чтения " . $file_name );
            };
        };
    }else{
        glog_dosyslog(__FUNCTION__ . ": WARNING: Файл '" . $file_name . "' не существует.");
    }

    return $res;
}
function glog_mail_create_multipart( $text, $attachment_content, $attachment_name="", $from="" ){
    
    
    if ( ! $attachment_name ) $attachment_name = "glog_attachment_" . date("YmdHis");
    
    
    $un        = strtoupper(uniqid(time()));
    
    $headers   = "";
    if (!empty($from)) $headers .= "From: $from\n";
    $headers  .= "X-Mailer: Glog\n";
    if (!empty($from)) $headers .= "Reply-To: $from\n";
    $headers  .= "Mime-Version: 1.0\n";
    $headers  .= "Content-Type:multipart/mixed;";
    $headers  .= "boundary=\"----------".$un."\"\n\n";
    
    $message   = "------------".$un."\nContent-Type:text/html;charset=" . GLOG_FILE_ENCODING . "\n";
    $message  .= "Content-Transfer-Encoding: 8bit\n\n$text\n\n";
    $message  .= "------------".$un."\n";
    $message  .= "Content-Type: application/octet-stream;";
    $message  .= "name=\"".basename($attachment_name)."\"\n";
    $message  .= "Content-Transfer-Encoding:base64\n";
    $message  .= "Content-Disposition:attachment;";
    $message  .= "filename=\"".basename($attachment_name)."\"\n\n";
    $message  .= chunk_split(base64_encode( $attachment_content ))."\n";

    return array("message"=>$message, "headers"=> $headers);
}


function glog_http_get($url, $use_cache = true, $user_agent = ""){
    return glog_http_request("GET", $url, array(), $use_cache, "", $user_agent);
};
function glog_http_post($url, $data, $use_cache = true, $content_type="", $user_agent = ""){
    return glog_http_request("POST", $url, $data, $use_cache, $content_type, $user_agent);
};
function glog_http_request($method, $url, $data, $use_cache = true, $content_type = "", $user_agent = ""){ // Выполняет HTTP запрос методом $method на $url с параметрами $data

    $cache_ttl = 60*60; // 1 час
    $cache_dir = DATA_DIR . ".cache/" . __FUNCTION__ . "/";
    if ( ! is_dir($cache_dir) ) mkdir($cache_dir, 0777, true);

    $max_tries = 5;
    $sleep_coef = .5;
    $max_response_length_for_log = 50;
    
    $result = "";
    $request_id = uniqid();
    
    $method = strtoupper($method);
    if ( ! $content_type && ( $method == "POST") ) $content_type = 'Content-type: application/x-www-form-urlencoded';
    
    if ($method == "POST") $postdata = http_build_query($data);
    
	$opts = array('http' => array( 'method'  => $method ) );
    if ( ! empty($content_type) ) $opts["http"]['header']     = $content_type;
    if ( ! empty($user_agent) )   $opts["http"]['user_agent'] = $user_agent;
    if ( ! empty($postdata) )     $opts["http"]['content']    = $postdata;

    
    glog_dosyslog(__FUNCTION__.": NOTICE: " . $method . "-запрос " . $request_id . " на '" . $url . "'" . ( ! empty($postdata) ? " с данными '" . urldecode($postdata) . "'" : "" ) . " ... ");
    
    $tries = $max_tries;
    
    $hash = md5( serialize( func_get_args() ) );
    $cache_file = $cache_dir . $hash . ".php";
    if ( $use_cache ){        
        if ( file_exists($cache_file) && ( time() - filemtime($cache_file) < $cache_ttl ) ){            
            $response = @file_get_contents($cache_file);
            glog_dosyslog(__FUNCTION__ . ": NOTICE: Ответ на запрос '" . $request_id . "' взят из кэша '".basename($cache_file). "'.");
        };
    };
    
    
    if ( empty($response) ){    
        $context = stream_context_create($opts);
        
        while ( ! ( $response = @file_get_contents($url , false, $context) ) && ($tries--) ){
            if ( ! $response ) sleep( $sleep_coef * ($max_tries-$tries));
        };            
        glog_dosyslog(__FUNCTION__.": NOTICE: Отправлен " . $method . "-запрос " . $request_id . " на '" . $url . "' ... " . ($response === false ? "ERROR" : "OK"));
        if ( ! empty($postdata) ) glog_dosyslog(__FUNCTION__.": NOTICE: " . $request_id . " POST-данные: '".$postdata."'.");
        
    };
    
    

    if ($response){
        $result = ltrim($response, "\xEF\xBB\xBF"); // избавляемся от BOM, если кодировка ответа UTF-8
        if ($result){
            if ($tries<$max_tries){
                if ( strlen($result) <= $max_response_length_for_log ){
                    glog_dosyslog(__FUNCTION__.": NOTICE: Получен ответ на " . $request_id . ": '" . $result . "'. Сделано попыток запроса: ".($max_tries-$tries));    
                }else{
                    glog_dosyslog(__FUNCTION__.": NOTICE: Получен ответ на " . $request_id . ". Сделано попыток запроса: ".($max_tries-$tries));
                }
            }
            
            if ( $use_cache ){
                if ( ! file_put_contents($cache_file, $result) ){
                    glog_dosyslog(__FUNCTION__ . ": ERROR: Ошибка записи в кэш-файл '" . $cache_file . "'.");
                };
            };
            
        }else{
            dosyslog(__FUNCTION__.": WARNING: Пустой ответ на " . $request_id . ": '" . $response . "'.");
        }
    }else{
        dosyslog(__FUNCTION__.": ERROR: Не удалось получить ответ на " . $request_id . " после " . $max_tries .  " попыток.");
        $result = false;
    }

    return $result;
}

function glog_render($template_file, array $data){
    $HTML = "";
    
    if (file_exists($template_file)){
        $template = file_get_contents($template_file);
        if (empty($template)){
            glog_dosyslog(__FUNCTION__.": ERROR: Файл шаблона пустой - '".$template_file."'.");
            $template = defined("TEMPLATE_DEFAULT") ? TEMPLATE_DEFAULT : "";
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
        //glog_dosyslog(__FUNCTION__.": NOTICE: Успешно применен шаблон '".$template_file."'.");
    
    }else{
		$HTML = "<p><b>Ошибка!</b> Файл шаблона не найден".(DIAGNOSTICS_MODE || ($_SERVER["HTTP_HOST"] == "localhost") ? " - '".$template_file."'" : "")."</p>";
            glog_dosyslog(__FUNCTION__.": ERROR: Файл шаблона не найден - '".$template_file."'.");
    };
    
    return $HTML;	
};

function glog_str_limit($str, $limit, $noHTML = false){  
    
    if (mb_strlen($str, "UTF-8") > $limit){
        if ($noHTML){
            return mb_substr($str,0, $limit - 3, "UTF-8") . "&hellip;";
        }else{
            return "<span title='".htmlspecialchars($str)."'>" . mb_substr($str,0, $limit - 3, "UTF-8") . "&hellip;</span>";
        };
    }else{
        return $str;
    }
};

// ----------------
if (!function_exists("get_callee")){
    function get_callee(){
        $dbt    = debug_backtrace();
        $callee = (!empty($dbt[2]) ? " < ".$dbt[2]["function"] : "") . (!empty($dbt[3]) ? " < ".$dbt[3]["function"] : ""); // вызывающая функция; для целей логирования.
        return $callee;
    };
};
if (!function_exists("dump")){
    function dump($var, $title="") {						// Печатает дамп переменной, окруженной тегами PRE
        if ( (defined("DEV_MODE") && DEV_MODE) || ! defined("DEV_MODE") ){
            if ($title) echo "$title : \n";
            echo "<pre>";
            var_dump($var);
            echo "</pre>"; 
        };
    };
};
// ----------------
