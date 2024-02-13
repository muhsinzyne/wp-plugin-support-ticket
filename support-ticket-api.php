<?php
/**
* Plugin Name: Support Ticket RestAPI Extension
* Plugin URI: https://www.aths.ac.ae/
* Description: Support Ticket Rest API Extension.
* Version: 1.0
* Author: thedevsavant
* Author URI: https://www.thedevsavant.com/
**/

// for migration for the access token
function custom_rest_api_endpoint()
{
    // Register a route to retrieve department data
    register_rest_route('support-form', '/getdepartment', [
        'methods'  => 'GET',
        'callback' => 'get_department_data',
    ]);

    register_rest_route('support-form', '/gethelptopic', [
        'methods'  => 'GET',
        'callback' => 'get_help_topic_data',
    ]);

    register_rest_route('support-form', '/getpriorities', [
        'methods'  => 'GET',
        'callback' => 'get_priority_data',
    ]);

    register_rest_route('support-form', '/saveticket', [
        'methods'  => 'POST',
        'callback' => 'save_ticket',
    ]);

    register_rest_route('support-form', '/mytickets', [
        'methods'  => 'GET',
        'callback' => 'get_my_tickets',
    ]);

    register_rest_route('support-form', '/getticket', [
        'methods'  => 'GET',
        'callback' => 'get_ticket_details',
    ]);

    register_rest_route('support-form', '/mydashboard', [
        'methods'  => 'GET',
        'callback' => 'get_my_dashboard',
    ]);

    register_rest_route('support-form', '/download', [
        'methods'  => 'GET',
        'callback' => 'download',
    ]);
}

function download($data)
{
    $id   = $data['id'] ?? null;
    $type = $data['type'] ?? null;

    $download   = true;

    if ($type != null && $id != null) {
        if ($type == 'all') {
            if (!class_exists('PclZip')) {
                do_action('jssupportticket_load_wp_pcl_zip');
            }
            $ticketattachment = JSSTincluder::getJSModel('ticket')->getAttachmentByTicketId($id);
            $path             = JSST_PLUGIN_PATH;
            $path .= 'zipdownloads';
            JSSTincluder::getJSModel('jssupportticket')->makeDir($path);
            $randomfolder = getRandomFolderName($path);
            $path .= '/' . $randomfolder;
            JSSTincluder::getJSModel('jssupportticket')->makeDir($path);

            $archive           = new PclZip($path . '/alldownloads.zip');

            $datadirectory     = jssupportticket::$_config['data_directory'];
            $maindir           = wp_upload_dir();
            $jpath             = $maindir['basedir'];
            $jpath             = $jpath . '/' . $datadirectory;
            $scanned_directory = [];

            foreach ($ticketattachment as $ticketattachments) {
                $directory = $jpath . '/attachmentdata/ticket/' . $ticketattachments->attachmentdir . '/';
                // $scanned_directory = array_diff(scandir($directory), array('..', '.'));
                array_push($scanned_directory, $ticketattachments->filename);
            }
            $filelist = '';
            foreach ($scanned_directory as $file) {
                $filelist .= $directory . '/' . $file . ',';
            }

            $filelist = jssupportticketphplib::JSST_substr($filelist, 0, jssupportticketphplib::JSST_strlen($filelist) - 1);
            $v_list   = $archive->create($filelist, PCLZIP_OPT_REMOVE_PATH, $directory);

            if ($v_list == 0) {
                die("Error : '" . $archive->errorInfo() . "'");
            }
            $file = $path . '/alldownloads.zip';

            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=' . jssupportticketphplib::JSST_basename($file));
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
            //ob_clean();
            flush();
            readfile($file);
            @unlink($file);
            $path = JSST_PLUGIN_PATH;
            $path .= 'zipdownloads';
            $path .= '/' . $randomfolder;
            @unlink($path . '/index.html');
            if (file_exists($path)) {
                rmdir($path);
            }
        }
        if ($type == 'byid') {
            if (!is_numeric($id)) {
                return false;
            }
            $query = 'SELECT ticket.attachmentdir AS foldername,ticket.id AS ticketid,attach.filename  '
                    . ' FROM `' . jssupportticket::$_db->prefix . 'js_ticket_attachments` AS attach '
                    . ' JOIN `' . jssupportticket::$_db->prefix . 'js_ticket_tickets` AS ticket ON ticket.id = attach.ticketid '
                    . ' WHERE attach.id = ' . esc_sql($id);
            $object     = jssupportticket::$_db->get_row($query);
            $foldername = $object->foldername;
            $ticketid   = $object->ticketid;
            $filename   = $object->filename;

            if ($download == true) {
                $datadirectory = jssupportticket::$_config['data_directory'];
                $maindir       = wp_upload_dir();
                $path          = $maindir['basedir'];
                $path          = $path . '/' . $datadirectory;
                $path          = $path . '/attachmentdata';
                $path          = $path . '/ticket/' . $foldername;
                $file          = $path . '/' . $filename;

                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename=' . jssupportticketphplib::JSST_basename($file));
                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');
                header('Content-Length: ' . filesize($file));
                //ob_clean();
                flush();
                readfile($file);
                exit();
            }
        }
        if ($type == 'byreplyid') {
        }
    }
    if ($id != null) {
    }
}

