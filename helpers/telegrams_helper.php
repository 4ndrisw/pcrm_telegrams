<?php

defined('BASEPATH') or exit('No direct script access allowed');

require 'vendor/autoload.php';

use TelegramBot\Api\BotApi;


function sendTelegram($data){
    $data['telegram_token']        = get_option('telegram_token');
    $data['telegram_group_chat_id']        = get_option('telegram_group_chat_id'); //1514861293

    $bot = new \TelegramBot\Api\BotApi($data['telegram_token']);
    $bot->sendMessage($data['telegram_group_chat_id'], $data['message']);
}

function telegramMessage($type='', $id='', $message){
     
    $data['message'] = $message;

    $NUM_OF_ATTEMPTS = 5;
    $attempts = 0;
    $sleep = 2;

    do {
        try
        {
            sendTelegram($data);
        } catch (\TelegramBot\Api\Exception $e) {
            log_activity('Telegrams : '. $type . ' ID '. $id . ' On run '. $attempts . ' X, we hit a problem, ' . $e->getMessage());
          if($attempts >= $NUM_OF_ATTEMPTS){

            }
            $attempts++;
            sleep($sleep);
            continue;
        }
        break;

    } while($attempts < $NUM_OF_ATTEMPTS);
}

function telegrams_before_cron_run($manual){
    log_activity('telegrams_before_cron_run_' . date('d/m/y H:i:s'));
}

