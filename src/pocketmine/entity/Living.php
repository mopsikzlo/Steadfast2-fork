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


use pocketmine\block\Block;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\Timings;
use pocketmine\item\Item as ItemItem;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\network\Network;
use pocketmine\network\protocol\AnimatePacket;
use pocketmine\network\protocol\EntityEventPacket;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\BlockIterator;
use pocketmine\utils\Binary;

abstract class Living extends Entity implements Damageable{

	protected $gravity = 0.08;
	protected $drag = 0.02;

	protected $attackTime = 0;

	protected $invisible = false;

	protected function initEntity(){
		parent::initEntity();

		if(isset($this->namedtag->HealF)){
			$this->namedtag->Health = new ShortTag("Health", (int) $this->namedtag["HealF"]);
			unset($this->namedtag->HealF);
		}

		if(!isset($this->namedtag->Health) or !($this->namedtag->Health instanceof Short)){
			$this->namedtag->Health = new ShortTag("Health", $this->getMaxHealth());
		}

		$this->setHealth($this->namedtag["Health"]);
	}

	public function setHealth($amount){
		$wasAlive = !$this->dead;
		parent::setHealth($amount);
		if(!$this->dead and !$wasAlive){
			$pk = new EntityEventPacket();
			$pk->eid = $this->getId();
			$pk->event = EntityEventPacket::RESPAWN;
			Server::broadcastPacket($this->hasSpawned, $pk);
		}
	}

	public function saveNBT(){
		parent::saveNBT();
		$this->namedtag->Health = new ShortTag("Health", $this->getHealth());
	}

	public abstract function getName();

	public function hasLineOfSight(Entity $entity){
		//TODO: head height
		return true;
		//return $this->getLevel()->rayTraceBlocks(Vector3::createVector($this->x, $this->y + $this->height, $this->z), Vector3::createVector($entity->x, $entity->y + $entity->height, $entity->z)) === null;
	}

	public function heal($amount, EntityRegainHealthEvent $source){
		parent::heal($amount, $source);
		if($source->isCancelled()){
			return;
		}

		$this->attackTime = 0;
	}

	public function attack($damage, EntityDamageEvent $source){
		if($this->attackTime > 0 or $this->noDamageTicks > 0){
			$lastCause = $this->getLastDamageCause();
			if($lastCause !== null and $lastCause->getDamage() >= $damage){
				$source->setCancelled();
			}
		}

		parent::attack($damage, $source);

		if($source->isCancelled()){
			return;
		}

		if(isset($damage[EntityDamageByEntityEvent::MODIFIER_CRITICAL])){
		    $pk = new AnimatePacket();
		    $pk->action = AnimatePacket::ACTION_CRITICAL_HIT;
		    $pk->eid = $this->getId();

		    Server::broadcastPacket($this->getViewers(), $pk);
        }

		$this->attackTime = 10; //0.5 seconds cooldown

		if($source instanceof EntityDamageByEntityEvent){
			$e = $source->getDamager();

			if($e->isOnFire() > 0){
				$this->setOnFire(2 * $this->server->getDifficulty());
			}

			$deltaX = $this->x - $e->x;
			$deltaZ = $this->z - $e->z;
			$this->knockBack($e, $damage, $deltaX, $deltaZ, $source->getKnockBack());
		}

		$pk = new EntityEventPacket();
		$pk->eid = $this->getId();
		$pk->event = $this->getHealth() <= 0 ? EntityEventPacket::DEATH_ANIMATION : EntityEventPacket::HURT_ANIMATION; //Ouch!
		Server::broadcastPacket($this->hasSpawned, $pk);
	}

	public function knockBack(Entity $attacker, $damage, $x, $z, $base = 0.4){
		$f = sqrt($x ** 2 + $z ** 2);

		$motion = new Vector3($this->motionX, $this->motionY, $this->motionZ);

		$motion->x = $motion->x >> 1;
		$motion->y = $motion->y >> 1;
		$motion->z = $motion->z >> 1;
		$motion->x += ($f != 0) ? ($x / $f) * $base : 0;
		$motion->y += $base;
		$motion->z += ($f != 0) ? ($z / $f) * $base : 0;

		if($motion->y > $base){
			$motion->y = $base;
		}

		$this->setMotion($motion);
	}