function get_ticket_details($data)
{
    global $wpdb;
    $validate = auth_validate_me();
    if (!$validate) {
        $response = new WP_REST_Response(['message' => 'UNAUTHORIZED'], 401);

        return rest_ensure_response($response);
    }

    $id = isset($data['id']) ? $data['id'] : null;
    $id = $wpdb->escape($id);

    $table_name               = $wpdb->prefix . 'js_ticket_tickets';
    $query                    = $wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $id
    );
    $ticket = $wpdb->get_row($query, ARRAY_A);

    if (!$ticket) {
        $response = new WP_REST_Response(['message' => 'No Ticket Found'], 404);

        return rest_ensure_response($response);
    }

    $attachmenttable               = $wpdb->prefix . 'js_ticket_attachments';
    $query                         = $wpdb->prepare(
        "SELECT * FROM $attachmenttable WHERE ticketid = %d AND replyattachmentid = %d",
        $ticket['id'],
        0,
    );

    $attachementList                      = [];
    $attachmentsResponse                  = $wpdb->get_results($query);
    foreach ($attachmentsResponse as $key => $item) {
        // code...
        $ticket['attachments'][] = (array) $item;
    }

    $table_replay               = $wpdb->prefix . 'js_ticket_replies';

    $ticketReplysQuery  =  $query = $wpdb->prepare(
        "SELECT * FROM $table_replay WHERE ticketid = %d ORDER BY created ASC",
        $id
    );

    $ticketReply                  = $wpdb->get_results($ticketReplysQuery);
    $replyFormatted               = [];
    foreach ($ticketReply as $key => $reply) {
        $query                         = $wpdb->prepare(
            "SELECT * FROM $attachmenttable WHERE ticketid = %d AND replyattachmentid = %d",
            $ticket['id'],
            $reply->id,
        );

        $replyAttachmentResponse                  = $wpdb->get_results($query);

        $reply                               = (array) $reply;
        $replyFormatted[$key]                = $reply;
        $replyFormatted[$key]['attachments'] = $replyAttachmentResponse;
        // code...
    }

    $ticket['replayList'] = $replyFormatted;

    return rest_ensure_response($ticket);
}

