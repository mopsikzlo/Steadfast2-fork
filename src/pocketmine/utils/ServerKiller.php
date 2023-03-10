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

namespace pocketmine\utils;

use pocketmine\Thread;

class ServerKiller extends Thread {

	public $time;

	public function __construct($time = 15) {
		$this->time = $time;
	}

	public function start(int $options = PTHREADS_INHERIT_NONE) {
		parent::start($options);
	}

	public function run() {
		$this->registerClassLoader();
		$start = time();
		$this->synchronized(function() {
			$this->wait($this->time * 1000000);
		});
		if (time() - $start >= $this->time) {
			echo "\nTook too long to stop, server was killed forcefully!\n";
			$uname = php_uname("s");
			if (stripos($uname, "Win") !== false or $uname === "Msys") {
				exec("taskkill.exe /F /PID " . ((int) getmypid()) . " > NUL");
			} else {
				exec("kill -9 " . ((int) $pid) . " > /dev/null 2>&1");
			}
		}
	}

	public function getThreadName() {
		return "Server Killer";
	}

}
