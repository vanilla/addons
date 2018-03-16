<?php if (!defined('APPLICATION')) exit(); ?>
    <h1><?php echo $this->data('Title'); ?></h1>
<div class="padded alert alert-warning">
    <?php echo sprintf(t('You must register your application with %s for this plugin to work.'), t('Mastodon')); ?>
</div>
<?php
$Cf = $this->ConfigurationModule;

$Cf->render();
?>
