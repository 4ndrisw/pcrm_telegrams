<?php

defined('BASEPATH') or exit('No direct script access allowed');

require 'vendor/autoload.php';
use app\services\telegrams\TelegramsPipeline;

use TelegramBot\Api\BotApi;

hooks()->add_action('app_admin_head', 'telegrams_head_component');
//hooks()->add_action('app_admin_footer', 'telegrams_footer_js__component');
hooks()->add_action('admin_init', 'telegrams_settings_tab');

/**
 * Get Jobreport short_url
 * @since  Version 2.7.3
 * @param  object $telegram
 * @return string Url
 */
function get_telegram_shortlink($telegram)
{
    $long_url = site_url("telegram/{$telegram->id}/{$telegram->hash}");
    if (!get_option('bitly_access_token')) {
        return $long_url;
    }

    // Check if telegram has short link, if yes return short link
    if (!empty($telegram->short_link)) {
        return $telegram->short_link;
    }

    // Create short link and return the newly created short link
    $short_link = app_generate_short_link([
        'long_url'  => $long_url,
        'title'     => format_telegram_number($telegram->id)
    ]);

    if ($short_link) {
        $CI = &get_instance();
        $CI->db->where('id', $telegram->id);
        $CI->db->update(db_prefix() . 'telegrams', [
            'short_link' => $short_link
        ]);
        return $short_link;
    }
    return $long_url;
}

/**
 * Check telegram restrictions - hash, clientid
 * @param  mixed $id   telegram id
 * @param  string $hash telegram hash
 */
function check_telegram_restrictions($id, $hash)
{
    $CI = &get_instance();
    $CI->load->model('telegrams_model');
    if (!$hash || !$id) {
        show_404();
    }
    if (!is_client_logged_in() && !is_staff_logged_in()) {
        if (get_option('view_telegram_only_logged_in') == 1) {
            redirect_after_login_to_current_url();
            redirect(site_url('authentication/login'));
        }
    }
    $telegram = $CI->telegrams_model->get($id);
    if (!$telegram || ($telegram->hash != $hash)) {
        show_404();
    }
    // Do one more check
    if (!is_staff_logged_in()) {
        if (get_option('view_telegram_only_logged_in') == 1) {
            if ($telegram->clientid != get_client_user_id()) {
                show_404();
            }
        }
    }
}

/**
 * Check if telegram email template for expiry reminders is enabled
 * @return boolean
 */
function is_telegrams_email_expiry_reminder_enabled()
{
    return total_rows(db_prefix() . 'emailtemplates', ['slug' => 'telegram-expiry-reminder', 'active' => 1]) > 0;
}

/**
 * Check if there are sources for sending telegram expiry reminders
 * Will be either email or SMS
 * @return boolean
 */
function is_telegrams_expiry_reminders_enabled()
{
    return is_telegrams_email_expiry_reminder_enabled() || is_sms_trigger_active(SMS_TRIGGER_TELEGRAM_EXP_REMINDER);
}

/**
 * Return RGBa telegram status color for PDF documents
 * @param  mixed $status_id current telegram status
 * @return string
 */
function telegram_status_color_pdf($status_id)
{
    if ($status_id == 1) {
        $statusColor = '119, 119, 119';
    } elseif ($status_id == 2) {
        // Sent
        $statusColor = '3, 169, 244';
    } elseif ($status_id == 3) {
        //Declines
        $statusColor = '252, 45, 66';
    } elseif ($status_id == 4) {
        //Accepted
        $statusColor = '0, 191, 54';
    } else {
        // Expired
        $statusColor = '255, 111, 0';
    }

    return hooks()->apply_filters('telegram_status_pdf_color', $statusColor, $status_id);
}

/**
 * Format telegram status
 * @param  integer  $status
 * @param  string  $classes additional classes
 * @param  boolean $label   To include in html label or not
 * @return mixed
 */