function get_my_dashboard($data)
{
    $validate = auth_validate_me();
    if (!$validate) {
        $response = new WP_REST_Response(['message' => 'UNAUTHORIZED'], 401);

        return rest_ensure_response($response);
    }

    global $wpdb;

    $emailBase64 = isset($data['email']) ? $data['email'] : null;
    $myemail     = base64_decode($emailBase64);

    if ($myemail == null || $myemail == '') {
        $response = new WP_REST_Response(['message' => 'bad_request_invalid_user'], 400);

        return rest_ensure_response($response);
    }

    $table_name               = $wpdb->prefix . 'js_ticket_tickets';

    $count_active_tickets = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE status = %d AND email = %s",
            0, // Assuming 0 represents 'active' status
            $myemail
        )
    );

    $count_closed_tickets = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE status = %d AND email = %s",
            1, // Assuming 0 represents 'active' status
            $myemail
        )
    );

    $count_answered_tickets = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE isanswered = %d AND email = %s",
            1, // Assuming 0 represents 'active' status
            $myemail
        )
    );

    $count_all_tickets = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE  email = %s", // Assuming 0 represents 'active' status
            $myemail
        )
    );

    $response = [
        'active'   => $count_active_tickets,
        'closed'   => $count_closed_tickets,
        'answered' => $count_answered_tickets,
        'all'      => $count_all_tickets,
    ];

    return rest_ensure_response($response);
}

function save_ticket($arguments)
{
    $validate = auth_validate_me();
    if (!$validate) {
        $response = new WP_REST_Response(['message' => 'UNAUTHORIZED'], 401);

        return rest_ensure_response($response);
    }

    $data       = JSSTrequest::get('post');
    $result     = JSSTincluder::getJSModel('ticket')->storeTickets($data);

    if (is_admin()) {
    } else {
        if (JSSTincluder::getObjectClass('user')->uid() == 0) { // visitor
            if ($result == false) { // error on captcha or ticket validation
                // not created the ticket
            } else { // all things perfect
                if (in_array('actions', jssupportticket::$_active_addons)) {
                    $url = jssupportticket::makeUrl(['jstmod'=>'ticket', 'jstlay'=>'visitormessagepage']);
                } else {
                    $url = jssupportticket::makeUrl(['jstmod'=>'jssupportticket', 'jstlay'=>'controlpanel']);
                }
            }
        }
    }

    if ($result == false) {
        // having error

        $response = [
            'message' => 'could_not_create_your_request',
        ];

        $response = new WP_REST_Response(['message' => 'could_not_create_your_request'], 400);

        return rest_ensure_response($response);
    } else {
        // ticket created successfully
        $response = [
            'message' => 'support_ticket_saved',
        ];

        return rest_ensure_response($response);
    }
}

function get_my_tickets($data)
{
    $validate = auth_validate_me();
    if (!$validate) {
        $response = new WP_REST_Response(['message' => 'UNAUTHORIZED'], 401);

        return rest_ensure_response($response);
    }

    global $wpdb;

    $emailBase64 = isset($data['email']) ? $data['email'] : null;
    $myemail     = base64_decode($emailBase64);

    if ($myemail == null || $myemail == '') {
        $response = new WP_REST_Response(['message' => 'bad_request_invalid_user'], 400);

        return rest_ensure_response($response);
    }
    //$status  = isset($data['status']) ? $data['status'] : 'active';

    $type    = isset($data['type']) ? $data['type'] : 'open';

    $page                      = isset($data['page']) ? $data['page'] : 1;
    $limit                     = isset($data['limit']) ? $data['limit'] : 20;

    if ($limit > 20) {
        $limit = 20;
    }
    $offset                    = ($page - 1) * $limit;
    $table_name                = $wpdb->prefix . 'js_ticket_tickets';

    if ($type == 'open') {
        $tickets                  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE status = %d AND email = %s ORDER BY created DESC LIMIT %d, %d",
                0,
                $myemail,
                $offset,
                $limit
            )
        );
    } elseif ($type == 'closed') {
        $tickets                  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE status = %d AND email = %s ORDER BY created DESC LIMIT %d, %d",
                1,
                $myemail,
                $offset,
                $limit
            )
        );
    } elseif ($type == 'answered') {
        $tickets                  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE isanswered = %d AND email = %s ORDER BY created DESC LIMIT %d, %d",
                1,
                $myemail,
                $offset,
                $limit
            )
        );
    } else {
        $tickets                  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE email = %s ORDER BY created DESC LIMIT %d, %d",
                $myemail,
                $offset,
                $limit
            )
        );
    }

    return rest_ensure_response($tickets);
}

