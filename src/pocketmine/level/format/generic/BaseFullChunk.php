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

namespace pocketmine\level\format\generic;

use pocketmine\entity\Entity;
use pocketmine\level\format\FullChunk;
use pocketmine\level\format\LevelProvider;
use pocketmine\nbt\tag\Compound;
use pocketmine\Player;
use pocketmine\tile\Tile;
use pocketmine\utils\Binary;
use pocketmine\block\Block;

abstract class BaseFullChunk implements FullChunk{

	/** @var Entity[] */
	protected $entities = [];

	/** @var Tile[] */
	protected $tiles = [];

	/** @var Tile[] */
	protected $tileList = [];

	/** @var string */
	protected $biomeIds;

	/** @var int[256] */
	protected $biomeColors;

	protected $blocks;

	protected $data;

	protected $skyLight;

	protected $blockLight;

	protected $heightMap;

	protected $NBTtiles;

	protected $NBTentities;

	protected $extraData = [];

	/** @var LevelProvider */
	protected $provider;

	protected $x;
	protected $z;

	protected $hasChanged = false;

	public $allowUnload = true;
	
	protected $freezedEntityCount = 0;


	public $incorrectHeightMap = false;
	/**
	 * @param LevelProvider $provider
	 * @param int           $x
	 * @param int           $z
	 * @param string        $blocks
	 * @param string        $data
	 * @param string        $skyLight
	 * @param string        $blockLight
	 * @param int[]         $biomeColors
	 * @param int[]         $heightMap
	 * @param Compound[]    $entities
	 * @param Compound[]    $tiles
	 */
	protected function __construct($provider, $x, $z, $blocks, $data, $skyLight, $blockLight, array $biomeColors = [], array $heightMap = [], array $entities = [], array $tiles = []){
		$this->provider = $provider;
		$this->x = (int) $x;
		$this->z = (int) $z;

		$this->blocks = $blocks;
		$this->data = $data;
		$this->skyLight = $skyLight;
		$this->blockLight = $blockLight;

		if(count($biomeColors) === 256){
			$this->biomeColors = $biomeColors;
		}else{
			$this->biomeColors = array_fill(0, 256, 0);
		}

		if(count($heightMap) === 256){
			$this->heightMap = $heightMap;
		}else{
			$this->heightMap = array_fill(0, 256, $provider::getMaxY() - 1);
			$this->incorrectHeightMap = true;
		}

		$this->NBTtiles = $tiles;
		$this->NBTentities = $entities;
	}

	public function initChunk(){
		if($this->getProvider() instanceof LevelProvider and $this->NBTentities !== null){
			$level = $this->getProvider()->getLevel();
            $level->timings->syncChunkLoadEntitiesTimer->startTiming();
			foreach($this->NBTentities as $nbt){
				if($nbt instanceof Compound){
					if(!isset($nbt->id)){
						$this->setChanged();
						continue;
					}

					if(($nbt["Pos"][0] >> 4) !== $this->x or ($nbt["Pos"][2] >> 4) !== $this->z){
						$this->setChanged();
						continue; //Fixes entities allocated in wrong chunks.
					}

					if(($entity = Entity::createEntity($nbt["id"], $this, $nbt)) instanceof Entity){
						$entity->spawnToAll();
					}else{
						$this->setChanged();
						continue;
					}
				}
			}
			$level->timings->syncChunkLoadEntitiesTimer->stopTiming();

			$level->timings->syncChunkLoadTileEntitiesTimer->startTiming();
			foreach($this->NBTtiles as $nbt){
				if($nbt instanceof Compound){
					if(!isset($nbt->id)){
						$this->setChanged();
						continue;
					}

					if(($nbt["x"] >> 4) !== $this->x or ($nbt["z"] >> 4) !== $this->z){
						$this->setChanged();
						continue; //Fixes tiles allocated in wrong chunks.
					}
					$tileName = $nbt["id"];
					// this part for pc maps
//					if (substr($nbt["id"], 0, 10) == 'minecraft:') {
//						$tileName = str_replace("_", "", ucwords(substr($tileName, 10), "_"));
//					}
					if(Tile::createTile($tileName, $this, $nbt) === null){
						$this->setChanged();
						continue;
					}
				}
			}

			$level->timings->syncChunkLoadTileEntitiesTimer->stopTiming();

			$this->NBTentities = null;
			$this->NBTtiles = null;
			$this->hasChanged = false;
		}
	}

	public function getX(){
		return $this->x;
	}

	public function getZ(){
		return $this->z;
	}

	public function setX($x){
		$this->x = $x;
	}

	public function setZ($z){
		$this->z = $z;
	}

	/**
	 * @return LevelProvider
	 *
	 * @deprecated
	 */
	public function getLevel(){
		return $this->getProvider();
	}

	/**
	 * @return LevelProvider
	 */
	public function getProvider(){
		return $this->provider;
	}

	public function setProvider(LevelProvider $provider){
		$this->provider = $provider;
	}

	public function getBiomeId($x, $z){
		return ($this->biomeColors[($z << 4) + $x] & 0xFF000000) >> 24;
	}

	public function setBiomeId($x, $z, $biomeId){
		$this->hasChanged = true;
		$this->biomeColors[($z << 4) + $x] = ($this->biomeColors[($z << 4) + $x] & 0xFFFFFF) | ($biomeId << 24);
	}

	public function getBiomeColor($x, $z){
		$color = $this->biomeColors[($z << 4) + $x] & 0xFFFFFF;

		return [$color >> 16, ($color >> 8) & 0xFF, $color & 0xFF];
	}

