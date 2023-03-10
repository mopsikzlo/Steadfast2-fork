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

use pocketmine\inventory\InventoryHolder;
use pocketmine\inventory\PlayerInventory;
use pocketmine\item\Item as ItemItem;
use pocketmine\inventory\EnderChestInventory;
use pocketmine\utils\UUID;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\Enum;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\IntTag;

use pocketmine\network\protocol\AddPlayerPacket;
use pocketmine\network\protocol\RemoveEntityPacket;
use pocketmine\Player;
use pocketmine\level\Level;

class Human extends Creature implements ProjectileSource, InventoryHolder{

	protected $nameTag = "TESTIFICATE";
	/** @var PlayerInventory */
	protected $inventory;

	/** @var UUID */
	protected $uuid;
	protected $rawUUID;
	public $enderChestInventory; //deleted

	public $width = 0.58;
	public $length = 0.58;
	public $height = 1.8;
	public $eyeHeight = 1.62;

	protected $skin;
	protected $skinName = 'Standard_Custom';
	protected $skinGeometryName = "geometry.humanoid.custom";
	protected $skinGeometryData = "";
	protected $capeData = "";

	/**
	 * @return string
	 */
    public function getSkin(){
        return $this->skin;
    }
    
	/**
	 * @return string
	 */
	public function getSkinData(){
		return $this->skin;
	}

	/**
	 * @return string
	 */
	public function getSkinName(){
		return $this->skinName;
	}
	
	/**
	 * @return string
	 */
    public function getSkinId(){
        return $this->skinName;
    }
	
	/**
	 * @return string
	 */
	public function getSkinGeometryName(){
		return $this->skinGeometryName;
	}
	
	/**
	 * @return string
	 */
	public function getSkinGeometryData(){
		return $this->skinGeometryData;
	}
	
	/**
	 * @return string
	 */
	public function getCapeData(){
		return $this->capeData;
	}

	/**
	 * @return UUID|null
	 */
	public function getUniqueId(){
		return $this->uuid;
	}

	/**
	 * @return string
	 */
	public function getRawUniqueId(){
		return $this->rawUUID;
	}

	/**
	 * @param string $str
	 * @param bool $skinName
	 * @param string $skinGeometryName
	 * @param string $skinGeometryData
	 * @param string $capeData
	 * @param bool $premium
	 * @param bool $check
	 *
	 * @return bool
	 */
	public function setSkin($str, $skinName, $skinGeometryName = "", $skinGeometryData = "", $capeData = "", $premium = false, $check = false){
		static $allowedSkinSize = [
			8192, // argb 64x32
			16384, // argb 64x64
			32768, // argb 128x64
			65536, // argb 128x128
		];

		if ($check && !in_array(strlen($str), $allowedSkinSize)) {
		    return false;
		}
		
		$this->skin = $str;
		
		if (is_string($skinName)) {
			$this->skinName = $skinName;
		}
		
		if (is_string($skinGeometryName)) {
			$this->skinGeometryName = $skinGeometryName;
		}
		
		if (is_string($skinGeometryData)) {
			$this->skinGeometryData = $skinGeometryData;
		}
		
		if (is_string($capeData)) {
			$this->capeData = $capeData;
		}

		return true;
	}


	/**
	 * @return EnderChestInventory
	 */
	public function getEnderChestInventory(){
		return $this->enderChestInventory;
	}

	/**
	 * @return PlayerInventory
	 */
	public function getInventory(){
		return $this->inventory;
	}

	protected function initEntity(){
        $this->enderChestInventory = new EnderChestInventory($this, ($this->namedtag->EnderChestInventory ?? null));
		$this->setDataFlag(self::DATA_PLAYER_FLAGS, self::DATA_PLAYER_FLAG_SLEEP, false, self::DATA_TYPE_BYTE);
		$this->setDataProperty(self::DATA_PLAYER_BED_POSITION, self::DATA_TYPE_POS, [0, 0, 0]);
		
		if(!($this instanceof Player)){
			$this->inventory = new PlayerInventory($this);
			if(isset($this->namedtag->NameTag)){
				$this->setNameTag($this->namedtag["NameTag"]);
			}

			if(isset($this->namedtag->Skin) and $this->namedtag->Skin instanceof Compound){
				$this->setSkin($this->namedtag->Skin["Data"], $this->namedtag->Skin["Name"], isset($this->namedtag->Skin["GeometryName"]) ? $this->namedtag->Skin["GeometryName"] : "", isset($this->namedtag->Skin["GeometryData"]) ? $this->namedtag->Skin["GeometryData"] : "", isset($this->namedtag->Skin["CapeData"]) ? $this->namedtag->Skin["CapeData"] : "");
			}

			$this->uuid = UUID::fromData($this->getId(), $this->getSkinData(), $this->getNameTag());
		}

		if(isset($this->namedtag->Inventory) and $this->namedtag->Inventory instanceof Enum){
			foreach($this->namedtag->Inventory as $item){
				if($item["Slot"] >= 0 and $item["Slot"] < 9){ //Hotbar
					$this->inventory->setHotbarSlotIndex($item["Slot"], isset($item["TrueSlot"]) ? $item["TrueSlot"] : -1);
				}elseif($item["Slot"] >= 100 and $item["Slot"] < 105){ //Armor
					$this->inventory->setItem($this->inventory->getSize() + $item["Slot"] - 100, NBT::getItemHelper($item));
				}else{
					$this->inventory->setItem($item["Slot"] - 9, NBT::getItemHelper($item));
				}
			}
		}

		parent::initEntity();
	}

