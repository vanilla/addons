<?php if (!defined('APPLICATION')) exit();?>
<h1>Customize Text</h1>
<?php echo $this->Form->Open(); ?>
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
            <?php echo 'There are currently '.Wrap($this->Data('CountDefinitions', '0'), 'strong').' definitions available for editing. '; ?>
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
if ($this->Form->GetValue('Keywords', '') != '') {
    echo '<div class="padded italic">';
    printf(t("%s matches found for '%s'."), $this->Data('CountMatches'), $this->Form->GetValue('Keywords'));
    echo '</div>';
    echo '<ul>';

    foreach ($this->Data('Matches') as $Key => $Definition) {
        $KeyHash = md5($Key);

        $DefinitionText = $Definition['def'];
        $DefinitionModified = (bool)$Definition['mod'];
        $ElementName = "def_{$KeyHash}";

        $CSSClass = "TextBox Definition";
        if ($DefinitionModified)
            $CSSClass .= " Modified";

        echo '<li class="form-group">';
        echo '<div class="label-wrap">';
        echo Wrap(Gdn_Format::Text($Key), 'label', array('for' => "Form_{$ElementName}"));

        if ($this->Form->IsPostBack()) {
            $SuppliedDefinition = $this->Form->GetValue($ElementName);

            // Changed?
            if ($SuppliedDefinition !== FALSE && $SuppliedDefinition != $DefinitionText)
                if (!$DefinitionModified) $CSSClass .= " Modified";
        }
        echo '</div>';
        echo $this->Form->textBoxWrap($ElementName, array('multiline' => TRUE, 'class' => $CSSClass));
        echo '</li>';
    }
    echo '</ul>';
}
echo $this->Form->Close('Save All');
