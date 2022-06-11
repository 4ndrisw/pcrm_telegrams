<?php

defined('BASEPATH') or exit('No direct script access allowed');

require 'vendor/autoload.php';

use TelegramBot\Api\BotApi;

function telegrams_before_cron_run($manual){
    log_activity('telegrams_before_cron_run_' . date('d/m/y H:i:s'));
}

function telegrams_notification($params){
    log_activity('telegrams_after_cron_run_' . date('d/m/y H:i:s'));
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

function telegrams_after_jobreport_added($insert_id){
     
     $CI = &get_instance();
     $CI->load->model('jobreports_model');
     $jobreport = $CI->jobreports_model->get($insert_id);
     $CI->load->model('projects_model');
     $project = $CI->projects_model->get($jobreport->project_id);
     $project_name = isset($project->name) ? $project->name : 'UNDEFINED';
     $jobreport_company = isset($jobreport->client->company) ? $jobreport->client->company : 'UNDEFINED';
     $jobreport_date = isset($jobreport->datecreated) ? _d($jobreport->datecreated) : date('d/m/y');

     $message = "";
     $message .= "HN pada "  . $jobreport_date . "menerbitkan :\r\n";
     $message .= format_jobreport_number($jobreport->id) . " telah diterbitkan.\r\n";
     $message .= "Dengan terbitnya BAPP tersebut maka dengan ini : \r\n"; 
     $message .= "PO/WO/SPK/PH " . $project_name . "\r\n";
     $message .= "dari " . $jobreport_company . " dinyatakan telah selesai. \r\n";
     $message .= "data - data task dan lainnya terkait proyek tersebut dinyatakan telah lengkap. \r\n";

     log_activity(json_encode($message));
     
    $data['message'] = $message;

    $NUM_OF_ATTEMPTS = 5;
    $attempts = 0;
    $sleep = 2;
    do {

        try
        {
            sendTelegram($data);
        } catch (\TelegramBot\Api\Exception $e) {
            log_activity('Telegrams : Task ID '. $param['task_id']. ' On run '. $attempts . ' X, we hit a problem, ' . $e->getMessage());
          if($attempts >= $NUM_OF_ATTEMPTS){

            }
            $attempts++;
            sleep($sleep);
            continue;
        }

        break;

    } while($attempts < $NUM_OF_ATTEMPTS);



}


function telegrams_after_jobreport_updated($id){
     
     $CI = &get_instance();
     $CI->load->model('jobreports_model');
     $jobreport = $CI->jobreports_model->get($id);
     $CI->load->model('projects_model');
     $project = $CI->projects_model->get($jobreport->project_id);
     $project_name = isset($project->name) ? $project->name : 'UNDEFINED';
     $jobreport_company = isset($jobreport->client->company) ? $jobreport->client->company : 'UNDEFINED';
     $jobreport_date = isset($jobreport->datecreated) ? _d($jobreport->datecreated) : date('d/m/y');

     $message = "";
     $message .= "HN pada "  . $jobreport_date . " memperbaharui :\r\n";
     $message .= format_jobreport_number($jobreport->id) . " telah diterbitkan.\r\n";
     $message .= "Dengan terbitnya BAPP tersebut maka dengan ini : \r\n"; 
     $message .= "PO/WO/SPK/PH " . $project_name . "\r\n";
     $message .= "dari " . $jobreport_company . " dinyatakan telah selesai. \r\n";
     $message .= "data - data task dan lainnya terkait proyek tersebut dinyatakan telah lengkap. \r\n";
     
     log_activity(json_encode($message));

    $data['message'] = $message;


    $NUM_OF_ATTEMPTS = 5;
    $attempts = 0;
    $sleep = 2;
    do {

        try
        {
            sendTelegram($data);
        } catch (\TelegramBot\Api\Exception $e) {
            log_activity('Telegrams : Task ID '. $param['task_id']. ' On run '. $attempts . ' X, we hit a problem, ' . $e->getMessage());
          if($attempts >= $NUM_OF_ATTEMPTS){

            }
            $attempts++;
            sleep($sleep);
            continue;
        }

        break;

    } while($attempts < $NUM_OF_ATTEMPTS);

}

function telegram_create_assigned_qrcode_hook($id){
     
     log_activity( 'Hello, world!' );

}

function telegram_status_changed_hook($data){

    log_activity('telegram_status_changed');

}

function sendTelegram($data){
    $data['telegram_token']        = get_option('telegram_token');
    $data['telegram_group_chat_id']        = get_option('telegram_group_chat_id'); //1514861293

    $bot = new \TelegramBot\Api\BotApi($data['telegram_token']);
    $bot->sendMessage($data['telegram_group_chat_id'], $data['message']);
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
    
    $message = "";
    $message .= date('d/m/Y H:i:s') . "\r\n";
    $message .= $task->assignees[0]['firstname'] .' '. $task->assignees[0]['lastname'] . "\r\n";
    $message .= 'update status ' . "\r\n";
    $message .= $task->project_data->client_data->company . "\r\n";
    $message .= $task->project_data->name . "\r\n";
    $message .= $task->name . "\r\n";
    $message .= 'to ' . _l('task_status_'.$param['status']);
    
    $data['message'] = $message;


    $NUM_OF_ATTEMPTS = 5;
    $attempts = 0;
    $sleep = 2;
    do {

        try
        {
            sendTelegram($data);
        } catch (\TelegramBot\Api\Exception $e) {
            log_activity('Telegrams : Task ID '. $param['task_id']. ' On run '. $attempts . ' X, we hit a problem, ' . $e->getMessage());
          if($attempts >= $NUM_OF_ATTEMPTS){

            }
            $attempts++;
            sleep($sleep);
            continue;
        }

        break;

    } while($attempts < $NUM_OF_ATTEMPTS);


}