	public function getName(){
		return $this->getNameTag();
	}

	public function getDrops(){
		$drops = [];
		if($this->inventory !== null){
			foreach($this->inventory->getContents() as $item){
				$drops[] = $item;
			}
		}

		return $drops;
	}

	public function saveNBT() {
		parent::saveNBT();
		$this->namedtag->Inventory = new Enum("Inventory", []);
		$this->namedtag->Inventory->setTagType(NBT::TAG_Compound);
		if ($this->inventory !== null) {
			for ($slot = 0; $slot < 9; ++$slot) {
				$hotbarSlot = $this->inventory->getHotbarSlotIndex($slot);
				if ($hotbarSlot !== -1) {
					$item = $this->inventory->getItem($hotbarSlot);
					if ($item->getId() !== ItemItem::AIR && $item->getCount() > 0) {
						$this->namedtag->Inventory[$slot] = NBT::putItemHelper($item, $slot);
						$this->namedtag->Inventory[$slot]->TrueSlot = new ByteTag("TrueSlot", $hotbarSlot);
						continue;
					}
				}
				$this->namedtag->Inventory[$slot] = NBT::putItemHelper(ItemItem::get(ItemItem::AIR), $slot);
				$this->namedtag->Inventory[$slot]->TrueSlot = new ByteTag("TrueSlot", -1);
			}

			//Normal inventory
			$slotCount = Player::SURVIVAL_SLOTS + 9;
			for($slot = 9; $slot < $slotCount; ++$slot){
				$item = $this->inventory->getItem($slot - 9);
				$this->namedtag->Inventory[$slot] = NBT::putItemHelper($item, $slot);
			}
			
			$this->namedtag->XpLevel = new IntTag("XP", $this->getExperienceLevel());

			//Armor and offhand
			for($slot = 100; $slot < 105; ++$slot){
				$item = $this->inventory->getItem($this->inventory->getSize() + $slot - 100);
				if($item instanceof ItemItem and $item->getId() !== ItemItem::AIR){
					$this->namedtag->Inventory[$slot] = NBT::putItemHelper($item, $slot);
				}
			}
			//EnderChest
	    	$this->namedtag->EnderChestInventory = new Enum("EnderChestInventory", []);
	    	$this->namedtag->Inventory->setTagType(NBT::TAG_Compound);
	    	if($this->enderChestInventory !== null){
		    	for($slot = 0; $slot < $this->enderChestInventory->getSize(); $slot++){
			    	if(($item = $this->enderChestInventory->getItem($slot)) instanceof ItemItem){
			    		$this->namedtag->EnderChestInventory[$slot] = NBT::putItemHelper($item, $slot);
			    	}
		    	}
	    	}
		}
	}

	public function spawnTo(Player $player){
		if($player !== $this and !isset($this->hasSpawned[$player->getId()])  and isset($player->usedChunks[Level::chunkHash($this->chunk->getX(), $this->chunk->getZ())])){
			$this->hasSpawned[$player->getId()] = $player;

			if(!($this instanceof Player)) {
				$this->server->updatePlayerListData($this->getUniqueId(), $this->getId(), $this->getName(), $this->skinName, $this->skin, $this->skinGeometryName, $this->skinGeometryData, $this->capeData, "", $player->getDeviceOS(), [], [$player], true);
			}

			$pk = new AddPlayerPacket();
			$pk->uuid = $this->getUniqueId();
			$pk->username = $this->getName();
			$pk->eid = $this->getId();
			$pk->x = $this->x;
			$pk->y = $this->y;
			$pk->z = $this->z;
			$pk->speedX = $this->motionX;
			$pk->speedY = $this->motionY;
			$pk->speedZ = $this->motionZ;
			$pk->yaw = $this->yaw;
			$pk->pitch = $this->pitch;
			$pk->item = $this->getInventory()->getItemInHand();
			$pk->metadata = $this->dataProperties;
			if ($this instanceof Player) {
				$pk->buildPlatform = $this->getDeviceOS();
			}
			$player->dataPacket($pk);

			$this->inventory->sendArmorContents($player);
			$this->level->addPlayerHandItem($this, $player);

			if(!($this instanceof Player)) {
				$this->server->removePlayerListData($this->getUniqueId(), [$player]);
			}
		}
	}

	public function despawnFrom(Player $player){
		if(isset($this->hasSpawned[$player->getId()])){
			$pk = new RemoveEntityPacket();
			$pk->eid = $this->getId();
			$player->dataPacket($pk);
			unset($this->hasSpawned[$player->getId()]);
		}
	}

	public function close(){
		if(!$this->closed){
			if(!($this instanceof Player) or $this->loggedIn){
				foreach($this->inventory->getViewers() as $viewer){
					$viewer->removeWindow($this->inventory);
				}
			}
			parent::close();
		}
	}
	
	public function isNeedSaveOnChunkUnload() {
		return false;
	}
}