<?php

/*
 *
 *  _____   _____   __   _   _   _____  __    __  _____
 * /  ___| | ____| |  \ | | | | /  ___/ \ \  / / /  ___/
 * | |     | |__   |   \| | | | | |___   \ \/ /  | |___
 * | |  _  |  __|  | |\   | | | \___  \   \  /   \___  \
 * | |_| | | |___  | | \  | | |  ___| |   / /     ___| |
 * \_____/ |_____| |_|  \_| |_| /_____/  /_/     /_____/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author iTX Technologies
 * @link https://itxtech.org
 *
 */

namespace pocketmine\event\level;

use pocketmine\event\Cancellable;
use pocketmine\level\Level;
use pocketmine\level\weather\Weather;

class WeatherChangeEvent extends LevelEvent implements Cancellable {
	public static $handlerList = null;

	private $weather;
	private $duration;

	/**
	 * WeatherChangeEvent constructor.
	 *
	 * @param Level $level
	 * @param int   $weather
	 * @param int   $duration
	 */
	public function __construct(Level $level, $weather, $duration){
		parent::__construct($level);
		$this->weather = $weather;
		$this->duration = $duration;
	}

	/**
	 * @return int
	 */
	public function getWeather(){
		return $this->weather;
	}

	/**
	 * @param int $weather
	 */
	public function setWeather($weather = Weather::SUNNY){
		$this->weather = $weather;
	}

	/**
	 * @return int
	 */
	public function getDuration(){
		return $this->duration;
	}

	/**
	 * @param int $duration
	 */
	public function setDuration($duration){
		$this->duration = $duration;
	}

}