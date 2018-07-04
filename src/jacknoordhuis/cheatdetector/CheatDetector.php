<?php

/**
 * CheatDetector.php – CheatDetector
 *
 * Copyright (C) 2018 Jack Noordhuis
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author Jack
 *
 */

declare(strict_types=1);

namespace jacknoordhuis\cheatdetector;

use jacknoordhuis\cheatdetector\entity\KillAuraDetector;
use pocketmine\entity\Entity;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class CheatDetector extends PluginBase {

	/** @var EventListener|null */
	private $listener;

	/** @var DetectionSession[] */
	private $sessions = [];

	/** @var string[] */
	public $staff = [];

	public function onLoad() {
		Entity::registerEntity(KillAuraDetector::class, true);
	}

	public function onEnable() {
		$this->saveResource("Settings.yml");
		$config = new Config($this->getDataFolder() . "Settings.yml", Config::YAML);

		$this->staff = array_map('strtolower', $config->get("staff", []));

		$this->listener = new EventListener($this);
	}

	public function openCheatDetectionSession(Player $player, int $deviceOs) : void {
		if(!$this->hasCheatDetectionSession($player)) {
			$this->sessions[spl_object_id($player)] = new DetectionSession($player, $this, $deviceOs);
		}
	}

	public function hasCheatDetectionSession(Player $player) : bool {
		return isset($this->sessions[$id = spl_object_id($player)]) and $this->sessions[$id] instanceof DetectionSession;
	}

	public function getCheatDetectionSession(Player $player) : ?DetectionSession {
		return $this->sessions[spl_object_id($player)] ?? null;
	}

	public function closeCheatDetectionSession(Player $player) : void {
		if($this->hasCheatDetectionSession($player)) {
			$this->getCheatDetectionSession($player)->destroy();
		}
	}

}