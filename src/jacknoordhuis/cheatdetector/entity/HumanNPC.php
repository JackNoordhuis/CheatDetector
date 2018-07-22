<?php

/**
 * HumanNPC.php – CheatDetector
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

namespace jacknoordhuis\cheatdetector\entity;

use jacknoordhuis\cheatdetector\CheatDetector;
use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\entity\Skin;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\level\ChunkLoader;
use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\level\Location;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\Player;
use pocketmine\plugin\PluginException;

abstract class HumanNPC extends Human implements ChunkLoader {

	/** @var CheatDetector */
	private $plugin;

	/** @var string */
	protected $name = "";

	/** @var bool */
	protected $active = true;

	/** @var int[][] */
	protected $loadedChunks = [];

	/** @var int */
	private $lastChunkHash;

	/**
	 * @return CheatDetector
	 */
	public function getPlugin() : CheatDetector {
		return $this->plugin;
	}

	/**
	 * Check if the entity is actively loading chunks
	 *
	 * @return bool
	 */
	public function isActiveChunkLoader() : bool {
		return $this->active;
	}

	/**
	 * Set whether the entity should load chunks or not
	 *
	 * @param bool $value
	 */
	public function setActiveChunkLoader(bool $value = true) : void {
		$this->active = $value;
	}

	/**
	 * Spawn the NPC to a player
	 *
	 * @param Player $player
	 */
	public function spawnTo(Player $player) : void {
		if(!isset($this->hasSpawned[$player->getLoaderId()]) and $this->chunk !== null and isset($player->usedChunks[((($this->chunk->getX()) & 0xFFFFFFFF) << 32) | (( $this->chunk->getZ()) & 0xFFFFFFFF)])) {
			$this->hasSpawned[$player->getId()] = $player;

			$pk = new PlayerListPacket();
			$pk->type = PlayerListPacket::TYPE_ADD;
			$pk->entries[] = PlayerListEntry::createAdditionEntry($this->getUniqueId(), $this->getId(), "", "", 0, $this->skin, "", "");
			$player->dataPacket($pk);

			$this->sendSpawnPacket($player);

			$this->armorInventory->sendContents($player);
		}
	}

	/**
	 * Ensure the NPC doesn't take damage
	 *
	 * @param EntityDamageEvent $source
	 */
	public function attack(EntityDamageEvent $source) : void {
		$source->setCancelled(true);
	}

	/**
	 * Make sure the npc doesn't get saved
	 */
	public function saveNBT() : void {
		return;
	}

	/**
	 * Same save characteristics as a player
	 */
	public function getSaveId() : string {
		return "Human";
	}

	/**
	 * Set the NPC's real name to the one given when the entity is spawned
	 */
	public function initEntity() : void {
		parent::initEntity();

		$plugin = $this->server->getPluginManager()->getPlugin("CheatDetector");
		if($plugin instanceof CheatDetector and $plugin->isEnabled()){
			$this->plugin = $plugin;
		} else {
			throw new PluginException("CheatDetector plugin isn't loaded!");
		}

		$this->name = $this->getNameTag();
		$this->getLevel()->registerChunkLoader($this, $this->chunk->getX(), $this->chunk->getZ());

		$this->setImmobile();
		$this->setNameTagVisible();
		$this->setNameTagAlwaysVisible();
	}

	/**
	 *
	 *
	 * @param int $tickDiff
	 *
	 * @return bool
	 */
	public function entityBaseTick(int $tickDiff = 1): bool {
		if(!$this->isImmobile() and $this->active) {
			if($this->lastChunkHash !== ($hash = Level::chunkHash($x = $this->chunk->getX(), $z = $this->chunk->getZ()))) {
				$this->registerToChunk($x, $z);

				Level::getXZ($this->lastChunkHash, $oldX, $oldZ);
				$this->unregisterFromChunk($oldX, $oldZ);

				$this->lastChunkHash = $hash;
			}
		}

		return parent::entityBaseTick($tickDiff);
	}

	/**
	 * @param $string
	 */
	public function setName(string $string) : void {
		$this->name = $string;
	}

	/**
	 * @return string
	 */
	public function getName() : string {
		return $this->name;
	}

	/**
	 * Make sure nothing drops in case the NPC dies
	 *
	 * @return array
	 */
	public function getDrops() : array {
		return [];
	}

	/**
	 * Function to easily spawn an NPC
	 *
	 * @param string $shortName
	 * @param Location $pos
	 * @param string $name
	 * @param string $skin
	 * @param string $skinName
	 * @param CompoundTag $nbt
	 *
	 * @return HumanNPC|null
	 */
	public static function spawn($shortName, Location $pos, $name, $skin, $skinName, CompoundTag $nbt) {
		$entity = Entity::createEntity($shortName, $pos->getLevel(), $nbt);
		if($entity instanceof HumanNPC) {
			$entity->setSkin(new Skin($skinName, $skin));
			$entity->setName($name);
			$entity->setNameTag($entity->getName());
			$entity->setPositionAndRotation($pos, $pos->yaw, $pos->pitch);
			return $entity;
		} else {
			$entity->kill();
		}
		return null;
	}

	/**
	 * Unregister as a chunk loader from all loaded chunks
	 */
	public function kill() : void {
		$this->active = false;

		foreach($this->loadedChunks as $hash => $v) {
			Level::getXZ($hash, $x, $z);
			$this->unregisterFromChunk($x, $z);
		}

		parent::kill();
	}

	public function getLoaderId(): int {
		return $this->getId();
	}

	public function isLoaderActive(): bool {
		return $this->active;
	}

	/**
	 * Add the entity as a loader to the specified chunk
	 *
	 * @param int $chunkX
	 * @param int $chunkZ
	 */
	public function registerToChunk(int $chunkX, int $chunkZ) : void {
		if(!isset($this->loadedChunks[Level::chunkHash($chunkX, $chunkZ)]) and $this->loadedChunks[Level::chunkHash($chunkX, $chunkZ)]) {
			$this->loadedChunks[Level::chunkHash($chunkX, $chunkZ)] = true;
			$this->getLevel()->registerChunkLoader($this, $chunkX, $chunkZ);
		}
	}

	/**
	 * Remove the entity as a loader from the specified chunk
	 *
	 * @param int $chunkX
	 * @param int $chunkZ
	 */
	public function unregisterFromChunk(int $chunkX, int $chunkZ) : void {
		if(isset($this->loadedChunks[Level::chunkHash($chunkX, $chunkZ)])) {
			unset($this->loadedChunks[Level::chunkHash($chunkX, $chunkZ)]);
			$this->getLevel()->unregisterChunkLoader($this, $chunkX, $chunkZ);
		}
	}

	public function onChunkChanged(Chunk $chunk) {
		// TODO: Implement onChunkChanged() method.
	}

	public function onChunkLoaded(Chunk $chunk) {
		// TODO: Implement onChunkLoaded() method.
	}

	public function onBlockChanged(Vector3 $block) {
		// TODO: Implement onBlockChanged() method.
	}

	public function onChunkPopulated(Chunk $chunk) {
		// TODO: Implement onChunkPopulated() method.
	}

	public function onChunkUnloaded(Chunk $chunk) {
		// TODO: Implement onChunkUnloaded() method.
	}

}