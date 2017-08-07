<?php if (!defined('APPLICATION')) exit();?>
<h1>Customize Text</h1>
<?php echo $this->Form->open(); ?>
    <script type="text/javascript" language="javascript">
        jQuery(document).ready(function($) {
            if ($.autogrow)
                $('textarea.TextBox').autogrow();

            $('.Popular a').click(function() {
                $('input[name$=Keywords]').val($(this).attr('term'));

                $('form').submit();
                return false;
            });
        });
    </script>
    <div class="form-group">
        <div class="label-wrap-wide">
            <?php echo 'There are currently '.wrap($this->data('CountDefinitions', '0'), 'strong').' definitions available for editing. '; ?>
        </div>
        <div class="input-wrap-right">
            <?php echo anchor('Find More', '/settings/customizetext/rebuild', 'btn btn-primary'); ?>
        </div>
    </div>
    <div class="Popular padded">
        <?php echo 'Search for the text you want to customize. Partial searches work. For example: "disc" will return "discussion" and "all discussions", etc. '; ?>
        Popular Searches: <a term="howdy" href="#">Howdy Stranger</a>, <a term="module" href="#">It looks like you're new here...</a>, <a term="disc" href="#">Discussions</a>, <a term="comment" href="#">Comments</a>, <a term="email" href="#">Email</a>.
    </div>
    <div class="form-group">
        <div class="text-input-button no-grid">
                <?php
                echo $this->Form->errors();
                echo $this->Form->textBox('Keywords');
                echo $this->Form->button(t('Go'));
                ?>
        </div>
    </div>
<?php
if ($this->Form->getValue('Keywords', '') != '') {
    echo '<div class="padded italic">';
    printf(t("%s matches found for '%s'."), $this->data('CountMatches'), $this->Form->getValue('Keywords'));
    echo '</div>';
    echo '<ul>';

    foreach ($this->data('Matches') as $Key => $Definition) {
        $KeyHash = md5($Key);

        $DefinitionText = $Definition['def'];
        $DefinitionModified = (bool)$Definition['mod'];
        $ElementName = "def_{$KeyHash}";

        $CSSClass = "TextBox Definition";
        if ($DefinitionModified)
            $CSSClass .= " Modified";

        echo '<li class="form-group">';
        echo '<div class="label-wrap">';
        echo wrap(Gdn_Format::text($Key), 'label', ['for' => "Form_{$ElementName}"]);

        if ($this->Form->isPostBack()) {
            $SuppliedDefinition = $this->Form->getValue($ElementName);

            // Changed?
            if ($SuppliedDefinition !== FALSE && $SuppliedDefinition != $DefinitionText)
                if (!$DefinitionModified) $CSSClass .= " Modified";
        }
        echo '</div>';
        echo $this->Form->textBoxWrap($ElementName, ['multiline' => TRUE, 'class' => $CSSClass]);
        echo '</li>';
    }
    echo '</ul>';
}
echo $this->Form->close('Save All');
