<?php

if (!defined('ABSPATH')) {
    die('Restricted Access');
}

class JSSTformhandler
{
    public function __construct()
    {
        add_action('init', [$this, 'checkFormRequest']);
        add_action('init', [$this, 'checkDeleteRequest']);
    }

    /*
     * Handle Form request
     */

    public function checkFormRequest()
    {
        $formrequest = JSSTrequest::getVar('form_request', 'post');
        if ($formrequest == 'jssupportticket') {
            //handle the request
            $page_id = JSSTRequest::getVar('page_id', 'GET');
            jssupportticket::setPageID($page_id);
            $modulename = (is_admin()) ? 'page' : 'jstmod';
            $module     = JSSTrequest::getVar($modulename);
            JSSTincluder::include_file($module);
            $class = 'JSST' . $module . 'Controller';
            $task  = JSSTrequest::getVar('task');
            $obj   = new $class();
            $obj->$task();
        }
    }

    /*
     * Handle Form request
     */

    public function checkDeleteRequest()
    {
        $jssupportticket_action = JSSTrequest::getVar('action', 'get');
        if ($jssupportticket_action == 'jstask') {
            //handle the request
            $page_id = JSSTRequest::getVar('page_id', 'GET');
            jssupportticket::setPageID($page_id);
            $modulename = (is_admin()) ? 'page' : 'jstmod';
            $module     = JSSTrequest::getVar($modulename, '', '');
            if ($module != '') {
                JSSTincluder::include_file($module);
                $class  = 'JSST' . $module . 'Controller';
                $action = JSSTrequest::getVar('task');
                $obj    = new $class();
                $obj->$action();
            } else {
                error_log(print_r($_REQUEST, true)); // temporary code to get the case when problem occurs(there are errors in log but no way to find the case that causes them)
            }
        }
    }
}

$formhandler = new JSSTformhandler();
