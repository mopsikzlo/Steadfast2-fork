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

use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\tile\Tile;
use pocketmine\level\{Level, Explosion, Position};
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;

class Bed extends Transparent{
	
	public $id = self::BED_BLOCK;

	public function __construct($meta = 0){
		$this->meta = $meta;
	}

	public function canBeActivated(){
		return true;
	}

	public function getHardness(){
		return 0.2;
	}

	public function getName(){
		return "Bed Block";
	}

	public function recalculateBoundingBox(){
		return new AxisAlignedBB(
			$this->x,
			$this->y,
			$this->z,
			$this->x + 1,
			$this->y + 0.5625,
			$this->z + 1
		);
	}

	public function onActivate(Item $item, Player $player = null){
		$dimension = $this->getLevel()->getDimension();
		if($dimension == Level::DIMENSION_NETHER or $dimension == Level::DIMENSION_END){
			$explosion = new Explosion(new Position($this->getX(), $this->getY(), $this->getZ(), $this->getLevel()), 6);
			$explosion->explodeA();
			$explosion->explodeB();
			return true;
		}
		if($player instanceof Player and !$this->getLevel()->isNight()){
			$player->sendMessage(TextFormat::GRAY . "Вы можете спать только ночью.");
			return true;
		}

		$blockNorth = $this->getSide(2); //Gets the blocks around them
		$blockSouth = $this->getSide(3);
		$blockEast = $this->getSide(5);
		$blockWest = $this->getSide(4);
		if(($this->meta & 0x08) === 0x08){ //This is the Top part of bed
			$b = $this;
		}else{ //Bottom Part of Bed
			if($blockNorth->getId() === $this->id and ($blockNorth->meta & 0x08) === 0x08){
				$b = $blockNorth;
			}elseif($blockSouth->getId() === $this->id and ($blockSouth->meta & 0x08) === 0x08){
				$b = $blockSouth;
			}elseif($blockEast->getId() === $this->id and ($blockEast->meta & 0x08) === 0x08){
				$b = $blockEast;
			}elseif($blockWest->getId() === $this->id and ($blockWest->meta & 0x08) === 0x08){
				$b = $blockWest;
			}else{
				if($player instanceof Player){
					$player->sendMessage(TextFormat::GRAY . "Эта кровать не полная.");
				}

				return true;
			}
		}

		if($player instanceof Player and $player->sleepOn($b) === false){
			$player->sendMessage(TextFormat::GRAY . "Эта кровать занята.");
		}

		return true;
	}

	public function place(Item $item, Block $block, Block $target, $face, $fx, $fy, $fz, Player $player = null){
		$down = $this->getSide(0);
		if($down->isTransparent() === false){
			$faces = [
				0 => 3,
				1 => 4,
				2 => 2,
				3 => 5,
			];
			$d = $player instanceof Player ? $player->getDirection() : 0;
			$next = $this->getSide($faces[(($d + 3) % 4)]);
			$downNext = $this->getSide(0);
			if($next->canBeReplaced() === true and $downNext->isTransparent() === false){
				$meta = (($d + 3) % 4) & 0x03;
				$this->getLevel()->setBlock($block, Block::get($this->id, $meta), true, true);
				$this->getLevel()->setBlock($next, Block::get($this->id, $meta | 0x08), true, true);
				
				$nbt = new Compound("", [
					new StringTag("id", Tile::BED),
					new IntTag("x", (int) $this->x),
					new IntTag("y", (int) $this->y),
					new IntTag("z", (int) $this->z),
					new ByteTag("color", (int) $item->getDamage()),
					new ByteTag("isMovable", (int) 1)
				]);
				Tile::createTile("Bed", $this->getLevel()->getChunk($this->x >> 4, $this->z >> 4), $nbt);
				
				$nbtNext = new Compound("", [
					new StringTag("id", Tile::BED),
					new IntTag("x", (int) $next->x),
					new IntTag("y", (int) $next->y),
					new IntTag("z", (int) $next->z),
					new ByteTag("color", (int) $item->getDamage()),
					new ByteTag("isMovable", (int) 1)
				]);
				Tile::createTile("Bed", $this->getLevel()->getChunk($next->x >> 4, $next->z >> 4), $nbtNext);

				return true;
			}
		}

		return false;
	}

	public function onBreak(Item $item){
		$blockNorth = $this->getSide(2); //Gets the blocks around them
		$blockSouth = $this->getSide(3);
		$blockEast = $this->getSide(5);
		$blockWest = $this->getSide(4);

		if(($this->meta & 0x08) === 0x08){ //This is the Top part of bed
			if($blockNorth->getId() === $this->id and $blockNorth->meta !== 0x08){ //Checks if the block ID and meta are right
				$this->getLevel()->setBlock($blockNorth, new Air(), true, true);
			}elseif($blockSouth->getId() === $this->id and $blockSouth->meta !== 0x08){
				$this->getLevel()->setBlock($blockSouth, new Air(), true, true);
			}elseif($blockEast->getId() === $this->id and $blockEast->meta !== 0x08){
				$this->getLevel()->setBlock($blockEast, new Air(), true, true);
			}elseif($blockWest->getId() === $this->id and $blockWest->meta !== 0x08){
				$this->getLevel()->setBlock($blockWest, new Air(), true, true);
			}
		}else{ //Bottom Part of Bed
			if($blockNorth->getId() === $this->id and ($blockNorth->meta & 0x08) === 0x08){
				$this->getLevel()->setBlock($blockNorth, new Air(), true, true);
			}elseif($blockSouth->getId() === $this->id and ($blockSouth->meta & 0x08) === 0x08){
				$this->getLevel()->setBlock($blockSouth, new Air(), true, true);
			}elseif($blockEast->getId() === $this->id and ($blockEast->meta & 0x08) === 0x08){
				$this->getLevel()->setBlock($blockEast, new Air(), true, true);
			}elseif($blockWest->getId() === $this->id and ($blockWest->meta & 0x08) === 0x08){
				$this->getLevel()->setBlock($blockWest, new Air(), true, true);
			}
		}
		$this->getLevel()->setBlock($this, new Air(), true, true);

		return true;
	}

	public function getDrops(Item $item){
		return [
			[Item::BED, 0, 1],
		];
	}

}