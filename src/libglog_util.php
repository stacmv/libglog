<?php

function glog_dosyslog($message) {								// ����� ��������� � ��������� ��� ��� ���������� ����� GLOG_DO_SYSLOG.

    if (GLOG_DO_SYSLOG) {
        if (!is_dir(dirname(GLOG_SYSLOG))) mkdir(dirname(GLOG_SYSLOG), 0777, true);
        // ��������� ����
        $syslog = GLOG_SYSLOG;
        
        $data = array(
            @$_SERVER["REMOTE_ADDR"],
            date("Y-m-d\TH:i:s"),
            $message,
        );
        
        $message = implode("\t", $data) . "\n";
    
        if (file_put_contents($syslog, $message, FILE_APPEND) === false) {
            $Subject = "������: ".$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME'];
            $extraheader = "Content-type: text/plain; charset=UTF-8";
            $message= "���������� �������� ������ � ��������� ��� '".$syslog."'!\n�� ����������  ������:\n".$message."\n";
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
function glog_rusdate($date, $withTime = false) {				/* ��������� ���� � ������� "����-��-��" � ���������� � ������� "��.��.����" */
    
    if (preg_match("/\d\d\.\d\d\.\d{4}/", $date)) return $date; // ���� ��� � ������� ��.��.����
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
function glog_get_age($anketa, $add_units = false) { 				// ���������� ������� ������� � ������� ������ "n" ($add_units = false) ��� "n ���" ($add_units = true). ��������� ������.
    
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
                $suf = "���";
                break;
            case 2:
            case 3:
            case 4:
                $suf = "����";
                break;
            case 5:
            case 6:
            case 7:
            case 8:
            case 9:
            default:
                $suf = "���";
        };
        $age = $age." ".$suf;
    };
    return $age;
};
function glog_codify($str){                                         // ���������� ������ � ����, ��������� ��� ������������� � ������ ������, url, css-�������, ... .
	$result = glog_translit($str);
    
	$result = str_replace(array("+","&"," ",",",":",";",".",",","/","\\","(",")","'","\""),array("_plus_","_and_","-","-","-","-"),$result); 
    
	$result = strtolower($result);
    
	$result = urlencode($result);
	
	return $result;
};
function glog_translit($s) {                                        //���������� ������������������� ������.
    $result = $s;

    $result = str_replace(array("�","�","�","�","�","�","�","�","�","�","�","�","�","�","�","�","�","�","�","�","�","�","�","�"), array("a","b","v","g","d","e","e","z","i","j","k","l","m","n","o","p","r","s","t","u","f","h","y","e"), $result);
    $result = str_replace(array("�","�","�","�","�","�","�","�","�","�","�","�","�","�","�","�","�","�","�","�","�","�","�","�"), array("A","B","V","G","D","E","E","Z","I","J","K","L","M","N","O","P","R","S","T","U","F","H","Y","E"), $result);
	
	$result = str_replace(array("�","�","�","�","�","�","�","�","�"), array("zh","ts","ch","sh","sch","yu","ya"),$result);
	$result = str_replace(array("�","�","�","�","�","�","�","�","�"), array("ZH","TS","CH","SH","SCH","YU","YA"),$result);

	return $result;
};
function glog_show_phone($phone_cleared){ 						    // ����������� ����� �������� (������ �����) �  ���� (123) 456-78-90
	return "(" . substr($phone_cleared, 0, 3) . ") " . substr($phone_cleared, 3, 3) . "-" . substr($phone_cleared, 6, 2) . "-" . substr($phone_cleared, 8, 3);
}
function glog_clear_phone($phone){                              	// ���������� ������� � ������� 9031234567 - ������ �����
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
    function dump($var, $title="") {						// �������� ���� ����������, ���������� ������ PRE
        if ($title) echo "$title : \n";
        echo "<pre>";
        var_dump($var);
        echo "</pre>"; 
    };
};
// ----------------
