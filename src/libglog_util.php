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
    
    if ( $add_units ) $age = glog_get_age_str($age);    
    
    return $age;
};
function glog_get_age_str($age){    // возвращает строку вида "n лет"
    
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
    
    return $age;
}
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
    }

    return $result;
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