function telegrams_after_cron_run($params = false){
    log_activity('telegrams_after_cron_run_' . date('d/m/y H:i:s'));
    
    $CI = &get_instance();

    $CI->load->model('scorecards/clients_recapitulation_model');

    $CI->load->model('schedules/schedules_model');
    $recapitulation_date = date('Y-m-d', time());
    $scorecards = $CI->clients_recapitulation_model->get_client_recapitulation_today($recapitulation_date);
    $staffs = $CI->clients_recapitulation_model->get_staff_grouped_today($recapitulation_date);
    
    foreach($staffs as $staff){ 
        $message = scorecards_daily_report($scorecards, $staff);
        log_activity($message);
        telegramMessage($type='CRON', $id='scorecards_daily_report', $message);
    }
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

function telegrams_schedule_status_changed($params){
    if ($params['new_status'] = 'Sent'){
        telegrams_after_schedule_updated($params['schedule_id']);
    }
}

function telegrams_schedule_send_to_customer_already_sent($schedule){
    telegrams_after_schedule_updated($schedule->id);
}



function telegrams_after_schedule_updated($id){
    if(get_option('schedule_send_telegram_message') == 0){
        log_activity('Schedules settings: '.'Not send telegram message');
        return;
    }

    $CI = &get_instance();
    $CI->load->model('schedules/schedules_model');
    $schedule = $CI->schedules_model->get($id);
    $schedule_members  = $CI->schedules_model->get_schedule_members($schedule->id,true);

    $CI->load->model('projects_model');
    $project = $CI->projects_model->get($schedule->project_id);
    $project_name = isset($project->name) ? $project->name : 'UNDEFINED';
    $schedule_company = isset($schedule->client->company) ? $schedule->client->company : 'UNDEFINED';
    $schedule_datecreated = isset($schedule->datecreated) ? _d($schedule->datecreated) : date('d/m/y');
    $schedule_date = isset($schedule->date) ? _d($schedule->date) : date('d/m/y');

    $message = "";
    $message .= get_staff_full_name($schedule->assigned) ." pada "  . $schedule_datecreated . " menerbitkan :\r\n";
    $message .= "Schedule ". format_schedule_number($schedule->id) . ".\r\n";
    $message .= "Tanggal ". ($schedule_date) . ".\r\n";
    $message .= "Berdasarkan PO/WO/SPK/PH : \r\n";
    $message .= $project_name . "\r\n";
    $message .= "dari " . $schedule_company . ".\r\n";
    if(!empty($schedule->items)){
        $message .= "dengan peralatan \r\n";
        $i = 1;
        foreach($schedule->items as $item){
            $description = isset($item['description']) ? $item['description'] : "";
            $long_description = isset($item['long_description']) ? $item['long_description'] : "";
            $message .=  $i . ". ". $description ." ". $long_description ."\r\n";
            $i++;
         }
    }

    if(!empty($schedule_members)){
        $message .= "Petugas :\r\n";
        $i = 1;
        foreach($schedule_members as $member){
          $message .=  $i .". ". $member['firstname'] ." ". $member['lastname'] ."\r\n";
          $i++;
        }
    }

    $message .= "Dengan terbitnya schedule tersebut maka staff diatas segera mempersiapkan segala sesuatu yang diperlukan. \r\n";
    
    $data['message'] = $message;

    $NUM_OF_ATTEMPTS = 5;
    $attempts = 0;
    $sleep = 2;

    $data['message'] = $message;
    //return $data;
    
    if($schedule->sent != '1' && $schedule->status != '2'){
        return;
    }
    
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

    log_activity(json_encode($message));
}

function telegrams_after_contract_added($insert_id){
    return telegrams_after_contract_updated($insert_id);
}

function telegrams_after_contract_updated($id){
    $CI = &get_instance();
    $CI->load->model('contracts_model');
    $contract = $CI->contracts_model->get($id);

    $datecreated = isset($contract->datecreated) ? $contract->datecreated : date('d/m/y H:i:s', time());
    $company = isset($contract->company) ? $contract->company : 'UNDEFINED';
    $subject = isset($contract->subject) ? $contract->subject : 'UNDEFINED';
    $datestart = isset($contract->datestart) ? $contract->datestart : 'UNDEFINED';
    $description = isset($contract->description) ? $contract->description : 'UNDEFINED';
    $url = site_url('contract/'.$id.'/'.$contract->hash);

    $message = "";
    $message .= "Pada " . $datecreated  . "\r\n";
    $message .= "Telah diterima contract dari" . "\r\n";
    $message .= "Perusahaan :" . $company . "\r\n";
    $message .= "PO/SPK/WO/PH :" . $subject . "\r\n";
    $message .= "Tanggal mulai :". $datestart . "\r\n";
    $message .= "Peralatan :" . "\r\n";
    $message .= $description . "\r\n";
    $message .= $url . "\r\n";

    $message .= "Mohon dipersiapkan project beserta tasknya, schedule, dokumen lain yang diperlukan untuk kelengkapan laporan." . "\r\n";

    log_activity($message);
    telegramMessage('Contract',$id, $message);

    return $message;
}

function telegrams_after_add_project($insert_id){
    return telegrams_after_update_project($insert_id);
}

function telegrams_after_update_project($id){
    $CI = &get_instance();
    $CI->load->model('projects_model');
    $project = $CI->projects_model->get($id);

    $CI->load->model('telegrams/telegrams_model');
    $project_members = $CI->telegrams_model->get_project_members($id);
    
    $member_string = "";
    $i = 1;
    foreach ($project_members as $member) {
        $member_string .= $i.". ".$member->staff_name."\r\n";
        $i++;
    }

    $datecreated = isset($project->datecreated) ? $project->datecreated : date('d/m/y H:i:s', time());
    $company = isset($project->client_data->company) ? $project->client_data->company : 'UNDEFINED';
    $name = isset($project->name) ? $project->name : 'UNDEFINED';
    $start_date = isset($project->start_date) ? $project->start_date : 'UNDEFINED';
    $deadline = isset($project->deadline) ? $project->deadline : 'UNDEFINED';
    $description = isset($project->description) ? strip_tags($project->description) : 'UNDEFINED';

    $find = array("Unit","unit");
    $replace = array("Unit\r\n","unit\r\n");

    $description = str_replace($find, $replace, $description);

    $url = admin_url('projects/view/'.$id);


    $message = "";
    $message .= "Pada " . $datecreated  . "\r\n";
    $message .= "Telah diterbitkan project dari" . "\r\n";
    $message .= "Perusahaan :" . $company . "\r\n";
    $message .= "PO/SPK/WO/PH :" . $name . "\r\n";
    $message .= "Tanggal mulai :". $start_date . "\r\n";
    $message .= "Tanggal deadline :". $deadline . "\r\n";
    $message .= "Peralatan :" . "\r\n";
    $message .= $description . "\r\n";
    $message .= "Petugas :" . "\r\n";
    $message .= $member_string . "\r\n";
    $message .= $url . "\r\n";
    

    log_activity($message);
    telegramMessage('Project',$id, $message);

    return $message;
}


function telegrams_after_jobreport_added($insert_id){ 
    if(get_option('jobreport_send_telegram_message') == 0){
        log_activity('Jobreports settings: '.'Not send telegram message');
        return;
    }

    $CI = &get_instance();
    $CI->load->model('jobreports/jobreports_model');
    $jobreport = $CI->jobreports_model->get($insert_id);
    $CI->load->model('projects_model');
    $project = $CI->projects_model->get($jobreport->project_id);
    $project_name = isset($project->name) ? $project->name : 'UNDEFINED';
    $jobreport_company = isset($jobreport->client->company) ? $jobreport->client->company : 'UNDEFINED';
    $jobreport_date = isset($jobreport->datecreated) ? _d($jobreport->datecreated) : date('d/m/y');

    $message = "";
    $message .= "HN pada "  . $jobreport_date . "menerbitkan :\r\n";
    $message .= format_jobreport_number($jobreport->id) . ".\r\n";
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
    
    if(get_option('jobreport_send_telegram_message') == 0){
        log_activity('Jobreports settings: '.'Not send telegram message');
        return;
    }
    
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

