<?php if (!defined('APPLICATION')) exit();

$links = '<ul>';
$links .= '<li>'.anchor(t('jsConnect Documentation'), 'http://docs.vanillaforums.com/features/sso/jsconnect/').'</li>';
$links .= '<li>'.anchor(t('jsConnect Client Libraries'), 'http://docs.vanillaforums.com/features/sso/jsconnect/overview/#your-endpoint').'</li>';
$links .= '</ul>';

helpAsset(sprintf(t('About %s'), 'jsConnect'), t('You can connect to multiple sites that support jsConnect.'));
helpAsset(t('Need More Help?'), $links);

echo heading(sprintf(t('%s Settings'), 'jsConnect'), t('Add Connection'), '/settings/jsconnect/addedit', 'btn btn-primary js-modal');

$inTestMode = [];
foreach ($this->Data('Providers') as $Provider) {
    if ($Provider['TestMode']) {
        $inTestMode[] = $Provider;
    }
}
?>

<?php if (count($inTestMode) > 0): ?>
<div class="alert alert-warning padded"><?php echo t('Providers in test mode.', 'The following providers are in test mode and are not secure.  Incoming connections will be accepted without verifying the source.'); ?>
    <ul>
    <?php foreach ($inTestMode as $testProvider): ?>
        <li><?php echo $testProvider['Name']; ?></li>
    <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<section>
    <?php
    echo subheading(t('Signing In'));
    echo $this->Form->open();
    echo $this->Form->errors(); ?>
    <div class="form-group">
        <div class="label-wrap-wide">
            <?php echo t('Auto Connect'); ?>
            <?php echo '<div class="info">'.t('Automatically connect to an existing user account if it has the same email address.').'</div>' ?>
        </div>
        <div class="input-wrap-right">
            <?php echo $this->Form->toggle('Garden.Registration.AutoConnect'); ?>
        </div>
    </div>
    <div class="form-group">
        <div class="label-wrap-wide">
            <?php echo t('Use Popup Sign In Pages'); ?>
            <?php echo '<div class="info">'.t('Use popups for sign in pages (not recommended while using SSO).').'</div>'; ?>
        </div>
        <div class="input-wrap-right">
            <?php echo $this->Form->toggle('Garden.SignIn.Popup'); ?>
        </div>
    </div>
    <?php echo $this->Form->close('Save'); ?>
</section>
<div class="table-wrap">
    <table class="table-data js-tj">
        <thead>
        <tr>
            <th><?php echo t('Client ID'); ?></th>
            <th><?php echo t('Site Name'); ?></th>
            <th class="column-md"><?php echo t('Authentication URL'); ?></th>
            <th><?php echo t('Test') ?></th>
            <th class="column-sm"></th>
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
                    echo anchor(t('Test URL'), str_replace('=?', '=test', JsConnectPlugin::connectUrl($Provider, TRUE)));
                    ?>
                    <div class="JsConnectContainer UserInfo"></div>
                </td>
                <td class="options">
                    <div class="btn-group">
                        <?php
                        echo anchor(dashboardSymbol('edit'), '/settings/jsconnect/addedit?client_id='.urlencode($Provider['AuthenticationKey']), 'js-modal btn btn-icon', ['aria-label' => t('Edit'), 'title' => t('Edit')]);
                        echo anchor(dashboardSymbol('delete'), '/settings/jsconnect/delete?client_id='.urlencode($Provider['AuthenticationKey']), 'js-modal-confirm btn btn-icon', ['aria-label' => t('Delete'), 'title' => t('Delete')]);
                        ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
