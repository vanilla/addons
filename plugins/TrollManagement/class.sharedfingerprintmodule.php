<?php if (!defined('APPLICATION')) exit();
/**
* Renders (in the panel) a list of users who share a fingerprint with the specified user.
*/
class SharedFingerprintModule extends Gdn_Module {

	protected $_Data = FALSE;

	public function getData($fingerprintUserID, $fingerprint) {
        if (Gdn::session()->getPermissions()->hasRanked('Garden.Users.Edit') !== true)
            return;
		$this->_Data = Gdn::sql()
			->select()
			->from('User')
			->where('Fingerprint', $fingerprint)
			->where('UserID <>', $fingerprintUserID)
			->get();
	}

	public function assetTarget() {
		return 'Panel';
	}

	public function toString() {
      if (!$this->_Data)
			return;

		if ($this->_Data->numRows() == 0)
			return;

		ob_start();
		?>
      <div id="SharedFingerprint" class="Box">
         <h4><?php echo t("Shared Accounts"); ?> <span class="Count"><?php echo $this->_Data->numRows(); ?></span></h4>
			<ul class="PanelInfo">
         <?php
			foreach ($this->_Data->result() as $sharedAccount) {
				echo '<li><strong>'.userAnchor($sharedAccount).'</strong><br /></li>';
			}
         ?>
			</ul>
		</div>
		<?php
		$string = ob_get_contents();
		@ob_end_clean();
		return $string;
	}
}
