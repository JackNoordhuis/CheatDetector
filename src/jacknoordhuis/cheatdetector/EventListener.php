<?php

/**
 * EventListener.php – CheatDetector
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

use jacknoordhuis\cheatdetector\util\Utils;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerJumpEvent;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\Player;

class EventListener implements Listener {

	/** @var CheatDetector */
	private $plugin;

	/** @var int[] */
	private $osMap = [];

	public function __construct(CheatDetector $plugin) {
		$this->plugin = $plugin;

		$plugin->getServer()->getPluginManager()->registerEvents($this, $plugin);
	}

	public function onJoin(PlayerJoinEvent $event) {
		$player = $event->getPlayer();

		$this->plugin->openCheatDetectionSession($player, $this->osMap[spl_object_id($player)] ?? -1);

		if(in_array(strtolower($player->getName()), $this->plugin->staff)) {
			Utils::addStaff($player);
		}
	}

	public function onQuit(PlayerQuitEvent $event) {
		$player = $event->getPlayer();

		$this->plugin->closeCheatDetectionSession($player);

		if(in_array(strtolower($player->getName()), $this->plugin->staff)) {
			Utils::removeStaff($player);
		}

		if(isset($this->osMap[$id = spl_object_id($player)])) {
			unset($this->osMap[$id]);
		}
	}

	public function onKick(PlayerKickEvent $event) {
		$player = $event->getPlayer();

		$this->plugin->closeCheatDetectionSession($player);

		if(in_array(strtolower($player->getName()), $this->plugin->staff)) {
			Utils::removeStaff($player);
		}

		if(isset($this->osMap[$id = spl_object_id($player)])) {
			unset($this->osMap[$id]);
		}
	}

	public function onDataPacketReceive(DataPacketReceiveEvent $event) {
		$packet = $event->getPacket();
		if($packet instanceof LoginPacket) {
			$player = $event->getPlayer();
			if(isset($packet->clientData["DeviceOS"])) {
				$this->osMap[spl_object_id($player)] = $packet->clientData["DeviceOS"];
			}
		}
	}

	public function onEntityDamage(EntityDamageEvent $event) {
		$victim = $event->getEntity();
		if($victim instanceof Player) {
			$s = $this->plugin->getCheatDetectionSession($victim);
			$s->lastDamagedTime = microtime(true);
			if($event instanceof EntityDamageByEntityEvent) {
				$attacker = $event->getDamager();
				if($attacker instanceof Player) {
					$s = $this->plugin->getCheatDetectionSession($attacker);
					$s->updateReachTriggers($victim->distance($attacker));
				}
			}
		}
	}

	public function onMove(PlayerMoveEvent $event) {
		$player = $event->getPlayer();
		$s = $this->plugin->getCheatDetectionSession($player);

		$s->lastMoveTime = microtime(true);
		$s->updateFlyTriggers($event->getTo(), round($event->getTo()->getY() - $event->getFrom()->getY(), 3));
	}

	public function onJump(PlayerJumpEvent $event) {
		$player = $event->getPlayer();
		$s = $this->plugin->getCheatDetectionSession($player);

		$s->lastJumpTime = microtime(true);
	}

}