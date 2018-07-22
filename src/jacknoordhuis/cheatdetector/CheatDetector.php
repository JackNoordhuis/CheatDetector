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

class CheatDetector extends PluginBase {

	/** @var CheatDetector|null */
	private static $instance = null;

	/** @var EventListener|null */
	private $listener = null;

	/** @var DetectionSession[] */
	private $sessions = [];

	public function onLoad() {
		Entity::registerEntity(KillAuraDetector::class, true);
	}

	public function onEnable()/* : void /* TODO: uncomment this for next major version */ {
		static::$instance = $this;

		$this->sessions = []; // make sure the sessions array is empty in-case the plugin is reloaded
		foreach($this->getServer()->getOnlinePlayers() as $p) {
			$this->openCheatDetectionSession($p, -1);
		}

		$this->listener = new EventListener($this);
	}

	public function onDisable()/* : void /* TODO: uncomment this for next major version */ {
		foreach($this->sessions as $s) {
			$s->destroy();
		}

		$this->listener->destroy();

		unset($this->sessions, $this->listener);

		static::$instance = null;
	}

	public static function getInstance() : ?CheatDetector {
		return static::$instance;
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
			unset($this->sessions[spl_object_id($player)]);
		}
	}

}
