<?php if (!defined('APPLICATION')) { exit(); } ?>
<h1><?php echo t($this->Data['Title']); ?></h1>
<div class="row form-group">
    <div class="label-wrap-wide">
        <?php echo sprintf(t('Enable %s'), t($this->Data['Title'])); ?>
        <div class="info"><?php echo t($this->Data['Description']); ?></div>
    </div>
    <div class="input-wrap-right">
        <span id="plaintext-toggle">
            <?php
            if ($this->Plugin->isEnabled()) {
                echo wrap(anchor('<div class="toggle-well"></div><div class="toggle-slider"></div>', $this->Plugin->autoTogglePath()), 'span', ['class' => "toggle-wrap toggle-wrap-on"]);
            } else {
                echo wrap(anchor('<div class="toggle-well"></div><div class="toggle-slider"></div>', $this->Plugin->autoTogglePath()), 'span', ['class' => "toggle-wrap toggle-wrap-off"]);
            }
            ?>
        </span>
    </div>
</div>
<?php if (!$this->Plugin->isEnabled()) {
    return;
} ?>
<h2><?php echo t('Add a new Auto Discussion Feed'); ?></h2>
<div class="AddFeed">
    <?php
    echo $this->Form->open([
        'action' => url('plugin/feeddiscussions/addfeed')
    ]);
    echo $this->Form->errors();

    $Refreshments = [
        "1m" => t("Every Minute"),
        "5m" => t("Every 5 Minutes"),
        "30m" => t("Twice Hourly"),
        "1h" => t("Hourly"),
        "1d" => t("Daily"),
        "3d" => t("Every 3 Days"),
        "1w" => t("Weekly"),
        "2w" => t("Every 2 Weeks")
    ];

    ?>
    <ul class="padded-bottom">
        <li class="form-group">
            <?php echo $this->Form->labelWrap('Feed URL', 'FeedURL'); ?>
            <?php echo $this->Form->textBoxWrap('FeedURL', ['class' => 'InputBox']); ?>
        </li>
        <li class="form-group">
            <div class="input-wrap no-label">
                <?php echo $this->Form->checkBox('Historical', t('Import Older Posts'), ['value' => '1']); ?>
            </div>
        </li>

        <li class="form-group">
            <?php echo $this->Form->labelWrap('Maximum Polling Frequency', 'Refresh'); ?>
            <div class="input-wrap">
                <?php echo $this->Form->dropDown('Refresh', $Refreshments, ['value' => "1d"]); ?>
            </div>
        </li>
        <li class="form-group">
            <?php echo $this->Form->labelWrap('Target Category', 'Category'); ?>
            <div class="input-wrap">
                <?php echo $this->Form->categoryDropDown('Category'); ?>
            </div>
        </li>
    </ul>
    <?php echo $this->Form->close("Add Feed"); ?>
</div>

<h2><?php echo t('Active Feeds'); ?></h2>
<div class="ActiveFeeds">
    <?php
    $NumFeeds = count($this->data('Feeds'));
    if (!$NumFeeds) {
        echo '<div class="italic padded">'.t("You have no active auto feeds at this time.").'</div>';
    } else {
        echo '<div class="italic padded">'.$NumFeeds." ".plural($NumFeeds, "Active Feed", "Active Feeds").'</div>'; ?>
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
                foreach ($this->data('Feeds') as $FeedURL => $FeedItem) {
                    $LastUpdate = $FeedItem['LastImport'];
                    $CategoryID = $FeedItem['Category'];
                    $Frequency = getValue($FeedItem['Refresh'], $Refreshments, t('Unknown'));
                    $Category = $this->data("Categories.{$CategoryID}.Name", 'Root');
                    ?>
                    <tr>
                        <td class="FeedItemURL"><?php echo anchor($FeedURL, $FeedURL); ?></td>
                        <td><?php echo $LastUpdate; ?></td>
                        <td><?php echo $Frequency; ?></td>
                        <td><?php echo $Category; ?></td>
                        <td class="DeleteFeed">
                            <?php echo anchor(dashboardSymbol('delete'), '/plugin/feeddiscussions/deletefeed/'.FeedDiscussionsPlugin::encodeFeedKey($FeedURL), 'btn btn-icon', ['aria-label' => t('Delete this Feed')]); ?>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</div>
