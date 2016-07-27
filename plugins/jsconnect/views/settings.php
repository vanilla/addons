<?php if (!defined('APPLICATION')) exit(); ?>
<?php Gdn_Theme::assetBegin('Help'); ?>
<div class="Help Aside">
    <?php
    echo '<h2>'.sprintf(t('About %s'), 'jsConnect').'</h2>';
    echo T('You can connect to multiple sites that support jsConnect.');
    echo '<h2>', T('Need More Help?'), '</h2>';
    echo '<ul>';
    echo '<li>', Anchor(T('jsConnect Documentation'), 'http://docs.vanillaforums.com/features/sso/jsconnect/'), '</li>';
    echo '<li>', Anchor(T('jsConnect Client Libraries'), 'http://docs.vanillaforums.com/features/sso/jsconnect/overview/#your-endpoint'), '</li>';
    echo '</ul>';
    ?>
</div>
<?php Gdn_Theme::assetEnd(); ?>
<div class="header-block">
    <h1><?php echo sprintf(t('%s Settings'), 'jsConnect'); ?></h1>
    <?php echo Anchor(T('Add Connection'), '/settings/jsconnect/addedit', 'btn btn-primary js-modal'); ?>
</div>
<h2>Signing In</h2>
<?php
    echo $this->Form->open();
    echo $this->Form->errors(); ?>
    <div class="form-group row">
        <div class="label-wrap-wide">
            <?php echo t('Auto Connect'); ?>
            <?php echo '<div class="info">'.t('Automatically connect to an existing user account if it has the same email address.').'</div>' ?>
        </div>
        <div class="input-wrap-right">
            <?php echo $this->Form->toggle('Garden.Registration.AutoConnect'); ?>
        </div>
    </div>
    <div class="form-group row">
        <div class="label-wrap-wide">
            <?php echo t('Use Popup Sign In Pages'); ?>
            <?php echo '<div class="info">'.t('Use popups for sign in pages (not recommended while using SSO).').'</div>'; ?>
        </div>
        <div class="input-wrap-right">
            <?php echo $this->Form->toggle('Garden.SignIn.Popup'); ?>
        </div>
    </div>

    <?php echo '<div class="Buttons form-footer">';
    echo $this->Form->button('Save');
    echo '</div>';

    echo $this->Form->close();
?>
<div class="table-wrap padded">
    <table class="AltRows">
        <thead>
        <tr>
            <th><?php echo T('Client ID'); ?></th>
            <th><?php echo T('Site Name'); ?></th>
            <th><?php echo T('Authentication URL'); ?></th>
            <th><?php echo T('Test') ?></th>
            <th>&#160;</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($this->Data('Providers') as $Provider): ?>
            <tr>
                <td><?php echo htmlspecialchars($Provider['AuthenticationKey']); ?></td>
                <td><?php echo htmlspecialchars($Provider['Name']); ?></td>
                <td><?php echo htmlspecialchars($Provider['AuthenticateUrl']); ?></td>
                <td>
                    <?php
                    echo Anchor(T('Test URL'), str_replace('=?', '=test', JsConnectPlugin::connectUrl($Provider, TRUE)));
                    ?>
                    <div class="JsConnectContainer UserInfo"></div>
                </td>
                <td>
                    <div class="btn-group">
                        <?php
                        echo anchor(dashboardSymbol('edit'), '/settings/jsconnect/addedit?client_id='.urlencode($Provider['AuthenticationKey']), 'js-modal btn btn-icon', ['aria-label' => t('Edit')]);
                        echo anchor(dashboardSymbol('delete'), '/settings/jsconnect/delete?client_id='.urlencode($Provider['AuthenticationKey']), 'js-modal-confirm js-hijack btn btn-icon', ['aria-label' => t('Delete')]);
                        ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
