<?php if (!defined('APPLICATION')) exit();
/**
* Renders (in the panel) a list of users who share a fingerprint with the specified user.
*/
class SharedFingerprintModule extends Gdn_Module {

	protected $_Data = FALSE;
	
	public function GetData($fingerprintUserID, $fingerprint) {
		if (!Gdn::Session()->CheckPermission('Garden.Users.Edit'))
			return;
		
		$this->_Data = Gdn::SQL()
			->Select()
			->From('User')
			->Where('Fingerprint', $fingerprint)
			->Where('UserID <>', $fingerprintUserID)
			->Get();
	}

	public function AssetTarget() {
		return 'Panel';
	}

	public function ToString() {
      if (!$this->_Data)
			return;
      
		if ($this->_Data->NumRows() == 0)
			return;
		
		ob_start();
		?>
      <div id="SharedFingerprint" class="Box">
         <h4><?php echo T("Shared Accounts"); ?> <span class="Count"><?php echo $this->_Data->NumRows(); ?></span></h4>
			<ul class="PanelInfo">
         <?php
			foreach ($this->_Data->Result() as $sharedAccount) {
				echo '<li><strong>'.UserAnchor($sharedAccount).'</strong><br /></li>';
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
