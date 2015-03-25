<?
define ("GLOG_DIR", LANDING_DIR ."data/"); 
define ("GLOG_FILE_PREFIX","gelog");
define ("GLOG_FILE_SUFFIX",".txt");
define ("GLOG_FILE_ENCODING", "UTF-8");
if (!defined("GLOG_WORK_ENCODING"))
    define ("GLOG_WORK_ENCODING", "CP1251");
define ("GLOG_TEMPLATES_DIR", LANDING_DIR . "templates/");

if (defined("DO_SYSLOG")) define("GLOG_DO_SYSLOG", DO_SYSLOG);
if (defined("SYSLOG")) define("GLOG_SYSLOG", SYSLOG);