	public function setBiomeColor($x, $z, $R, $G, $B){
		$this->hasChanged = true;
		$this->biomeColors[($z << 4) + $x] = ($this->biomeColors[($z << 4) + $x] & 0xFF000000) | (($R & 0xFF) << 16) | (($G & 0xFF) << 8) | ($B & 0xFF);
	}

	public function getHeightMap($x, $z){
		return $this->heightMap[($z << 4) + $x];
	}

	public function setHeightMap($x, $z, $value){
		$this->heightMap[($z << 4) + $x] = $value;
	}

	public function getHighestBlockAt($x, $z){
		$column = $this->getBlockIdColumn($x, $z);
		$provider = $this->provider;
		for($y = $provider::getMaxY() - 1; $y >= 0; --$y){
			if($column[$y] !== "\x00"){
				return $y;
			}
		}

		return 0;
	}

	public function addEntity(Entity $entity){
		$this->entities[$entity->getId()] = $entity;
		if(!($entity instanceof Player)){
			$this->hasChanged = true;
			if ($entity->isCanFreezeChunk()) {
				$this->freezedEntityCount++;
			}
		}
	}

	public function removeEntity(Entity $entity){
		unset($this->entities[$entity->getId()]);
		if(!($entity instanceof Player)){
			$this->hasChanged = true;
			if ($entity->isCanFreezeChunk()) {
				$this->freezedEntityCount--;
			}
		}
	}

	public function addTile(Tile $tile){
		$this->tiles[$tile->getId()] = $tile;
		if(isset($this->tileList[$index = (($tile->z & 0x0f) << 12) | (($tile->x & 0x0f) << 8) | ($tile->y & 0xff)]) and $this->tileList[$index] !== $tile){
			$this->tileList[$index]->close();
		}
		$this->tileList[$index] = $tile;
		$this->hasChanged = true;
	}

	public function removeTile(Tile $tile){
		unset($this->tiles[$tile->getId()]);
		unset($this->tileList[(($tile->z & 0x0f) << 12) | (($tile->x & 0x0f) << 8) | ($tile->y & 0xff)]);
		$this->hasChanged = true;
	}

	public function getEntities(){
		return $this->entities;
	}

	public function getTiles(){
		return $this->tiles;
	}

	public function getBlockExtraDataArray(){
		return $this->extraData;
	}

	public function getTile($x, $y, $z){
		$index = ($z << 12) | ($x << 8) | $y;
		return isset($this->tileList[$index]) ? $this->tileList[$index] : null;
	}

	public function isLoaded(){
		return $this->getProvider() === null ? false : $this->getProvider()->isChunkLoaded($this->getX(), $this->getZ());
	}

	public function load($generate = true){
		return $this->getProvider() === null ? false : $this->getProvider()->getChunk($this->getX(), $this->getZ(), true) instanceof FullChunk;
	}

	public function unload($save = true, $safe = true){
		$level = $this->getProvider();
		if($level === null){
			return true;
		}
		if($save === true and $this->hasChanged){
			$level->saveChunk($this->getX(), $this->getZ());
		}
		if($safe === true){
			foreach($this->getEntities() as $entity){
				if($entity instanceof Player){
					return false;
				}
			}
		}

		foreach($this->getEntities() as $entity){
			if($entity instanceof Player){
				continue;
			}
			$entity->close();
		}
		foreach($this->getTiles() as $tile){
			$tile->close();
		}
		return true;
	}

	public function getBlockIdArray(){
		return $this->blocks;
	}

	public function getBlockDataArray(){
		return $this->data;
	}

	public function getBlockSkyLightArray(){
		return $this->skyLight;
	}

	public function getBlockLightArray(){
		return $this->blockLight;
	}

	public function getBiomeIdArray(){
		return $this->biomeIds;
	}

	public function getBiomeColorArray(){
		return $this->biomeColors;
	}

	public function getHeightMapArray(){
		return $this->heightMap;
	}
	
	public function hasChanged(){
		return $this->hasChanged;
	}

	public function setChanged($changed = true){
		$this->hasChanged = (bool) $changed;
	}

	public static function fromFastBinary($data, LevelProvider $provider = null){
		return static::fromBinary($data, $provider);
	}

	public function toFastBinary(){
		return $this->toBinary();
	}
	
	public function recalculateHeightMap(){
		for($z = 0; $z < 16; ++$z){
			for($x = 0; $x < 16; ++$x){
				$this->setHeightMap($x, $z, $this->getHighestBlockAt($x, $z, false));
			}
		}
	}
	
	public function populateSkyLight(){
		for($z = 0; $z < 16; ++$z){
			for($x = 0; $x < 16; ++$x){
				$top = $this->getHeightMap($x, $z);
				$provider = $this->provider;
				for($y = $provider::getMaxY() - 1; $y > $top; --$y){
					$this->setBlockSkyLight($x, $y, $z, 15);
				}

				for($y = $top; $y >= 0; --$y){
					if(Block::$solid[$this->getBlockId($x, $y, $z)]){
						break;
					}

					$this->setBlockSkyLight($x, $y, $z, 15);
				}

				$this->setHeightMap($x, $z, $this->getHighestBlockAt($x, $z, false));
			}
		}
	}
	
	public function setLightPopulated($value = 1){

	}
	
	public function setBlockIdArray($arr){
		$this->blocks = $arr;
	}
	
	public function setBlockDataArray($arr){
		$this->data = $arr;
	}
	
	public function isAllowUnload() {
		return $this->freezedEntityCount <= 0 && $this->allowUnload;
	}

}