function format_telegram_status($status, $classes = '', $label = true)
{
    $id          = $status;
    $label_class = telegram_status_color_class($status);
    $status      = telegram_status_by_id($status);
    if ($label == true) {
        return '<span class="label label-' . $label_class . ' ' . $classes . ' s-status telegram-status-' . $id . ' telegram-status-' . $label_class . '">' . $status . '</span>';
    }

    return $status;
}

/**
 * Return telegram status translated by passed status id
 * @param  mixed $id telegram status id
 * @return string
 */
function telegram_status_by_id($id)
{
    $status = '';
    if ($id == 1) {
        $status = _l('telegram_status_draft');
    } elseif ($id == 2) {
        $status = _l('telegram_status_sent');
    } elseif ($id == 3) {
        $status = _l('telegram_status_declined');
    } elseif ($id == 4) {
        $status = _l('telegram_status_accepted');
    } elseif ($id == 5) {
        // status 5
        $status = _l('telegram_status_expired');
    } else {
        if (!is_numeric($id)) {
            if ($id == 'not_sent') {
                $status = _l('not_sent_indicator');
            }
        }
    }

    return hooks()->apply_filters('telegram_status_label', $status, $id);
}

/**
 * Return telegram status color class based on twitter bootstrap
 * @param  mixed  $id
 * @param  boolean $replace_default_by_muted
 * @return string
 */
function telegram_status_color_class($id, $replace_default_by_muted = false)
{
    $class = '';
    if ($id == 1) {
        $class = 'default';
        if ($replace_default_by_muted == true) {
            $class = 'muted';
        }
    } elseif ($id == 2) {
        $class = 'info';
    } elseif ($id == 3) {
        $class = 'danger';
    } elseif ($id == 4) {
        $class = 'success';
    } elseif ($id == 5) {
        // status 5
        $class = 'warning';
    } else {
        if (!is_numeric($id)) {
            if ($id == 'not_sent') {
                $class = 'default';
                if ($replace_default_by_muted == true) {
                    $class = 'muted';
                }
            }
        }
    }

    return hooks()->apply_filters('telegram_status_color_class', $class, $id);
}

/**
 * Check if the telegram id is last invoice
 * @param  mixed  $id telegramid
 * @return boolean
 */
function is_last_telegram($id)
{
    $CI = &get_instance();
    $CI->db->select('id')->from(db_prefix() . 'telegrams')->order_by('id', 'desc')->limit(1);
    $query            = $CI->db->get();
    $last_telegram_id = $query->row()->id;
    if ($last_telegram_id == $id) {
        return true;
    }

    return false;
}

/**
 * Format telegram number based on description
 * @param  mixed $id
 * @return string
 */
function format_telegram_number($id)
{
    $CI = &get_instance();
    $CI->db->select('date,number,prefix,number_format')->from(db_prefix() . 'telegrams')->where('id', $id);
    $telegram = $CI->db->get()->row();

    if (!$telegram) {
        return '';
    }

    $number = telegram_number_format($telegram->number, $telegram->number_format, $telegram->prefix, $telegram->date);

    return hooks()->apply_filters('format_telegram_number', $number, [
        'id'       => $id,
        'telegram' => $telegram,
    ]);
}


function telegram_number_format($number, $format, $applied_prefix, $date)
{
    $originalNumber = $number;
    $prefixPadding  = get_option('number_padding_prefixes');

    if ($format == 1) {
        // Number based
        $number = $applied_prefix . str_pad($number, $prefixPadding, '0', STR_PAD_LEFT);
    } elseif ($format == 2) {
        // Year based
        $number = $applied_prefix . date('Y', strtotime($date)) . '.' . str_pad($number, $prefixPadding, '0', STR_PAD_LEFT);
    } elseif ($format == 3) {
        // Number-yy based
        $number = $applied_prefix . str_pad($number, $prefixPadding, '0', STR_PAD_LEFT) . '-' . date('y', strtotime($date));
    } elseif ($format == 4) {
        // Number-mm-yyyy based
        $number = $applied_prefix . str_pad($number, $prefixPadding, '0', STR_PAD_LEFT) . '.' . date('m', strtotime($date)) . '.' . date('Y', strtotime($date));
    }

    return hooks()->apply_filters('telegram_number_format', $number, [
        'format'         => $format,
        'date'           => $date,
        'number'         => $originalNumber,
        'prefix_padding' => $prefixPadding,
    ]);
}

