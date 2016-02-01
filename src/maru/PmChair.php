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
use pocketmine\utils\TextFormat;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\protocol\PlayerActionPacket;
use pocketmine\event\player\PlayerQuitEvent;
class PmChair extends PluginBase implements Listener {
	private $onChair = [ ];
	public function onEnable() {
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}
	public function onTouch(PlayerInteractEvent $event) {
		$player = $event->getPlayer();
		$block = $event->getBlock();
		if (!isset($this->onChair[$player->getName()])) {
			if ($block instanceof Stair) {
				$addEntityPacket = new AddEntityPacket();
				$addEntityPacket->eid = $this->onChair[$player->getName()] = Entity::$entityCount++;
				$addEntityPacket->speedX = 0;
				$addEntityPacket->speedY = 0;
				$addEntityPacket->speedZ = 0;
				$addEntityPacket->pitch = 0;
				$addEntityPacket->yaw = 0;
				$addEntityPacket->item = 0;
				$addEntityPacket->meta = 0;
				$addEntityPacket->x = $block->getX() + 0.5;
				$addEntityPacket->y = $block->getY() + 0.3;
				$addEntityPacket->z = $block->getZ() + 0.5;
				$addEntityPacket->type = 69;
				$addEntityPacket->metadata = [
						Entity::DATA_FLAGS => [Entity::DATA_TYPE_BYTE, 1 << Entity::DATA_FLAG_INVISIBLE],
						Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, TextFormat::AQUA."휴식중"],
						Entity::DATA_SHOW_NAMETAG => [Entity::DATA_TYPE_BYTE, 1],
						Entity::DATA_NO_AI => [Entity::DATA_TYPE_BYTE, 1]
				];
				
				$setEntityLinkPacket = new SetEntityLinkPacket();
				$setEntityLinkPacket->from = $addEntityPacket->eid;
				$setEntityLinkPacket->to = $player->getId();
				$setEntityLinkPacket->type = true;
				
				foreach ($this->getServer()->getOnlinePlayers() as $target) {
					$target->dataPacket($addEntityPacket);
					if ($player !== $target) {
						$target->dataPacket($setEntityLinkPacket);
					}
				}
				
				$setEntityLinkPacket->to = 0;
				$player->dataPacket($setEntityLinkPacket);
			}
		} else {
			$removeEntityPacket = new RemoveEntityPacket();
			$removeEntityPacket->eid = $this->onChair[$player->getName()];
			$this->getServer()->broadcastPacket($this->getServer()->getOnlinePlayers(), $removeEntityPacket);
			unset($this->onChair[$player->getName()]);
		}
	}
	public function onJump(DataPacketReceiveEvent $event) {
		$packet = $event->getPacket();
		if (!$packet instanceof PlayerActionPacket) {
			return;
		}
		$player = $event->getPlayer();
		if ($packet->action === PlayerActionPacket::ACTION_JUMP && isset($this->onChair[$player->getName()])) {
			$removepk = new RemoveEntityPacket();
			$removepk->eid = $this->onChair[$player->getName()];
			$this->getServer()->broadcastPacket($this->getServer()->getOnlinePlayers(), $removepk);
			unset($this->onChair[$player->getName()]);
		}
	}
	public function onQuit(PlayerQuitEvent $event) {
		$player = $event->getPlayer();
		if(!isset($this->onChair[$player->getName()])) {
			return;
		}
		$removepk = new RemoveEntityPacket();
		$removepk->eid = $this->onChair[$player->getName()];
		$this->getServer()->broadcastPacket($this->getServer()->getOnlinePlayers(), $removepk);
		unset($this->onChair[$player->getName()]);
	}
}
?>