<?php if (!defined('APPLICATION')) exit(); ?>

<h1><?php echo T($this->Data['Title']); ?></h1>
<div class="Info">
    <?php echo t($this->Data['PluginDescription']); ?>
</div>
<h3><?php echo t('Settings'); ?></h3>
<?php
    echo $this->Form->open();
    echo $this->Form->errors();
?>
<ul>
    <li><?php
        echo $this->Form->label('Display condition', 'Plugin.Example.RenderCondition');
        echo $this->Form->dropDown('Plugin.Example.RenderCondition', array(
            'all' => 'Discussions & Announcements',
            'announcements' => 'Just Announcements',
            'discussions' => 'Just Discussions'
        ));
    ?></li>
    <li><?php
        echo $this->Form->label('Excerpt length', 'Plugin.Example.TrimSize');
        echo $this->Form->textbox('Plugin.Example.TrimSize');
    ?></li>
</ul>
<?php
    echo $this->Form->close('Save');
?>