/**
 * Calculate telegrams percent by status
 * @param  mixed $status          telegram status
 * @return array
 */
function get_telegrams_percent_by_status($status, $project_id = null)
{
    $has_permission_view = has_permission('telegrams', '', 'view');
    $where               = '';

    if (isset($project_id)) {
        $where .= 'project_id=' . get_instance()->db->escape_str($project_id) . ' AND ';
    }
    if (!$has_permission_view) {
        $where .= get_telegrams_where_sql_for_staff(get_staff_user_id());
    }

    $where = trim($where);

    if (endsWith($where, ' AND')) {
        $where = substr_replace($where, '', -3);
    }

    $total_telegrams = total_rows(db_prefix() . 'telegrams', $where);

    $data            = [];
    $total_by_status = 0;

    if (!is_numeric($status)) {
        if ($status == 'not_sent') {
            $total_by_status = total_rows(db_prefix() . 'telegrams', 'sent=0 AND status NOT IN(2,3,4)' . ($where != '' ? ' AND (' . $where . ')' : ''));
        }
    } else {
        $whereByStatus = 'status=' . $status;
        if ($where != '') {
            $whereByStatus .= ' AND (' . $where . ')';
        }
        $total_by_status = total_rows(db_prefix() . 'telegrams', $whereByStatus);
    }

    $percent                 = ($total_telegrams > 0 ? number_format(($total_by_status * 100) / $total_telegrams, 2) : 0);
    $data['total_by_status'] = $total_by_status;
    $data['percent']         = $percent;
    $data['total']           = $total_telegrams;

    return $data;
}

function get_telegrams_where_sql_for_staff($staff_id)
{
    $CI = &get_instance();
    $has_permission_view_own             = has_permission('telegrams', '', 'view_own');
    $allow_staff_view_telegrams_assigned = get_option('allow_staff_view_telegrams_assigned');
    $whereUser                           = '';
    if ($has_permission_view_own) {
        $whereUser = '((' . db_prefix() . 'telegrams.addedfrom=' . $CI->db->escape_str($staff_id) . ' AND ' . db_prefix() . 'telegrams.addedfrom IN (SELECT staff_id FROM ' . db_prefix() . 'staff_permissions WHERE feature = "telegrams" AND capability="view_own"))';
        if ($allow_staff_view_telegrams_assigned == 1) {
            $whereUser .= ' OR assigned=' . $CI->db->escape_str($staff_id);
        }
        $whereUser .= ')';
    } else {
        $whereUser .= 'assigned=' . $CI->db->escape_str($staff_id);
    }

    return $whereUser;
}
/**
 * Check if staff member have assigned telegrams / added as sale agent
 * @param  mixed $staff_id staff id to check
 * @return boolean
 */
function staff_has_assigned_telegrams($staff_id = '')
{
    $CI       = &get_instance();
    $staff_id = is_numeric($staff_id) ? $staff_id : get_staff_user_id();
    $cache    = $CI->app_object_cache->get('staff-total-assigned-telegrams-' . $staff_id);

    if (is_numeric($cache)) {
        $result = $cache;
    } else {
        $result = total_rows(db_prefix() . 'telegrams', ['assigned' => $staff_id]);
        $CI->app_object_cache->add('staff-total-assigned-telegrams-' . $staff_id, $result);
    }

    return $result > 0 ? true : false;
}
/**
 * Check if staff member can view telegram
 * @param  mixed $id telegram id
 * @param  mixed $staff_id
 * @return boolean
 */
