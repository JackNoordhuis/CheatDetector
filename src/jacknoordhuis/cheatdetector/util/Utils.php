<?php

/**
 * Utils.php – CheatDetector
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

namespace jacknoordhuis\cheatdetector\util;

use pocketmine\Player;
use pocketmine\utils\TextFormat;

abstract class Utils {

	/** @var Player[] */
	private static $staff = [];

	public static function addStaff(Player $player) {
		static::$staff[spl_object_id($player)] = $player;
	}

	public static function broadcastStaffMessage(string $message) {
		foreach(static::$staff as $staff) {
			$staff->sendMessage(TextFormat::colorize($message, '&'));
		}
	}

	public static function removeStaff(Player $player) {
		unset(static::$staff[spl_object_id($player)]);
	}

}