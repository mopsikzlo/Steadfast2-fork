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
use pocketmine\item\Item;
use pocketmine\item\Tool;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\Enum;
use pocketmine\Player;

class Pumpkin extends Solid{

	const SLOT_NUMBER = 0;

	public $id = self::PUMPKIN;

	public function __construct(){

	}

	public function getHardness(){
		return 1;
	}

	public function getToolType(){
		return Tool::TYPE_AXE;
	}

	public function getName(){
		return "Pumpkin";
	}

	public function place(Item $item, Block $block, Block $target, $face, $fx, $fy, $fz, Player $player = null){
		if($player instanceof Player){
			$this->meta = ((int) $player->getDirection() + 5) % 4;
		}
		$this->getLevel()->setBlock($block, $this, true, true);
		if($player != null){
			$level = $this->getLevel();
			if($player->getServer()->getSnowGolem()){
				$block0 = $level->getBlock($block->add(0, -1, 0));
				$block1 = $level->getBlock($block->add(0, -2, 0));
				if($block0->getId() == Item::SNOW_BLOCK and $block1->getId() == Item::SNOW_BLOCK){
					$level->setBlock($block, new Air());
					$level->setBlock($block0, new Air());
					$level->setBlock($block1, new Air());
					$golem = Entity::createEntity("SnowGolem", $player->getLevel()->getChunk($this->x >> 4, $this->z >> 4), new Compound("", [
						"Pos" => new Enum("Pos", [
							new DoubleTag("", $fx),
							new DoubleTag("", $fy - 2),
							new DoubleTag("", $fz)
						]),
						"Motion" => new Enum("Motion", [
							new DoubleTag("", 0),
							new DoubleTag("", 0),
							new DoubleTag("", 0)
						]),
						"Rotation" => new Enum("Rotation", [
							new FloatTag("", 0),
							new FloatTag("", 0)
						]),
					]));
					$golem->spawnToAll();
				}
			}
			if($player->getServer()->getIronGolem()){
				$block0 = $level->getBlock($block->add(0, -1, 0));
				$block1 = $level->getBlock($block->add(0, -2, 0));
				$block2 = $level->getBlock($block->add(-1, -1, 0));
				$block3 = $level->getBlock($block->add(1, -1, 0));
				$block4 = $level->getBlock($block->add(0, -1, -1));
				$block5 = $level->getBlock($block->add(0, -1, 1));
				if($block0->getId() == Item::IRON_BLOCK and $block1->getId() == Item::IRON_BLOCK){
					if($block2->getId() == Item::IRON_BLOCK and $block3->getId() == Item::IRON_BLOCK and $block4->getId() == Item::AIR and $block5->getId() == Item::AIR){
						$level->setBlock($block2, new Air());
						$level->setBlock($block3, new Air());
					}elseif($block4->getId() == Item::IRON_BLOCK and $block5->getId() == Item::IRON_BLOCK and $block2->getId() == Item::AIR and $block3->getId() == Item::AIR){
						$level->setBlock($block4, new Air());
						$level->setBlock($block5, new Air());
					}else return false;
					$level->setBlock($block, new Air());
					$level->setBlock($block0, new Air());
					$level->setBlock($block1, new Air());
					$golem = Entity::createEntity("IronGolem", $player->getLevel()->getChunk($this->x >> 4, $this->z >> 4), new Compound("", [
						"Pos" => new Enum("Pos", [
							new DoubleTag("", $fx),
							new DoubleTag("", $fy - 2),
							new DoubleTag("", $fz)
						]),
						"Motion" => new Enum("Motion", [
							new DoubleTag("", 0),
							new DoubleTag("", 0),
							new DoubleTag("", 0)
						]),
						"Rotation" => new Enum("Rotation", [
							new FloatTag("", 0),
							new FloatTag("", 0)
						]),
					]));
					$golem->spawnToAll();
				}
			}
		}
		return true;
	}

}