<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: telegrams
Description: Default module for defining telegrams
Version: 1.0.1
Requires at least: 2.3.*
*/

define('TELEGRAMS_MODULE_NAME', 'telegrams');
define('TELEGRAM_ATTACHMENTS_FOLDER', 'uploads/telegrams/');

hooks()->add_action('admin_init', 'telegrams_module_init_menu_items');
hooks()->add_action('admin_init', 'telegrams_permissions');
//hooks()->add_action('clients_init', 'telegrams_clients_area_menu_items');

hooks()->add_action('app_admin_head', 'telegrams_head_component');
//hooks()->add_action('app_admin_footer', 'telegrams_footer_js__component');
hooks()->add_action('admin_init', 'telegrams_settings_tab');
hooks()->add_action('task_status_changed','telegrams_task_status_changed');


//hooks()->do_action('before_cron_run', $manually);
hooks()->add_action('before_cron_run', 'telegrams_before_cron_run');
hooks()->add_action('after_cron_run', 'telegrams_notification');


/*
function telegrams_add_dashboard_widget($widgets)
{
    
    $widgets[] = [
        'path'      => 'telegrams/widgets/telegram_this_week',
        'container' => 'left-8',
    ];

    return $widgets;

}
*/

function telegrams_permissions()
{
    $capabilities = [];

    $capabilities['capabilities'] = [
            'view'   => _l('permission_view') . '(' . _l('permission_global') . ')',
            'create' => _l('permission_create'),
            'edit'   => _l('permission_edit'),
            'delete' => _l('permission_delete'),
    ];

    register_staff_capabilities('telegrams', $capabilities, _l('telegrams'));
}


/**
* Register activation module hook
*/
register_activation_hook(TELEGRAMS_MODULE_NAME, 'telegrams_module_activation_hook');

function telegrams_module_activation_hook()
{
    $CI = &get_instance();
    require_once(__DIR__ . '/install.php');
}

/**
* Register deactivation module hook
*/
register_deactivation_hook(TELEGRAMS_MODULE_NAME, 'telegrams_module_deactivation_hook');

function telegrams_module_deactivation_hook()
{

     log_activity( 'Hello, world! . telegrams_module_deactivation_hook ' );
}

//hooks()->add_action('deactivate_' . $module . '_module', $function);

/**
* Register language files, must be registered if the module is using languages
*/
register_language_files(TELEGRAMS_MODULE_NAME, [TELEGRAMS_MODULE_NAME]);

/**
 * Init telegrams module menu items in setup in admin_init hook
 * @return null
 */
function telegrams_module_init_menu_items()
{
    $CI = &get_instance();

    $CI->app->add_quick_actions_link([
            'name'       => _l('telegram'),
            'url'        => 'telegrams',
            'permission' => 'telegrams',
            'position'   => 57,
            ]);
    /*
    if (has_permission('telegrams', '', 'view')) {
        $CI->app_menu->add_sidebar_menu_item('telegrams', [
                'slug'     => 'telegrams-tracking',
                'name'     => _l('telegrams'),
                //'collapse' => true, // Indicates that this item will have submitems
                'icon'     => 'fa fa-hourglass-half',
                'href'     => admin_url('telegrams'),
                'position' => 14,
        ]);
    }
    */
}

function module_telegrams_action_links($actions)
{
    $actions[] = '<a href="' . admin_url('settings?group=telegrams') . '">' . _l('settings') . '</a>';

    return $actions;
}

/**
 * [perfex_dark_theme_settings_tab net menu item in setup->settings]
 * @return void
 */
function telegrams_settings_tab()
{
    $CI = &get_instance();
    $CI->app_tabs->add_settings_tab('telegrams', [
        'name'     => _l('settings_group_telegrams'),
        'view'     => 'telegrams/telegrams_settings',
        'position' => 52,
    ]);
}

$CI = &get_instance();
$CI->load->helper(TELEGRAMS_MODULE_NAME . '/telegrams');

if(($CI->uri->segment(0)=='admin' && $CI->uri->segment(1)=='telegrams') || $CI->uri->segment(1)=='telegrams'){
    $CI->app_css->add(TELEGRAMS_MODULE_NAME.'-css', base_url('modules/'.TELEGRAMS_MODULE_NAME.'/assets/css/'.TELEGRAMS_MODULE_NAME.'.css'));
    $CI->app_scripts->add(TELEGRAMS_MODULE_NAME.'-js', base_url('modules/'.TELEGRAMS_MODULE_NAME.'/assets/js/'.TELEGRAMS_MODULE_NAME.'.js'));
}

