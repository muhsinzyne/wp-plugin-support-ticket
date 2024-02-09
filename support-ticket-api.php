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

// api end points register routes
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

    register_rest_route('support-form', '/getattachment', [
        'methods'  => 'GET',
        'callback' => 'get_attachment',
    ]);
}

function get_ticket_reply_attachments($ticketId, $replayId)
{
    global $wpdb;
    $ticketId                 = $wpdb->escape($ticketId);
    $replayId                 = $wpdb->escape($replayId);
    $table_name               = $wpdb->prefix . 'js_ticket_attachments';
    $query                    = $wpdb->prepare(
        "SELECT * FROM $table_name WHERE ticketid = %d AND replyattachmentid = %d",
        $ticketId,
        $replayId,
    );
    $attachments                  = $wpdb->get_results($query);

    echo '<pre>';
    print_r($attachments);
    echo '</pre>';
    die();
    $upload_dir = wp_upload_dir();
    $base_dir   = $upload_dir['basedir'];
    $base_url   = $upload_dir['baseurl'];
    $attachList = [];
    foreach ($attachments as $key => $attach) {
        $attach = (array) $attach;
        // Extract the year and month from the creation date
        $created_date = new DateTime($attach['created']);
        $year         = $created_date->format('Y');
        $month        = $created_date->format('m');
        // Construct the file path
        $file_path = $base_dir . '/' . $year . '/' . $month . '/' . $attach['filename'];
        if (file_exists($file_path)) {
            // Construct the URL to access the file
            $file_url     = $base_url . '/' . $year . '/' . $month . '/' . $attach['filename'];
            $attachList[] = $file_url;
        }
    }

    return $attachList;
}

function get_ticket_attachments($ticketId)
{
    global $wpdb;
    $id                       = $wpdb->escape($ticketId);
    $table_name               = $wpdb->prefix . 'js_ticket_attachments';
    $query                    = $wpdb->prepare(
        "SELECT * FROM $table_name WHERE ticketid = %d AND replyattachmentid = %d",
        $id,
        0
    );
    $attachments                  = $wpdb->get_results($query);
    $upload_dir                   = wp_upload_dir();
    $base_dir                     = $upload_dir['basedir'];
    $base_url                     = $upload_dir['baseurl'];
    $attachList                   = [];
    foreach ($attachments as $key => $attach) {
        $attach = (array) $attach;
        // Extract the year and month from the creation date
        $created_date = new DateTime($attach['created']);
        $year         = $created_date->format('Y');
        $month        = $created_date->format('m');
        // Construct the file path
        $file_path = $base_dir . '/' . $year . '/' . $month . '/' . $attach['filename'];
        if (file_exists($file_path)) {
            // Construct the URL to access the file
            $file_url     = $base_url . '/' . $year . '/' . $month . '/' . $attach['filename'];
            $attachList[] = $file_url;
        }
    }

    return $attachList;
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

    $ticket['attachments'] = get_ticket_attachments($ticket['id']);

    $table_replay               = $wpdb->prefix . 'js_ticket_replies';

    $ticketReplysQuery  =  $query = $wpdb->prepare(
        "SELECT * FROM $table_replay WHERE ticketid = %d ORDER BY created ASC",
        $id
    );

    $ticketReply                  = $wpdb->get_results($ticketReplysQuery);
    $replyFormatted               = [];
    foreach ($ticketReply as $key => $reply) {
        $reply                               = (array) $reply;
        $replyFormatted[$key]                = $reply;
        $replyFormatted[$key]['attachments'] =  get_ticket_reply_attachments($reply['ticketid'], $reply['id']);
        // code...
    }

    echo '<pre>';
    print_r($replyFormatted);
    echo '</pre>';
    die();
}

function get_attachment($data)
{
    $validate = auth_validate_me();
    if (!$validate) {
        $response = new WP_REST_Response(['message' => 'UNAUTHORIZED'], 401);

        return rest_ensure_response($response);
    }
    global $wpdb;

    $id = isset($data['id']) ? $data['id'] : null;

    $id = $wpdb->escape($id);

    if ($id == null) {
        $response = new WP_REST_Response(['message' => 'image not availabe'], 404);

        return rest_ensure_response($response);
    } else {
        $table_name               = $wpdb->prefix . 'js_ticket_attachments';

        $query = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $id
        );
        $result = $wpdb->get_row($query, ARRAY_A);
    }
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
register_activation_hook(__FILE__, 'create_key_table');