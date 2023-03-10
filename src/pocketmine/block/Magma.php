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

namespace pocketmine\block;

use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByBlockEvent;
use pocketmine\item\Item;
use pocketmine\item\Tool;

class Magma extends Solid {

	public $id = self::MAGMA;

	public function __construct() {
		
	}

	public function getHardness() {
		return 0.5;
	}

	public function getName() {
		return "Magma";
	}

	public function getToolType() {
		return Tool::TYPE_PICKAXE;
	}
	
	public function getDrops(Item $item) {
		return [];
	}

	public function hasEntityCollision(){
		return true;
	}

	public function onEntityCollide(Entity $entity) {
		$ev = new EntityDamageByBlockEvent($this, $entity, EntityDamageByBlockEvent::CAUSE_FIRE, 1);
		$entity->attack($ev->getFinalDamage(), $ev);
	}

}
