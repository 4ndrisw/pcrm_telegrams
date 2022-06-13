<?php

require 'vendor/autoload.php';
use app\services\telegrams\TelegramsPipeline;

use TelegramBot\Api\BotApi;
use STS\Backoff\Backoff;

use Anam\PhantomMagick\Converter;

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

    public function send($id = '')
    {
    
        $data['title'] = 'Just test';
        $this->load->view('admin/telegrams/send', $data);
    }

}