function get_priority_data($data)
{
    $validate = auth_validate_me();
    if (!$validate) {
        $response = new WP_REST_Response(['message' => 'UNAUTHORIZED'], 401);

        return rest_ensure_response($response);
    }
    global $wpdb;

    $table_name               = $wpdb->prefix . 'js_ticket_priorities';
    $priorities               = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, priority, ispublic FROM $table_name ORDER BY ordering ASC",
        )
    );

    return rest_ensure_response($priorities);
}

function get_help_topic_data($data)
{
    $validate = auth_validate_me();
    if (!$validate) {
        $response = new WP_REST_Response(['message' => 'UNAUTHORIZED'], 401);

        return rest_ensure_response($response);
    }

    global $wpdb;

    $departmentId = isset($data['departmentid']) ? $data['departmentid'] : 0;

    $table_name           = $wpdb->prefix . 'js_ticket_help_topics';
    $topics               = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, isactive, topic FROM $table_name WHERE departmentid = %d",
            $departmentId
        )
    );

    return rest_ensure_response($topics);
}

// api call back function
// Callback function to handle the request and retrieve department data
function get_department_data($data)
{
    $validate = auth_validate_me();
    if (!$validate) {
        $response = new WP_REST_Response(['message' => 'UNAUTHORIZED'], 401);

        return rest_ensure_response($response);
    }
    global $wpdb;

    $athsMobileDepartment = 'Communicator - Mobile';
    $table_name           = $wpdb->prefix . 'js_ticket_departments';
    $departments          = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, departmentname, departmentsignature, ispublic FROM $table_name WHERE departmentname LIKE %s",
            '%' . $wpdb->esc_like($athsMobileDepartment) . '%'
        ),
    );

    return rest_ensure_response($departments);
}