	public function kill(){
		if($this->dead){
			return;
		}
		parent::kill();
		$this->server->getPluginManager()->callEvent($ev = new EntityDeathEvent($this, $this->getDrops()));
		foreach($ev->getDrops() as $item){
			$this->getLevel()->dropItem($this, $item);
		}
	}

	public function entityBaseTick($tickDiff = 1) {
		if ($this->dead === true) {
			++$this->deadTicks;
			if ($this->deadTicks >= 10) {
				$this->despawnFromAll();
				if (!($this instanceof Player)) {
					$this->close();
				}
			}
			return $this->deadTicks < 10;
		}

		$hasUpdate = parent::entityBaseTick($tickDiff);

		if ($this->isInsideOfSolid()) {
			$hasUpdate = true;
			$ev = new EntityDamageEvent($this, EntityDamageEvent::CAUSE_SUFFOCATION, 1);
			$this->attack($ev->getFinalDamage(), $ev);
		}
		if (!$this->hasEffect(Effect::WATER_BREATHING) && $this->isInsideOfWater()) {
			if ($this instanceof WaterAnimal) {
				$this->dataProperties[self::DATA_AIR] = [self::DATA_TYPE_SHORT, 300];
			} else {
				$hasUpdate = true;
				$airTicks = $this->getDataProperty(self::DATA_AIR) - $tickDiff;
				if ($airTicks <= -20) {
					$airTicks = 0;
					$ev = new EntityDamageEvent($this, EntityDamageEvent::CAUSE_DROWNING, 2);
					$this->attack($ev->getFinalDamage(), $ev);
				}
				$this->setAirTick($airTicks);
				if ($this instanceof Player) {
					$this->setDataFlag(self::DATA_FLAGS, self::DATA_FLAG_NOT_IN_WATER, false, self::DATA_TYPE_LONG, false);
					$this->sendSelfData();
				}
			}
		} else {
			if ($this instanceof WaterAnimal) {
				$hasUpdate = true;
				$airTicks = $this->getDataProperty(self::DATA_AIR) - $tickDiff;
				if ($airTicks <= -20) {
					$airTicks = 0;

					$ev = new EntityDamageEvent($this, EntityDamageEvent::CAUSE_SUFFOCATION, 2);
					$this->attack($ev->getFinalDamage(), $ev);
				}
				$this->dataProperties[self::DATA_AIR] = [self::DATA_TYPE_SHORT, $airTicks];
			} else {
				if ($this->getDataProperty(self::DATA_AIR) != 300) {
					$this->setAirTick(300);
					if (($this instanceof Player)) {
						$this->setDataFlag(self::DATA_FLAGS, self::DATA_FLAG_NOT_IN_WATER, true, self::DATA_TYPE_LONG, false);
						$this->sendSelfData();
					}
				}
			}
		}

		if ($this->attackTime > 0) {
			$this->attackTime -= $tickDiff;
		}
		return $hasUpdate;
	}

	/**
	 * @return ItemItem[]
	 */
	public function getDrops(){
		return [];
	}

	/**
	 * @param int   $maxDistance
	 * @param int   $maxLength
	 * @param array $transparent
	 *
	 * @return Block[]
	 */
	public function getLineOfSight($maxDistance, $maxLength = 0, array $transparent = []){
		if ($maxDistance > 120) {
			$maxDistance = 120;
		}
		if (count($transparent) === 0) {
			$transparent = null;
		}
		$blocks = [];
		$nextIndex = 0;

		$itr = new BlockIterator($this->level, $this->getPosition(), $this->getDirectionVector(), $this->getEyeHeight(), $maxDistance);
		while ($itr->valid()) {
			$itr->next();
			$block = $itr->current();
			$blocks[$nextIndex++] = $block;

			if ($maxLength !== 0 && count($blocks) > $maxLength) {
				array_shift($blocks);
				--$nextIndex;
			}

			$id = $block->getId();

			if ($transparent === null) {
				if ($id !== 0) {
					break;
				}
			} else {
				if (!isset($transparent[$id])) {
					break;
				}
			}
		}

		return $blocks;
	}

	/**
	 * @param int   $maxDistance
	 * @param array $transparent
	 *
	 * @return Block
	 */
	public function getTargetBlock($maxDistance, array $transparent = []){
		try{
			$blocks = $this->getLineOfSight($maxDistance, 1, $transparent);
			if (isset($blocks[0]) && $blocks[0] instanceof Block) {
				return $blocks[0];
			}
		}catch (\ArrayOutOfBoundsException $e){

		}

		return null;
	}
}
