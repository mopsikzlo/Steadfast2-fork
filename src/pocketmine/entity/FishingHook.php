<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

namespace pocketmine\entity;

use pocketmine\event\player\PlayerFishEvent;
use pocketmine\level\format\FullChunk;
use pocketmine\nbt\tag\Compound;
use pocketmine\Player;
use pocketmine\item\Item as ItemItem;
use pocketmine\network\protocol\EntityEventPacket;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\Server;
use pocketmine\level\Level;

class FishingHook extends Projectile{
	const NETWORK_ID = 77;

	public $width = 0.25;
	public $length = 0.25;
	public $height = 0.25;

	protected $gravity = 0.1;
	protected $drag = 0.05;

	public $data = 0;
	public $attractTimer = 100;
	public $coughtTimer = 0;
	public $damageRod = false;

	public function initEntity(){
		parent::initEntity();

		if(isset($this->namedtag->Data)){
			$this->data = $this->namedtag["Data"];
		}

		// $this->setDataProperty(FallingSand::DATA_BLOCK_INFO, self::DATA_TYPE_INT, $this->getData());
	}

	public function __construct(FullChunk $chunk, Compound $nbt, Entity $shootingEntity = null){
		parent::__construct($chunk, $nbt, $shootingEntity);
	}

	public function setData($id){
		$this->data = $id;
	}

	public function getData(){
		return $this->data;
	}

	public function onUpdate($currentTick){
		if($this->closed){
			return false;
		}

		//$this->timings->startTiming();

		$hasUpdate = parent::onUpdate($currentTick);

		if($this->isCollided && $this->isInsideOfWater()){
			$this->motionX = 0;
			$this->motionY += 0.01;
			$this->motionZ = 0;
			$this->motionChanged = true;
			$hasUpdate = true;
		}
		if($this->attractTimer === 0 && mt_rand(0, 100) <= 30){ // chance, that a fish bites
			$this->coughtTimer = mt_rand(5, 10) * 20; // random delay to catch fish
			$this->attractTimer = mt_rand(30, 100) * 20; // reset timer
			$this->attractFish();
			if($this->shootingEntity instanceof Player) $this->shootingEntity->sendTip("A fish bites!");
		}elseif($this->attractTimer > 0){
			$this->attractTimer--;
		}
		if($this->coughtTimer > 0){
			$this->coughtTimer--;
			$this->fishBites();
		}

		//$this->timings->stopTiming();

		return $hasUpdate;
	}

	public function fishBites(){
		if($this->shootingEntity instanceof Player){
			$pk = new EntityEventPacket();
			$pk->eid = $this->shootingEntity->getId();//$this or $this->shootingEntity
			$pk->event = EntityEventPacket::FISH_HOOK_HOOK;
			Server::broadcastPacket($this->shootingEntity->hasSpawned, $pk);
		}
	}

	public function attractFish(){
		if($this->shootingEntity instanceof Player){
			$pk = new EntityEventPacket();
			$pk->eid = $this->shootingEntity->getId();//$this or $this->shootingEntity
			$pk->event = EntityEventPacket::FISH_HOOK_BUBBLE;
			Server::broadcastPacket($this->shootingEntity->hasSpawned, $pk);
		}
	}

	public function reelLine(){
		$this->damageRod = false;

		if($this->shootingEntity instanceof Player && $this->coughtTimer > 0){
			$fishes = [ItemItem::RAW_FISH, ItemItem::RAW_SALMON, ItemItem::CLOWN_FISH, ItemItem::PUFFER_FISH];
			$fish = array_rand($fishes, 1);
			$item = ItemItem::get($fishes[$fish]);
			$this->getLevel()->getServer()->getPluginManager()->callEvent($ev = new PlayerFishEvent($this->shootingEntity, $item, $this));
			if(!$ev->isCancelled()){
				$this->shootingEntity->getInventory()->addItem($item);
				$this->shootingEntity->addExperience(mt_rand(1, 6));
				$this->damageRod = true;
			}
		}

		if($this->shootingEntity instanceof Player){
			$this->shootingEntity->unlinkHookFromPlayer();
		}

		if(!$this->closed){
			$this->kill();
			$this->close();
		}

		return $this->damageRod;
	}

	public function spawnTo(Player $player) {
		if (!isset($this->hasSpawned[$player->getId()]) && isset($player->usedChunks[Level::chunkHash($this->chunk->getX(), $this->chunk->getZ())])) {
			$this->hasSpawned[$player->getId()] = $player;
			$pk = new AddEntityPacket();
			$pk->eid = $this->getId();
			$pk->type = FishingHook::NETWORK_ID;
			$pk->x = $this->x;
			$pk->y = $this->y;
			$pk->z = $this->z;
			$pk->speedX = $this->motionX;
			$pk->speedY = $this->motionY;
			$pk->speedZ = $this->motionZ;
			$pk->yaw = $this->yaw;
			$pk->pitch = $this->pitch;
//			$pk->metadata = $this->dataProperties;
			$player->dataPacket($pk);
		}
	}

}
