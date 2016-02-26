<?php if (!defined('APPLICATION')) exit(); ?>

<h1><?php echo t($this->Data['Title']); ?></h1>

<?php
echo $this->Form->open();
echo $this->Form->errors();
?>
<ul>
    <li><?php
        $pointsAwardEnabled = c('QnA.Points.Enabled');

        $textBoxAttributes = array();
        $checkBoxAttributes = array(
            'id' => 'IsPointsAwardEnabled',
        );

        if ($pointsAwardEnabled) {
            $checkBoxAttributes['checked'] = true;
        } else {
            $textBoxAttributes['disabled'] = true;
        }

        echo $this->Form->checkBox('QnA.Points.Enabled', t('Enables points award. This will gives users points for answering questions.'), $checkBoxAttributes);
    ?></li>
    <li class="PointAwardsInputs"<?php echo $pointsAwardEnabled ? null : ' style="display:none;"'?>><?php
        echo $this->Form->label(t('Point(s) per answer (Only the first user\'s answer to a question will award points)'), 'QnA.Points.Answer');
        echo $this->Form->textBox('QnA.Points.Answer', $textBoxAttributes);
    ?></li>
    <li class="PointAwardsInputs"<?php echo $pointsAwardEnabled ? null : ' style="display:none;"'?>><?php
        echo $this->Form->label(t('Points per accepted answer'), 'QnA.Points.AcceptedAnswer');
        echo $this->Form->textBox('QnA.Points.AcceptedAnswer', $textBoxAttributes);
    ?></li>
</ul>
<?php echo $this->Form->close('Save');
