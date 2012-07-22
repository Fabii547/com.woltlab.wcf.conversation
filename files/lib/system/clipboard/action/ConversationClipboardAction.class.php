<?php
namespace wcf\system\clipboard\action;
use wcf\data\conversation\Conversation;
use wcf\system\clipboard\ClipboardEditorItem;
use wcf\system\clipboard\action\IClipboardAction;
use wcf\system\database\util\PreparedStatementConditionBuilder;
use wcf\system\exception\PermissionDeniedException;
use wcf\system\exception\SystemException;
use wcf\system\request\LinkHandler;
use wcf\system\WCF;

/**
 * Prepares clipboard editor items for conversations.
 * 
 * @author	Alexander Ebert
 * @copyright	2001-2012 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	com.woltlab.wcf.conversation
 * @subpackage	system.clipboard.action
 * @category 	Community Framework
 */
class ConversationClipboardAction implements IClipboardAction {
	/**
	 * list of conversations
	 * @var	array<wcf\data\conversation\Conversation>
	 */
	public $conversations = null;
	
	/**
	 * @see	wcf\system\clipboard\action\IClipboardAction::getTypeName()
	 */
	public function getTypeName() {
		return 'com.woltlab.wcf.conversation.conversation';
	}
	
	/**
	 * @see	wcf\system\clipboard\action\IClipboardAction::execute()
	 */
	public function execute(array $objects, $actionName, $typeData = array()) {
		if ($this->conversations === null) {
			// validate conversations
			$this->validateParticipation($objects);
		}
		
		// check if no conversation was accessible
		if (empty($this->conversations)) {
			return null;
		}
		
		$item = new ClipboardEditorItem();
		
		switch ($actionName) {
			case 'assignLabel':
				// check if user has labels
				$sql = "SELECT	COUNT(*) AS count
					FROM	wcf".WCF_N."_conversation_label
					WHERE	userID = ?";
				$statement = WCF::getDB()->prepareStatement($sql);
				$statement->execute(array(WCF::getUser()->userID));
				$row = $statement->fetchArray();
				if ($row['count'] == 0) {
					return null;
				}
				
				$item->addParameter('objectIDs', array_keys($this->conversations));
				$item->setName('conversation.assignLabel');
			break;
			
			case 'close':
				$conversationIDs = $this->validateClose();
				if (empty($conversationIDs)) {
					return null;
				}
				
				$item->addParameter('objectIDs', $conversationIDs);
				$item->addParameter('actionName', 'close');
				$item->addParameter('className', 'wcf\data\conversation\ConversationAction');
				$item->setName('conversation.close');
			break;
			
			case 'leave':
				$item->addParameter('objectIDs', array_keys($this->conversations));
				$item->setName('conversation.leave');
			break;
			
			case 'leavePermanently':
				$item->addParameter('objectIDs', array_keys($this->conversations));
				$item->setName('conversation.leavePermanetly');
			break;
			
			case 'open':
				$conversationIDs = $this->validateOpen();
				if (empty($conversationIDs)) {
					return null;
				}
			
				$item->addParameter('objectIDs', $conversationIDs);
				$item->addParameter('actionName', 'open');
				$item->addParameter('className', 'wcf\data\conversation\ConversationAction');
				$item->setName('conversation.open');
			break;
			
			default:
				throw new SystemException("Unknown action '".$actionName."'");
			break;
		}
		
		return $item;
	}
	
	/**
	 * Returns a list of conversations with user participation.
	 * 
	 * @param	array<wcf\data\conversation\Conversation>
	 * @return	array<wcf\data\conversation\Conversation>
	 */
	protected function validateParticipation(array $conversations) {
		$conversationIDs = array();
		
		// validate ownership
		foreach ($conversations as $conversation) {
			if ($conversation->userID != WCF::getUser()->userID) {
				$conversationIDs[] = $conversation->conversationID;
			}
		}
		
		// validate participation as non-owner
		if (!empty($conversationIDs)) {
			$conditions = new PreparedStatementConditionBuilder();
			$conditions->add("conversationID IN (?)", array($conversationIDs));
			$conditions->add("userID = ?", array(WCF::getUser()->userID));
			
			$sql = "SELECT	conversationID
				FROM	wcf".WCF_N."_conversation_to_user
				".$conditions;
			$statement = WCF::getDB()->prepareStatement($sql);
			$statement->execute($conditions->getParameters());
			while ($row = $statement->fetchArray()) {
				$index = array_search($row['conversationID'], $conversationIDs);
				unset($index);
			}
			
			// remove unaccessible conversations
			if (!empty($conversationIDs)) {
				foreach ($conversations as $index => $conversation) {
					if (in_array($conversation->conversationID, $conversationIDs)) {
						unset($conversations[$index]);
					}
				}
			}
		}
		
		foreach ($conversations as $conversation) {
			$this->conversations[$conversation->conversationID] = $conversation;
		}
	}
	
	/**
	 * Validates if user may close the given conversations.
	 * 
	 * @return	array<integer>
	 */
	protected function validateClose() {
		$conversationIDs = array();
		
		foreach ($this->conversations as $conversation) {
			if (!$conversation->isClosed && $conversation->userID == WCF::getUser()->userID) {
				$conversationIDs[] = $conversation->conversationID;
			}
		}
		
		return $conversationIDs;
	}
	
	/**
	 * Validates if user may open the given conversations.
	 *
	 * @return	array<integer>
	 */
	protected function validateOpen() {
		$conversationIDs = array();
	
		foreach ($this->conversations as $conversation) {
			if ($conversation->isClosed && $conversation->userID == WCF::getUser()->userID) {
				$conversationIDs[] = $conversation->conversationID;
			}
		}
	
		return $conversationIDs;
	}
	
	/**
	 * @see	wcf\system\clipboard\action\IClipboardAction::getEditorLabel()
	 */
	public function getEditorLabel(array $objects) {
		return WCF::getLanguage()->getDynamicVariable('wcf.clipboard.label.conversation.marked', array('count' => count($objects)));
	}
}
