<?php

/**
 * DetectionSession.php – CheatDetector
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
use jacknoordhuis\cheatdetector\util\Utils;
use pocketmine\block\Block;
use pocketmine\block\Liquid;
use pocketmine\entity\Entity;
use pocketmine\math\Math;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteArrayTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Player;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;

class DetectionSession {

	/**
	 * Array of block ids for the anti-fly to ignore
	 *
	 * @var int[]
	 */
	protected static $ignoredBlocks = [
		Block::STONE_SLAB,
		Block::WOODEN_SLAB,
		Block::STONE_SLAB2,
		Block::NETHER_BRICK_STAIRS,
		Block::DARK_OAK_STAIRS,
		Block::ACACIA_STAIRS,
		Block::JUNGLE_STAIRS,
		Block::BIRCH_STAIRS,
		Block::SPRUCE_STAIRS,
		Block::BRICK_STAIRS,
		Block::COBBLESTONE_STAIRS,
		Block::OAK_STAIRS,
		Block::PURPUR_STAIRS,
		Block::QUARTZ_STAIRS,
		Block::RED_SANDSTONE_STAIRS,
		Block::SANDSTONE_STAIRS,
		Block::STONE_BRICK_STAIRS,
		Block::STONE_STAIRS,
		Block::WOODEN_STAIRS,
		Block::WATER,
		Block::FLOWING_WATER,
		Block::LAVA,
		Block::FLOWING_LAVA,
	];


	/** @var Player */
	private $owner;

	/** @var CheatDetector */
	private $plugin;

	/** @var int */
	private $deviceOs = -1;

	/** @var Task|null */
	private $killAuraTriggersDeincrementTask;

	/** @var KillAuraDetector[] */
	private $killAuraDetectors = [];

	/** @var int */
	public $killAuraTriggers = 0;

	/** @var int */
	public $flyChances = 0;

	/** @var int */
	public $reachChances = 0;

	/** @var int */
	public $lastJumpTime = 0;

	/** @var int */
	public $lastDamagedTime = 0;

	/** @var int */
	public $lastMoveTime = 0;

	/** @var bool */
	public $debugFly = false;

	/** @var bool */
	private $destroyed = false;

	/** Device operating systems */
	const OS_UNKNOWN = 0;
	const OS_ANDROID = 1;
	const OS_IOS = 2;
	const OS_OSX = 3;
	const OS_FIREOS = 4;
	const OS_GEARVR = 5;
	const OS_HOLOLENS = 6;
	const OS_WIN10 = 7;
	const OS_WIN32 = 8;
	const OS_DEDICATED = 9;

	public function __construct(Player $player, CheatDetector $plugin, int $deviceOs = self::OS_UNKNOWN) {
		$this->owner = $player;
		$this->plugin = $plugin;
		$this->deviceOs = $deviceOs;

		$this->killAuraTriggersDeincrementTask = new class($this) extends Task {
			/** @var DetectionSession */
			private $s;

			public function __construct(DetectionSession $s) {
				$this->s = $s;
			}

			public function onRun(int $currentTick) {
				$this->s->killAuraTriggers--;
			}
		};
		$plugin->getScheduler()->scheduleRepeatingTask($this->killAuraTriggersDeincrementTask, 20 * 60); // 1 minute
	}

	/**
	 * @return Player
	 */
	public function getOwner() : Player {
		return $this->owner;
	}

	/**
	 * @return CheatDetector
	 */
	public function getPlugin() : CheatDetector {
		return $this->plugin;
	}

	/**
	 * @return int
	 */
	public function getDeviceOS() : int {
		return $this->deviceOs;
	}

	/**
	 * @return bool
	 */
	public function hasDebugFly() : bool {
		return $this->debugFly;
	}

	/**
	 * Increases the amount of times a player has been detected for having kill aura
	 */
	public function addKillAuraTrigger() : void {
		$this->killAuraTriggers++;
		$this->checkKillAuraTriggers();
	}

	/**
	 * @param bool $value
	 */
	public function setDebugFly(bool $value = true) : void {
		$this->debugFly = $value;
	}

	/**
	 * Checks the amount of times a player has triggered a kill aura detector and handles the result accordingly
	 */
	public function checkKillAuraTriggers() : void {
		if($this->killAuraTriggers >= 12) {
			$this->owner->kick(TextFormat::colorize("&cYou have been kicked for using a modified client!", "&"), false);
			Utils::broadcastStaffMessage("&a" . $this->owner->getName() . " &ehas been kicked for suspected kill-aura!");
		}
	}

	/**
	 * Spawn the kill aura detection entities
	 */
	public function spawnKillAuraDetectors() {
		$nbt = Entity::createBaseNBT($this->owner->asVector3(), null, 180);
		$nbt->setTag(new CompoundTag("Skin", [
			new StringTag("Name", $this->owner->getSkin()->getSkinId()),
			new ByteArrayTag("Data", $this->owner->getSkin()->getSkinData()),
			new ByteArrayTag("CapeData", $this->owner->getSkin()->getCapeData()),
			new StringTag("GeometryName", $this->owner->getSkin()->getGeometryName()),
			new ByteArrayTag("GeometryData", $this->owner->getSkin()->getGeometryData())
		]));

		$entity = Entity::createEntity("KillAuraDetector", $this->owner->getLevel(), clone $nbt);
		if($entity instanceof KillAuraDetector) {
			$entity->setTarget($this);
			$entity->setOffset(new Vector3(0, 3, 0));
		} else {
			$entity->kill();
		}
		$this->killAuraDetectors[] = $entity;

		$entity = Entity::createEntity("KillAuraDetector", $this->owner->getLevel(), clone $nbt);
		if($entity instanceof KillAuraDetector) {
			$entity->setTarget($this);
			$entity->setOffset(new Vector3(0, -1, 0));
		} else {
			$entity->kill();
		}
		$this->killAuraDetectors[] = $entity;
	}

	/**
	 * Checks the amount of times a player has triggered the reach detection and handles the result accordingly
	 */
	public function checkReachTriggers() {
		if($this->reachChances >= 14) {
			$this->owner->kick(TextFormat::colorize("&cYou have been kicked for using a modified client!", "&"), false);
			Utils::broadcastStaffMessage("&a" . $this->owner->getName() . " &ehas been kicked for suspected reach!");
		}
	}

	/**
	 * Increases or decreases the amount of reach triggers based on distance and ping
	 *
	 * @param float $distance
	 */
	public function updateReachTriggers(float $distance) {
		if($distance >= 6.5 and $this->owner->getPing() <= 200) {
			$this->reachChances += 1;
		} elseif($distance >= 8 and $this->owner->getPing() <= 600) {
			$this->reachChances += 2;
		} elseif($distance >= 12) {
			$this->reachChances += 4;
		} else {
			$this->reachChances--;
			return;
		}

		$this->checkReachTriggers();
	}

	/**
	 * Checks the amount of times a player has triggered the fly detection and handles the result accordingly
	 */
	public function checkFlyTriggers() {
		// be more harsh on android players due to it being the easiest platform to 'hack' on
		if(($this->getDeviceOs() === self::OS_ANDROID and $this->flyChances >= 24) or (($this->getDeviceOs() === self::OS_IOS or $this->getDeviceOs() === self::OS_WIN10) and $this->flyChances >= 32) or $this->flyChances >= 48) {
//			if($this->isAuthenticatedInternally()) {
//				$banWaveTask = $this->getCore()->getBanWaveTask();
//				if(!isset($banWaveTask->flyKicks[$this->getName()])) {
//					$banWaveTask->flyKicks[$this->getName()] = 0;
//				}
//				$banWaveTask->flyKicks[$this->getName()]++; // increment number of times kicked for fly
//				if($banWaveTask->flyKicks[$this->getName()] >= 5) {
//					$banWaveTask->queue(new BanEntry(-1, $this->getName(), $this->getAddress(), $this->getClientId(), strtotime("+4 days"), time(), true, "You were banned automatically ¯\_(ツ)_/¯", "MAGIC I"));
//				}
//			}
			$this->owner->kick(TextFormat::colorize("&cYou have been kicked for using a modified client!", "&"), false);
			Utils::broadcastStaffMessage("&a" . $this->owner->getName() . " &ehas been kicked for suspected flight!");
		}
	}

	/**
	 * @param Vector3 $to
	 * @param float $yDistance
	 */
	public function updateFlyTriggers(Vector3 $to, float $yDistance) {
		if($this->owner != null and !$this->owner->getAllowFlight()) { // make sure the player isn't allowed to fly
			$level = $this->owner->getLevel();
			$blockInId = $level->getBlockAt($to->getFloorX(), Math::ceilFloat($to->getY() + 1), $to->getFloorZ())->getId(); // block at players feet (used to make sure player isn't in a transparent block (cobwebs, water, etc)
			$blockOnId = $level->getBlockAt($to->getFloorX(), Math::ceilFloat($to->getY() - 0.5), $to->getFloorZ())->getId(); // block the player is on (use this for checking slabs, stairs, etc)
			$blockBelowId = $level->getBlockAt($to->getFloorX(), Math::ceilFloat($to->getY() - 1), $to->getFloorZ())->getId(); // block beneath the player
			$inAir = ($blockOnId === Block::AIR and $blockInId === Block::AIR and $blockBelowId === Block::AIR);

			$nearLiquid = false;
			foreach($this->owner->getBlocksAround() as $b) {
				if($b instanceof Liquid) {
					$nearLiquid = true;
					break;
				}
			}

			if($this->hasDebugFly()) {
				$this->owner->sendTip("Air ticks: " . $this->owner->getInAirTicks(). ", y-distance: " . $yDistance . ", In air: " . ($inAir ? "yes" : "no") . ", Fly chances: " . $this->flyChances);
				$this->owner->sendPopup("Block on: " . $blockOnId. ", Block in: " . $blockInId . ", Block below: " . $blockBelowId . ", Near liquid: " . ($nearLiquid ? "yes" : "no"));
			}

			if(microtime(true) - $this->lastDamagedTime >= 5 or $nearLiquid) { // player hasn't taken damage for five seconds and isn't near liquid
				// check fly upwards
				if(($yDistance >= 0.05 or ($this->owner->getInAirTicks() >= 100 and $yDistance >= 0)) // TODO: Improve this so detection isn't triggered when players are moving horizontally
					and $this->lastMoveTime - $this->lastJumpTime >= 2) { // if the movement wasn't downwards and the player hasn't jumped for 2 seconds
					if($inAir) { // make sure the player isn't standing on a slab or stairs and the block directly below them is air
						$secondBlockBelowId = $level->getBlockIdAt($to->getFloorX(), Math::ceilFloat($to->getY() - 2), $to->getFloorZ());
						if($secondBlockBelowId === Block::AIR) { // if two blocks directly below them is air
							$thirdBlockBelowId = $level->getBlockIdAt($to->getFloorX(), Math::ceilFloat($to->getY() - 3), $to->getFloorZ());
							if($thirdBlockBelowId === Block::AIR) { // if three blocks directly below them is air
								$this->flyChances += 2;
							} else {
								$this->flyChances += 1;
							}
						}
						if($yDistance >= 0.6) {
							$this->flyChances += 4;
						} elseif($yDistance >= 0.45) {
							$this->flyChances += 2;
						} elseif($yDistance >= 0.38) {
							$this->flyChances += 1;
						}
					} else {
						if($this->flyChances > 0) { // player isn't in the air
							$this->flyChances -= 1;
						}
					}
				} else {
					if($this->flyChances > 0) { // player just likes to jump
						$this->flyChances -= 2;
					}
				}
			}
			$this->checkFlyTriggers();
		}
	}


	public function destroy() {
		if(!$this->destroyed) {
			$this->destroyed = true;
			$this->plugin->getScheduler()->cancelTask($this->killAuraTriggersDeincrementTask->getTaskId());

			foreach($this->killAuraDetectors as $e) {
				$e->kill();
			}

			$this->killAuraDetectors = [];

			unset($this->owner, $this->plugin, $this->killAuraTriggersDeincrementTask);
		}
	}

}
