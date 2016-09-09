<?php if (!defined('APPLICATION')) exit(); ?>

<h1><?php echo $this->data('Title'); ?></h1>
<div class="padded alert alert-warning">
    <?php echo sprintf(t('You must register your application with %s for this plugin to work.'), t('Disqus')); ?>
</div>
<div class="padded">
    <?php echo t('The Disqus plugin allows users to sign in using their Disqus account.'); ?>
</div>

<?php
$form = $this->Form;

echo $form->open();
echo $form->errors();

echo $form->simple(array(
    'AuthenticationKey' => array('LabelCode' => 'Consumer Key'),
    'AssociationSecret' => array('LabelCode' => 'Consumer Secret')
));

echo $form->button('Save');
echo $form->close();