function user_can_view_telegram($id, $staff_id = false)
{
    $CI = &get_instance();

    $staff_id = $staff_id ? $staff_id : get_staff_user_id();

    if (has_permission('telegrams', $staff_id, 'view')) {
        return true;
    }

    if(is_client_logged_in()){

        $CI = &get_instance();
        $CI->load->model('telegrams_model');
       
        $telegram = $CI->telegrams_model->get($id);
        if (!$telegram) {
            show_404();
        }
        // Do one more check
        if (get_option('view_telegram_only_logged_in') == 1) {
            if ($telegram->clientid != get_client_user_id()) {
                show_404();
            }
        }
    
        return true;
    }

    $CI->db->select('id, addedfrom, assigned');
    $CI->db->from(db_prefix() . 'telegrams');
    $CI->db->where('id', $id);
    $telegram = $CI->db->get()->row();

    if ((has_permission('telegrams', $staff_id, 'view_own') && $telegram->addedfrom == $staff_id)
        || ($telegram->assigned == $staff_id && get_option('allow_staff_view_telegrams_assigned') == '1')
    ) {
        return true;
    }

    return false;
}


/**
 * Prepare general telegram pdf
 * @since  Version 1.0.2
 * @param  object $telegram telegram as object with all necessary fields
 * @param  string $tag tag for bulk pdf exporter
 * @return mixed object
 */
function telegram_pdf($telegram, $tag = '')
{
    return app_pdf('telegram',  module_libs_path(TELEGRAMS_MODULE_NAME) . 'pdf/Jobreport_pdf', $telegram, $tag);
}



/**
 * Get items table for preview
 * @param  object  $transaction   e.q. invoice, estimate from database result row
 * @param  string  $type          type, e.q. invoice, estimate, proposal
 * @param  string  $for           where the items will be shown, html or pdf
 * @param  boolean $admin_preview is the preview for admin area
 * @return object
 */
function get_telegram_items_table_data($transaction, $type, $for = 'html', $admin_preview = false)
{
    include_once(module_libs_path(TELEGRAMS_MODULE_NAME) . 'Jobreport_items_table.php');

    $class = new Jobreport_items_table($transaction, $type, $for, $admin_preview);

    $class = hooks()->apply_filters('items_table_class', $class, $transaction, $type, $for, $admin_preview);

    if (!$class instanceof App_items_table_template) {
        show_error(get_class($class) . ' must be instance of "Jobreport_items_template"');
    }

    return $class;
}



/**
 * Add new item do database, used for proposals,estimates,credit notes,invoices
 * This is repetitive action, that's why this function exists
 * @param array $item     item from $_POST
 * @param mixed $rel_id   relation id eq. invoice id
 * @param string $rel_type relation type eq invoice
 */
function add_new_telegram_item_post($item, $rel_id, $rel_type)
{

    $CI = &get_instance();

    $CI->db->insert(db_prefix() . 'itemable', [
                    'description'      => $item['description'],
                    'long_description' => nl2br($item['long_description']),
                    'qty'              => $item['qty'],
                    'rel_id'           => $rel_id,
                    'rel_type'         => $rel_type,
                    'item_order'       => $item['order'],
                    'unit'             => isset($item['unit']) ? $item['unit'] : 'unit',
                ]);

    $id = $CI->db->insert_id();

    return $id;
}

/**
 * Update telegram item from $_POST 
 * @param  mixed $item_id item id to update
 * @param  array $data    item $_POST data
 * @param  string $field   field is require to be passed for long_description,rate,item_order to do some additional checkings
 * @return boolean
 */
function update_telegram_item_post($item_id, $data, $field = '')
{
    $update = [];
    if ($field !== '') {
        if ($field == 'long_description') {
            $update[$field] = nl2br($data[$field]);
        } elseif ($field == 'rate') {
            $update[$field] = number_format($data[$field], get_decimal_places(), '.', '');
        } elseif ($field == 'item_order') {
            $update[$field] = $data['order'];
        } else {
            $update[$field] = $data[$field];
        }
    } else {
        $update = [
            'item_order'       => $data['order'],
            'description'      => $data['description'],
            'long_description' => nl2br($data['long_description']),
            'qty'              => $data['qty'],
            'unit'             => $data['unit'],
        ];
    }

    $CI = &get_instance();
    $CI->db->where('id', $item_id);
    $CI->db->update(db_prefix() . 'itemable', $update);

    return $CI->db->affected_rows() > 0 ? true : false;
}


