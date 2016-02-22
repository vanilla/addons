<?php if (!defined('APPLICATION')) { exit(); } ?>
<div class="DataBox DataBox-AcceptedAnswers"><span id="accepted"></span>
    <h2 class="CommentHeading"><?php echo plural(count($Sender->data('Answers')), 'Best Answer', 'Best Answers'); ?></h2>
    <ul class="MessageList DataList AcceptedAnswers">
        <?php
        foreach ($Sender->data('Answers') as $Row) {
            $Sender->EventArguments['Comment'] = $Row;
            writeComment($Row, $Sender, Gdn::session(), 0);
        }
        ?>
    </ul>
</div>
