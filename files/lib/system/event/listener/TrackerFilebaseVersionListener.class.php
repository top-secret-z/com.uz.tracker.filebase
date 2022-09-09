<?php
namespace filebase\system\event\listener;
use wcf\data\package\PackageCache;
use wcf\data\user\tracker\log\TrackerLogEditor;
use wcf\system\event\listener\IParameterizedEventListener;
use wcf\system\cache\builder\TrackerCacheBuilder;
use wcf\system\WCF;

/**
 * Listen to Filebase version event action.
 * 
 * @author		2016-2022 Zaydowicz
 * @license		GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package		com.uz.tracker.filebase
 */
class TrackerFilebaseVersionListener implements IParameterizedEventListener {
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
		if (!$this->tracker->wlfilFileVersion && !$this->tracker->otherModeration) return;
		
		// actions / data
		$action = $eventObj->getActionName();
		
		if ($this->tracker->wlfilFileVersion) {
			if ($action == 'create') {
				$returnValues = $eventObj->getReturnValues();
				$fileVersion = $returnValues['returnValues'];
				$this->link = $fileVersion->getLink();
				
				if ($fileVersion->isDisabled) $this->store('wcf.uztracker.description.filebase.version.addDisabled', 'wcf.uztracker.type.wlfil');
				else $this->store('wcf.uztracker.description.filebase.version.add', 'wcf.uztracker.type.wlfil');
			}
		}
		
		if ($this->tracker->otherModeration) {
			if ($action == 'disable' || $action == 'enable') {
				$objects = $eventObj->getObjects();
				foreach ($objects as $fileVersion) {
					$this->link = $fileVersion->getLink();
					if ($action == 'disable') {
						$this->store('wcf.uztracker.description.filebase.version.disable', 'wcf.uztracker.type.moderation');
					}
					else {
						$this->store('wcf.uztracker.description.filebase.version.enable', 'wcf.uztracker.type.moderation');
					}
				}
			}
			
			if ($action == 'delete') {
				$objects = $eventObj->getObjects();
				foreach ($objects as $fileVersion) {
					$this->link = '';
					$name = $fileVersion->filename;
					$content = $fileVersion->version;
					$this->store('wcf.uztracker.description.filebase.version.delete', 'wcf.uztracker.type.moderation', $name, $content);
				}
			}
		}
		
		if ($action == 'trash' || $action == 'restore') {
			$objects = $eventObj->getObjects();
			foreach ($objects as $fileVersion) {
				$this->link = $fileVersion->getLink();
				$file = $fileVersion->getFile();
				if ($action == 'trash') {
					if ($file->userID == $user->userID) {
						if ($this->tracker->wlfilFileVersion) $this->store('wcf.uztracker.description.filebase.version.trash', 'wcf.uztracker.type.wlfil');
					}
					else {
						if ($this->tracker->otherModeration) $this->store('wcf.uztracker.description.filebase.version.trash', 'wcf.uztracker.type.moderation');
					}
				}
				else {
					if ($file->userID == $user->userID) {
						if ($this->tracker->wlfilFileVersion) $this->store('wcf.uztracker.description.filebase.version.restore', 'wcf.uztracker.type.wlfil');
					}
					else {
						if ($this->tracker->otherModeration) $this->store('wcf.uztracker.description.filebase.version.restore', 'wcf.uztracker.type.moderation');
					}
				}
			}
		}
		
		// update includes review
		if ($action == 'update') {
			$params = $eventObj->getParameters();
			$objects = $eventObj->getObjects();
			
			foreach ($objects as $fileVersion) {
				$this->link = $fileVersion->getLink();
				$file = $fileVersion->getFile();
				if ($file->userID == $user->userID) {
					if ($this->tracker->wlfilFileVersion) $this->store('wcf.uztracker.description.filebase.version.update', 'wcf.uztracker.type.wlfil');
				}
				// reviews since 5.2
				elseif (isset($params['counters'])) {
					if ($this->tracker->wlfilFile) $this->store('wcf.uztracker.description.filebase.review', 'wcf.uztracker.type.wlfil');
				}
				else {
					if ($this->tracker->otherModeration) $this->store('wcf.uztracker.description.filebase.version.update', 'wcf.uztracker.type.moderation');
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
