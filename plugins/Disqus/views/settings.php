<?php if (!defined('APPLICATION')) exit(); ?>

<h1><?php echo $this->data('Title'); ?></h1>

<div class="PageInfo">
    <?php echo t('The Disqus plugin allows users to sign in using their Disqus account.', 'The Disqus plugin allows users to sign in using their Disqus account. <b>You must register your application with Disqus for this plugin to work.</b>'); ?>
</div>

<?php
$Form = $this->Form;

echo $Form->open();
echo $Form->errors();

echo $Form->simple(array(
    'AuthenticationKey' => array('LabelCode' => 'Consumer Key', 'Options' => array('class' => 'InputBox WideInput')),
    'AssociationSecret' => array('LabelCode' => 'Consumer Secret', 'Options' => array('class' => 'InputBox WideInput'))
));

echo $Form->button('Save');
echo $Form->close();