/**
 * Function that return full path for upload based on passed type
 * @param  string $type
 * @return string
 */
function get_telegram_upload_path($type=NULL)
{
   $type = 'telegram';
   $path = TELEGRAM_ATTACHMENTS_FOLDER;
   
    return hooks()->apply_filters('get_upload_path_by_type', $path, $type);
}


/**
 * Injects theme CSS
 * @return null
 */
function telegrams_head_component()
{
    $CI = &get_instance();
    if (($CI->uri->segment(1) == 'admin' && $CI->uri->segment(2) == 'telegrams') ||
        $CI->uri->segment(1) == 'telegrams'){
        echo '<link href="' . base_url('modules/telegrams/assets/css/telegrams.css') . '"  rel="stylesheet" type="text/css" >';
    }
}


/**
 * Injects theme CSS
 * @return null
 */
function telegram_head_component()
{
}

$CI = &get_instance();
// Check if telegram is excecuted
if ($CI->uri->segment(1)=='telegrams') {
    hooks()->add_action('app_customers_head', 'telegram_app_client_includes');
}

/**
 * Theme clients footer includes
 * @return stylesheet
 */
function telegram_app_client_includes()
{
    echo '<link href="' . base_url('modules/' .TELEGRAMS_MODULE_NAME. '/assets/css/telegrams.css') . '"  rel="stylesheet" type="text/css" >';
    echo '<script src="' . module_dir_url('' .TELEGRAMS_MODULE_NAME. '', 'assets/js/telegrams.js') . '"></script>';
}


function after_telegram_updated($id){


}

function telegram_create_assigned_qrcode_hook($id){
     
     log_activity( 'Hello, world!' );

}

function telegram_status_changed_hook($data){

    log_activity('telegram_status_changed');

}

function telegrams_task_status_changed($param) {
    if($param['status'] != 5){
        return;
    }

    $data = [];
    $CI = &get_instance();
    $data['key_id'] = $param['task_id'].'-'.$param['status'];
    $data['task_id'] = $param['task_id'];
    $data['status'] = $param['status'];

    $CI->load->model('tasks_model');
    $task = $CI->tasks_model->get($data['task_id']);
    if($task->rel_type !== 'project'){
        return;
    }
    
    $data['telegram_token']        = get_option('telegram_token');
    $data['telegram_group_chat_id']        = get_option('telegram_group_chat_id'); //1514861293

    $message = "";
    $message .= date('d/m/Y H:i:s') . "\r\n";
    $message .= $task->assignees[0]['firstname'] .' '. $task->assignees[0]['lastname'] . "\r\n";
    $message .= 'update status ' . "\r\n";
    $message .= $task->project_data->client_data->company . "\r\n";
    $message .= $task->project_data->name . "\r\n";
    $message .= $task->name . "\r\n";
    $message .= 'to ' . _l('task_status_'.$param['status']);
    
    $data['message'] = $message;

    try {

        $bot = new \TelegramBot\Api\BotApi($data['telegram_token']);
        $bot->sendMessage($data['telegram_group_chat_id'], $data['message']);

        /*
        $file = FCPATH . get_telegram_upload_path('telegram').$telegram->id.'/assigned-'.$telegram_number.'.png';
        $file = FCPATH . get_telegram_upload_path('telegram').$id.'/BAPP-074-06-2022.pdf';

        if(file_exists($file)){
            $document = new \CURLFile($file);
        }else{
            log_activity($file);
        }
        $bot->sendDocument($data['telegram_group_chat_id'], $document);
        */

    } catch (\TelegramBot\Api\Exception $e) {
        $e->getMessage();
        log_activity('Telegrams : '. json_encode($e->getMessage()));
    }


}

