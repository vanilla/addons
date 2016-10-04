<?php if (!defined('APPLICATION')) exit(); ?>
<h1><?php echo t($this->Data['Title']); ?></h1>
<div class="row form-group">
    <div class="label-wrap-wide">
        <?php echo sprintf(t('Enable %s'), t($this->Data['Title'])); ?>
        <div class="info"><?php echo t($this->Data['Description']); ?></div>
    </div>
    <div class="input-wrap-right">
        <span id="plaintext-toggle">
            <?php
            if ($this->Plugin->IsEnabled()) {
                echo wrap(anchor('<div class="toggle-well"></div><div class="toggle-slider"></div>', $this->Plugin->AutoTogglePath()), 'span', array('class' => "toggle-wrap toggle-wrap-on"));
            } else {
                echo wrap(anchor('<div class="toggle-well"></div><div class="toggle-slider"></div>', $this->Plugin->AutoTogglePath()), 'span', array('class' => "toggle-wrap toggle-wrap-off"));
            }
            ?>
        </span>
    </div>
</div>
<?php if (!$this->Plugin->IsEnabled()) return; ?>
<h2><?php echo t('Add a new Auto Discussion Feed'); ?></h2>
<div class="AddFeed">
    <?php
    echo $this->Form->Open(array(
        'action'  => Url('plugin/feeddiscussions/addfeed')
    ));
    echo $this->Form->Errors();

    $Refreshments = array(
        "1m"  => T("Every Minute"),
        "5m"  => T("Every 5 Minutes"),
        "30m" => T("Twice Hourly"),
        "1h"  => T("Hourly"),
        "1d"  => T("Daily"),
        "3d"  => T("Every 3 Days"),
        "1w"  => T("Weekly"),
        "2w"  => T("Every 2 Weeks")
    );

    ?>
    <ul>
        <li class="form-group">
            <?php echo $this->Form->labelWrap('Feed URL', 'FeedURL'); ?>
            <?php echo $this->Form->textBoxWrap('FeedURL', array('class' => 'InputBox')); ?>
        </li>
        <li class="form-group">
            <div class="input-wrap no-label">
                <?php echo $this->Form->checkBox('Historical', T('Import Older Posts'), array('value' => '1')); ?>
            </div>
        </li>

        <li class="form-group">
            <?php echo $this->Form->labelWrap('Maximum Polling Frequency', 'Refresh'); ?>
            <div class="input-wrap">
                <?php echo $this->Form->dropDown('Refresh', $Refreshments, array('value'  => "1d")); ?>
            </div>
        </li>
        <li class="form-group">
            <?php echo $this->Form->labelWrap('Target Category', 'Category'); ?>
            <div class="input-wrap">
                <?php echo $this->Form->CategoryDropDown('Category'); ?>
            </div>
        </li>
    </ul>
    <div class="form-footer padded-bottom">
        <?php echo $this->Form->Close("Add Feed"); ?>
    </div>
</div>

<h2><?php echo T('Active Feeds'); ?></h2>
<div class="ActiveFeeds">
    <?php
    $NumFeeds = count($this->Data('Feeds'));
    if (!$NumFeeds) {
        echo '<div class="italic padded">'.t("You have no active auto feeds at this time.").'</div>';
    } else {
        echo '<div class="italic padded">'.$NumFeeds." ".Plural($NumFeeds, "Active Feed", "Active Feeds").'</div>'; ?>
        <div class="table-wrap">
            <table class="table-data js-tj">
                <thead>
                <tr>
                    <th class="column-xl"><?php echo t('Feed Url'); ?></th>
                    <th><?php echo t('Updated'); ?></th>
                    <th><?php echo t('Refresh'); ?></th>
                    <th><?php echo t('Category'); ?></th>
                    <th class="column-sm"></th>
                </tr>
                </thead>
                <tbody>
                <?php
                foreach ($this->Data('Feeds') as $FeedURL => $FeedItem) {
                    $LastUpdate = $FeedItem['LastImport'];
                    $CategoryID = $FeedItem['Category'];
                    $Frequency = GetValue($FeedItem['Refresh'], $Refreshments, T('Unknown'));
                    $Category = $this->Data("Categories.{$CategoryID}.Name", 'Root');
                    ?>
                    <tr>
                        <td class="FeedItemURL"><?php echo Anchor($FeedURL,$FeedURL); ?></td>
                        <td><?php echo $LastUpdate; ?></td>
                        <td><?php echo $Frequency; ?></td>
                        <td><?php echo $Category; ?></td>
                        <td class="DeleteFeed">
                            <?php echo anchor(dashboardSymbol('delete'), '/plugin/feeddiscussions/deletefeed/'.FeedDiscussionsPlugin::EncodeFeedKey($FeedURL), 'btn btn-icon', ['aria-label' => t('Delete this Feed')]); ?>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</div>
