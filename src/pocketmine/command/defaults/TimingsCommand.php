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

namespace pocketmine\command\defaults;

use pocketmine\command\CommandSender;
use pocketmine\event\TimingsHandler;
use pocketmine\utils\TextFormat;

class TimingsCommand extends VanillaCommand{

	public static $timingStart = 0;

	public function __construct($name){
		parent::__construct(
			$name,
			"Records timings to see performance of the server.",
			"/timings <reset|report|on|off|paste>"
		);
		$this->setPermission("pocketmine.command.timings");
	}

	public function execute(CommandSender $sender, $currentAlias, array $args){
		if(!$this->testPermission($sender)){
			return true;
		}

		if(count($args) !== 1){
			$sender->sendMessage(TextFormat::RED . "Usage: " . $this->usageMessage);

			return true;
		}

		$mode = strtolower($args[0]);

		if($mode === "on"){
			$sender->getServer()->getPluginManager()->setUseTimings(true);
			TimingsHandler::reload();
			$sender->sendMessage("Enabled Timings & Reset");

			return true;
		}elseif($mode === "off"){
			$sender->getServer()->getPluginManager()->setUseTimings(false);
			$sender->sendMessage("Disabled Timings");
			return true;
		}

		if(!$sender->getServer()->getPluginManager()->useTimings()){
			$sender->sendMessage("Please enable timings by typing /timings on");

			return true;
		}

		$paste = $mode === "paste";

		if($mode === "reset"){
			TimingsHandler::reload();
			$sender->sendMessage("Timings reset");
		}elseif($mode === "merged" or $mode === "report" or $paste){

			$timings = "";
			if($paste){
				$fileTimings = fopen("php://temp", "r+b");
			}else{
				$index = 0;
				$timingFolder = $sender->getServer()->getDataPath() . "timings/";

				if(!file_exists($timingFolder)){
					mkdir($timingFolder, 0777);
				}
				$timings = $timingFolder . "timings.txt";
				while(file_exists($timings)){
					$timings = $timingFolder . "timings" . (++$index) . ".txt";
				}

				$fileTimings = fopen($timings, "a+b");
			}
			TimingsHandler::printTimings($fileTimings);

			if($paste){
				fseek($fileTimings, 0);
				$data = [
					"browser" => $sender->getServer()->getName() . " " . $sender->getServer()->getPocketMineVersion(),
					"data" => stream_get_contents($fileTimings)
				];

				$ch = curl_init("https://timings.pmmp.io?upload=true");
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
				curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
				curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
				curl_setopt($ch, CURLOPT_AUTOREFERER, false);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
				curl_setopt($ch, CURLOPT_HEADER, true);
				curl_setopt($ch, CURLOPT_HTTPHEADER, [
					"User-Agent: " . $this->getName() . " " . $sender->getServer()->getPocketMineVersion(),
					"Content-Type: application/x-www-form-urlencoded"
				]);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				try{
					$data = curl_exec($ch);
					if($data === false){
						throw new \Exception(curl_error($ch));
					}
				}catch(\Exception $e){
					$sender->getServer()->getLogger()->logException($e);
				}

				$data = substr($data, curl_getinfo($ch, CURLINFO_HEADER_SIZE));
				curl_close($ch);
				if(!is_array($response = json_decode($data, true)) or !isset($response["id"])){
					$sender->sendMessage("An error happened while pasting the report");

					return true;
				}


				$sender->sendMessage("You can read the results at http://timings.pmmp.io/?id=" . $response["id"]);
				fclose($fileTimings);
			}else{
				fclose($fileTimings);
				$sender->sendMessage("Timings written to " . $timings);
			}
		}

		return true;
	}
}