function auth_validate_me()
{
    global $wpdb;

    $table_name         = $wpdb->prefix . 'support_access_tokens';

    try {
        $app_id         = isset($_SERVER['HTTP_APPID']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_APPID'])) : '';

        $authorization  = isset($_SERVER['HTTP_AUTHORIZATION']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_AUTHORIZATION'])) : '';

        if ($app_id == null || $authorization == null) {
            throw new Exception('Missing headers');
        }
        // Other operations...
    } catch (Exception $e) {
        return false;
    }

    $authorization  = str_replace('Bearer ', '', $authorization);
    $authorization  = str_replace('bearer ', '', $authorization);
    $publicKey      = base64_decode($authorization);

    $query = $wpdb->prepare(
        "SELECT * FROM $table_name WHERE app_name = %s LIMIT 1",
        $app_id
    );
    $credentials = $wpdb->get_row($query, ARRAY_A);

    $privateKey = $credentials['private_key'];

    $valid = keyValidate($publicKey, $privateKey);

    return $valid;
}

function keyValidate($publicKey, $privateKey)
{
    // Example validation logic
    // For demonstration purposes, you can check if the public key corresponds to the private key
    // In real-world scenarios, you might have more complex validation logic
    $res     = openssl_pkey_get_private($privateKey);
    $details = openssl_pkey_get_details($res);
    $valid   = ($details['key'] == $publicKey);

    return $valid;
}

function my_settings_accesstoken_generate_menu()
{
    add_options_page('API Access Token', 'API Access Token', 'manage_options', 'suppor-ticket-ext-generate-token', 'generate_token_from_page');
}

function generate_token_from_page()
{
    require_once plugin_dir_path(__FILE__) . 'view/generate-key.php';
}

// Hook into rest_api_init to register the custom endpoint

// register actions
add_action('rest_api_init', 'custom_rest_api_endpoint');

add_action('admin_menu', 'my_settings_accesstoken_generate_menu');

// register hooks
register_activation_hook(__FILE__, 'activation_functions');
register_deactivation_hook(__FILE__, 'deactivation_functions');

function activation_functions()
{
    $contents = file_get_contents(__DIR__ . '/plugin-overriders/formhandler.php');
    $savedd   = file_put_contents(__DIR__ . '/../js-support-ticket/includes/formhandler.php', $contents);

    create_key_table();
}

function create_key_table()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'support_access_tokens';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        app_name VARCHAR(255) NOT NULL,
        public_key TEXT NOT NULL,
        private_key TEXT NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

function deactivation_functions()
{
    $contents = file_get_contents(__DIR__ . '/plugin-stocks/formhandler.php');
    $savedd   = file_put_contents(__DIR__ . '/../js-support-ticket/includes/formhandler.php', $contents);
}

function getRandomFolderName($path)
{
    $match = '';
    do {
        $rndfoldername = '';
        $length        = 5;
        $possible      = '2346789bcdfghjkmnpqrtvwxyzBCDFGHJKLMNPQRTVWXYZ';
        $maxlength     = jssupportticketphplib::JSST_strlen($possible);
        if ($length > $maxlength) {
            $length = $maxlength;
        }
        $i = 0;
        while ($i < $length) {
            $char = jssupportticketphplib::JSST_substr($possible, mt_rand(0, $maxlength - 1), 1);
            if (!strstr($rndfoldername, $char)) {
                if ($i == 0) {
                    if (ctype_alpha($char)) {
                        $rndfoldername .= $char;
                        $i++;
                    }
                } else {
                    $rndfoldername .= $char;
                    $i++;
                }
            }
        }
        $folderexist = $path . '/' . $rndfoldername;
        if (file_exists($folderexist)) {
            $match = 'Y';
        } else {
            $match = 'N';
        }
    } while ($match == 'Y');

    return $rndfoldername;
}

class SupportTicketAPI
{
    private $ticketid;
    private $articleid;
    private $downloadid;
    private $categoryid;
    private $staffid;
    private $uploadfor;

    public function __construct()
    {
        // Hook into the 'upload_dir' filter provided by the original plugin
        add_filter('upload_dir', [$this, 'jssupportticket_upload_dir']);
    }

    public function jssupportticket_upload_dir($dir)
    {
        $form_request = JSSTrequest::getVar('form_request');

        $atValue = isset($_GET['api']) ? (JSSTrequest::getVar('api')) : false;

        if ($form_request == 'jssupportticket' or $this->uploadfor == 'agent' || $atValue == true) {
            $datadirectory = jssupportticket::$_config['data_directory'];
            $path          = $datadirectory . '/attachmentdata';

            $foldername = '';

            if ($this->uploadfor == 'ticket') {
                $path       = $path . '/ticket';
                $query      = 'SELECT attachmentdir FROM `' . jssupportticket::$_db->prefix . 'js_ticket_tickets` WHERE id = ' . esc_sql($this->ticketid);
                $foldername = jssupportticket::$_db->get_var($query);
            } elseif ($this->uploadfor == 'article') {
                $path = $path . '/articles/article_' . $this->articleid;
            } elseif ($this->uploadfor == 'download') {
                $path = $path . '/downloads/download_' . $this->downloadid;
            } elseif ($this->uploadfor == 'category') {
                $path = $datadirectory . '/knowledgebasedata/categories/category_' . $this->categoryid;
            } elseif ($this->uploadfor == 'agent') {
                $path = $datadirectory . '/staffdata/staff_' . $this->staffid;
            }

            $userpath = $path . '/' . $foldername;

            $array = [
                'path'   => $dir['basedir'] . '/' . $userpath,
                'url'    => $dir['baseurl'] . '/' . $userpath,
                'subdir' => '/' . $userpath,
            ] + $dir;

            return $array;
        } elseif ($this->uploadfor == 'notificationlogo') {
            $datadirectory = jssupportticket::$_config['data_directory'];
            $path          = $datadirectory;

            return $path;
        } else {
            return $dir;
        }
    }
}

$api = new SupportTicketAPI();
