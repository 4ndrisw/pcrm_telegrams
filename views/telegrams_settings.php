<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php echo form_hidden('telegrams_settings'); ?>
<div class="horizontal-scrollable-tabs mbot15">
   <div role="tabpanel" class="tab-pane" id="telegrams">
      <?php echo render_input('settings[telegram_token]','telegram_token',get_option('telegram_token')); ?>
      <hr />
      <?php echo render_input('settings[telegram_bot_chat_id]','telegram_bot_chat_id',get_option('telegram_bot_chat_id')); ?>
      <hr />
      <?php echo render_input('settings[telegram_group_chat_id]','telegram_group_chat_id',get_option('telegram_group_chat_id')); ?>
      <hr />
   </div>
 <?php hooks()->do_action('after_telegrams_tabs_content'); ?>
</div>
