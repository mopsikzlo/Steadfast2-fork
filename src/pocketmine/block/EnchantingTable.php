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

use pocketmine\inventory\EnchantInventory;
use pocketmine\item\Item;
use pocketmine\item\Tool;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Player;
use pocketmine\tile\Tile;

class EnchantingTable extends Transparent {

	public $id = self::ENCHANTING_TABLE;

	public function __construct() {
		
	}

	public function place(Item $item, Block $block, Block $target, $face, $fx, $fy, $fz, Player $player = null) {
		$this->getLevel()->setBlock($block, $this, true, true);
		$nbt = new Compound("", [
			new StringTag("id", Tile::ENCHANT_TABLE),
			new IntTag("x", $this->x),
			new IntTag("y", $this->y),
			new IntTag("z", $this->z)
		]);

		if ($item->hasCustomName()) {
			$nbt->CustomName = new StringTag("CustomName", $item->getCustomName());
		}

		if ($item->hasCustomBlockData()) {
			foreach ($item->getCustomBlockData() as $key => $v) {
				$nbt->{$key} = $v;
			}
		}

		Tile::createTile(Tile::ENCHANT_TABLE, $this->getLevel()->getChunk($this->x >> 4, $this->z >> 4), $nbt);

		return true;
	}

	public function canBeActivated() {
		return true;
	}

	public function getHardness() {
		return 5;
	}

	public function getResistance() {
		return 6000;
	}

	public function getName() {
		return "Enchanting Table";
	}

	public function getToolType() {
		return Tool::TYPE_PICKAXE;
	}

	public function onActivate(Item $item, Player $player = null) {
		if ($player instanceof Player) {
			if ($player->isCreative()) {
				return true;
			}
			$tile = $this->getLevel()->getTile($this);
			$enchantTable = null;
			if ($tile instanceof EnchantTable) {
				$enchantTable = $tile;
			} else {
				$this->getLevel()->setBlock($this, $this, true, true);
				$nbt = new Compound("", [
					new StringTag("id", Tile::ENCHANT_TABLE),
					new IntTag("x", $this->x),
					new IntTag("y", $this->y),
					new IntTag("z", $this->z)
				]);
				if ($item->hasCustomName()) {
					$nbt->CustomName = new StringTag("CustomName", $item->getCustomName());
				}
				if ($item->hasCustomBlockData()) {
					foreach ($item->getCustomBlockData() as $key => $v) {
						$nbt->{$key} = $v;
					}
				}
				/** @var EnchantTable $enchantTable */
				$enchantTable = Tile::createTile(Tile::ENCHANT_TABLE, $this->getLevel()->getChunk($this->x >> 4, $this->z >> 4), $nbt);
			}
			$player->craftingType = Player::CRAFTING_ENCHANT;
			$player->addWindow(new EnchantInventory($this, $player));
		}
		return true;
	}

	public function getDrops(Item $item) {
		if ($item->isPickaxe() >= 1) {
			return [
					[$this->id, 0, 1],
			];
		} else {
			return [];
		}
	}

}
