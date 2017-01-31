<?php

namespace maru;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\block\Stair;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\entity\Entity;
use pocketmine\network\protocol\SetEntityLinkPacket;
use pocketmine\network\protocol\RemoveEntityPacket;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\protocol\PlayerActionPacket;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\entity\Item;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Config;

class PmChair extends PluginBase implements Listener {
	private $onChair = [ ];
	private $doubleTap = [ ];
	private $messages;
	
	const m_version = 1;
	
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->loadMessage();
	}
	public function loadMessage() {
		$this->saveResource("messages.yml");
		$this->messages = (new Config($this->getDataFolder()."messages.yml", Config::YAML, [ ]))->getAll();
		$this->updateMessage();
	}
	public function updateMessage() {
		if($this->messages['m_version'] < self::m_version) {
			$this->saveResource("messages.yml", true);
			$this->messages = (new Config($this->getDataFolder()."messages.yml", Config::YAML, [ ]))->getAll();
		}
	}
	public function get($m) {
		return $this->messages[$this->messages['default-language'].'-'.$m];
	}
	public function onTouch(PlayerInteractEvent $event) {
		$player = $event->getPlayer ();
		$block = $event->getBlock ();
		if (! isset ( $this->onChair [$player->getName ()] )) {
			if ($block instanceof Stair) {
				if (! isset ( $this->doubleTap [$player->getName ()] )) {
					$this->doubleTap [$player->getName ()] = $this->_microtime ();
					$player->sendPopup ( TextFormat::RED . $this->get("touch-popup"));
					return;
				}
				if ($this->_microtime ( true ) - $this->doubleTap [$player->getName ()] < 0.5) {
					
					$addEntityPacket = new AddEntityPacket ();
					$addEntityPacket->eid = $this->onChair [$player->getName ()] = Entity::$entityCount ++;
					$addEntityPacket->speedX = 0;
					$addEntityPacket->speedY = 0;
					$addEntityPacket->speedZ = 0;
					$addEntityPacket->pitch = 0;
					$addEntityPacket->yaw = 0;
					$addEntityPacket->item = 0;
					$addEntityPacket->meta = 0;
					$addEntityPacket->x = $block->getX () + 0.5;
					$addEntityPacket->y = $block->getY () + 1.5;
					$addEntityPacket->z = $block->getZ () + 0.5;
					$addEntityPacket->type = Item::NETWORK_ID;
					$flag = 0;
					$flag |= (1 << Entity::DATA_FLAG_CAN_SHOW_NAMETAG) | (1 << Entity::DATA_FLAG_INVISIBLE) | (1 << Entity::DATA_FLAG_IMMOBILE) | (1 << Entity::DATA_FLAG_ALWAYS_SHOW_NAMETAG);
					$addEntityPacket->metadata = [
							Entity::DATA_NAMETAG => [
									Entity::DATA_TYPE_STRING,
									$this->get("tagblock-message")
							],
							Entity::DATA_FLAGS => [
									Entity::DATA_TYPE_LONG,
									$flag
							]
					];
					
					$setEntityLinkPacket = new SetEntityLinkPacket ();
					$setEntityLinkPacket->from = $addEntityPacket->eid;
					$setEntityLinkPacket->to = $player->getId ();
					$setEntityLinkPacket->type = true;
					
					foreach ( $this->getServer ()->getOnlinePlayers () as $target ) {
						$target->dataPacket ( $addEntityPacket );
						if ($player !== $target) {
							$target->dataPacket ( $setEntityLinkPacket );
						}
					}
					
					$setEntityLinkPacket->to = 0;
					$player->dataPacket ( $setEntityLinkPacket );
					unset($this->doubleTap[$player->getName()]);
				} else {
					$this->doubleTap [$player->getName ()] = $this->_microtime ();
					$player->sendPopup ( TextFormat::RED . $this->get("touch-popup") );
					return;
				}
			}
		} else {
			$removeEntityPacket = new RemoveEntityPacket ();
			$removeEntityPacket->eid = $this->onChair [$player->getName ()];
			$this->getServer ()->broadcastPacket ( $this->getServer ()->getOnlinePlayers (), $removeEntityPacket );
			unset ( $this->onChair [$player->getName ()] );
		}
	}
	public function _microtime () { 
		return array_sum(explode(' ',microtime()));
	}
	public function onJump(DataPacketReceiveEvent $event) {
		$packet = $event->getPacket ();
		if (! $packet instanceof PlayerActionPacket) {
			return;
		}
		$player = $event->getPlayer ();
		if ($packet->action === PlayerActionPacket::ACTION_JUMP && isset ( $this->onChair [$player->getName ()] )) {
			$removepk = new RemoveEntityPacket ();
			$removepk->eid = $this->onChair [$player->getName ()];
			$this->getServer ()->broadcastPacket ( $this->getServer ()->getOnlinePlayers (), $removepk );
			unset ( $this->onChair [$player->getName ()] );
		}
	}
	public function onQuit(PlayerQuitEvent $event) {
		$player = $event->getPlayer ();
		if (! isset ( $this->onChair [$player->getName ()] )) {
			return;
		}
		$removepk = new RemoveEntityPacket ();
		$removepk->eid = $this->onChair [$player->getName ()];
		$this->getServer ()->broadcastPacket ( $this->getServer ()->getOnlinePlayers (), $removepk );
		unset ( $this->onChair [$player->getName ()] );
	}
}
?>