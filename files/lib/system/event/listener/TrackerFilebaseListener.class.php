<?php
namespace filebase\system\event\listener;
use wcf\data\package\PackageCache;
use wcf\data\user\tracker\log\TrackerLogEditor;
use wcf\system\event\listener\IParameterizedEventListener;
use wcf\system\cache\builder\TrackerCacheBuilder;
use wcf\system\WCF;

/**
 * Listen to Filebase event action.
 * 
 * @author		2016-2022 Zaydowicz
 * @license		GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package		com.uz.tracker.filebase
 */
class TrackerFilebaseListener implements IParameterizedEventListener {
	/**
	 * tracker and link
	 */
	protected $tracker = null;
	protected $link = '';
	
	/**
	 * @inheritDoc
	 */
	public function execute($eventObj, $className, $eventName, array &$parameters) {
		if (!MODULE_TRACKER) return;
		
		// only if user is to be tracked
		$user = WCF::getUser();
		if (!$user->userID || !$user->isTracked || WCF::getSession()->getPermission('mod.tracking.noTracking')) return;
		
		// only if trackers
		$trackers = TrackerCacheBuilder::getInstance()->getData();
		if (!isset($trackers[$user->userID])) return;
		
		$this->tracker = $trackers[$user->userID];
		if (!$this->tracker->wlfilFile && !$this->tracker->otherModeration) return;
		
		// actions / data
		$action = $eventObj->getActionName();
		
		if ($this->tracker->wlfilFile) {
			if ($action == 'create') {
				$returnValues = $eventObj->getReturnValues();
				$file = $returnValues['returnValues'];
				$this->link = $file->getLink();
				
				if ($file->isDisabled) $this->store('wcf.uztracker.description.filebase.addDisabled', 'wcf.uztracker.type.wlfil');
				else $this->store('wcf.uztracker.description.filebase.add', 'wcf.uztracker.type.wlfil');
			}
		}
		
		if ($this->tracker->otherModeration) {
			if ($action == 'disable' || $action == 'enable') {
				$objects = $eventObj->getObjects();
				foreach ($objects as $file) {
					$this->link = $file->getLink();
					if ($action == 'disable') {
						$this->store('wcf.uztracker.description.filebase.disable', 'wcf.uztracker.type.moderation');
					}
					else {
						$this->store('wcf.uztracker.description.filebase.enable', 'wcf.uztracker.type.moderation');
					}
				}
			}
			
			if ($action == 'delete') {
				$objects = $eventObj->getObjects();
				foreach ($objects as $file) {
					$this->link = '';
					$name = $file->subject;
					$content = $file->message;
					$this->store('wcf.uztracker.description.filebase.delete', 'wcf.uztracker.type.moderation', $name, $content);
				}
			}
			
			if ($action == 'setAsFeatured' || $action == 'unsetAsFeatured') {
				$objects = $eventObj->getObjects();
				foreach ($objects as $file) {
					$this->link = $file->getLink();
					if ($action == 'setAsFeatured') {
						$this->store('wcf.uztracker.description.filebase.setAsFeatured', 'wcf.uztracker.type.moderation');
					}
					else {
						$this->store('wcf.uztracker.description.filebase.unsetAsFeatured', 'wcf.uztracker.type.moderation');
					}
				}
			}
		}
		
		if ($action == 'trash' || $action == 'restore') {
			$objects = $eventObj->getObjects();
			foreach ($objects as $file) {
				$this->link = $file->getLink();
				if ($action == 'trash') {
					if ($file->userID == $user->userID) {
						if ($this->tracker->wlfilFile) $this->store('wcf.uztracker.description.filebase.trash', 'wcf.uztracker.type.wlfil');
					}
					else {
						if ($this->tracker->otherModeration) $this->store('wcf.uztracker.description.filebase.trash', 'wcf.uztracker.type.moderation');
					}
				}
				else {
					if ($file->userID == $user->userID) {
						if ($this->tracker->wlfilFile) $this->store('wcf.uztracker.description.filebase.restore', 'wcf.uztracker.type.wlfil');
					}
					else {
						if ($this->tracker->otherModeration) $this->store('wcf.uztracker.description.filebase.restore', 'wcf.uztracker.type.moderation');
					}
				}
			}
		}
		
		// update includes review
		if ($action == 'update') {
			$params = $eventObj->getParameters();
			$objects = $eventObj->getObjects();
			
			foreach ($objects as $file) {
				$this->link = $file->getLink();
				if ($file->userID == $user->userID) {
					if ($this->tracker->wlfilFile) $this->store('wcf.uztracker.description.filebase.update', 'wcf.uztracker.type.wlfil');
				}
				// reviews since 5.2, track only version action
				elseif (isset($params['counters'])) {
					continue;
				}
				else {
					if ($this->tracker->otherModeration) $this->store('wcf.uztracker.description.filebase.update', 'wcf.uztracker.type.moderation');
				}
			}
		}
	}
	
	/**
	 * store log entry
	 */
	protected function store ($description, $type, $name = '', $content = '') {
		$packageID = PackageCache::getInstance()->getPackageID('com.uz.tracker.filebase');
		TrackerLogEditor::create(array(
				'description' => $description,
				'link' => $this->link,
				'name' => $name,
				'trackerID' => $this->tracker->trackerID,
				'type' => $type,
				'packageID' => $packageID,
				'content' => $content
		));
	}
}
