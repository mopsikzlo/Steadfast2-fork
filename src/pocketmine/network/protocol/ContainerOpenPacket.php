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

namespace pocketmine\network\protocol;

class ContainerOpenPacket extends PEPacket{
	
	const NETWORK_ID = Info::CONTAINER_OPEN_PACKET;
	const PACKET_NAME = "CONTAINER_OPEN_PACKET";

	public $windowid;
	public $type;
	public $x;
	public $y;
	public $z;
	public $entityId = -1;

	public function decode($playerProtocol){}

	public function encode($playerProtocol) {
		$this->reset($playerProtocol);
		$this->putByte($this->windowid);
		$this->putByte($this->type);
		$this->putSignedVarInt($this->x);
		$this->putVarInt($this->y);
		$this->putSignedVarInt($this->z);
		$this->putSignedVarInt($this->entityId);
	}

}
