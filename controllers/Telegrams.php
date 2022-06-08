<?php

require 'vendor/autoload.php';
use app\services\telegrams\TelegramsPipeline;

use TelegramBot\Api\BotApi;

defined('BASEPATH') or exit('No direct script access allowed');

class Telegrams extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('telegrams_model');
        $this->load->model('clients_model');
        $this->load->model('projects_model');
    }

    /* Get all telegrams in case user go on index page */
    public function send($id = '')
    {
        if (!has_permission('telegrams', '', 'view')) {
            access_denied('telegrams');
        }

        $telegram_path = get_upload_path_by_type('telegrams') . $id . '/';
        //$telegram_path = get_upload_path_by_type('telegrams') . $telegram->id . '/';
        _maybe_create_upload_path('uploads/telegrams');
        _maybe_create_upload_path('uploads/telegrams/'.$telegram_path);

        $htmlformat = "

        satu 
        dua
        tiga \r\n 
        empat \r\n 
        lima
        create_new_telegram 

        ";

        $data['title']                 = _l('telegrams_message');
        $data['message']               = $htmlformat;

        $data['telegram_token']        = get_option('telegram_token');
        $data['telegram_group_chat_id']        = get_option('telegram_group_chat_id'); //1514861293
        $telegram_data = $data;

        $data['telegram_data']            = $telegram_data;
        
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

        $this->load->view('admin/telegrams/send', $data);
    }



}