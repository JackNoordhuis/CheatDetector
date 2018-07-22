<?php

/**
 * CheatDetectionReference.php – CheatDetector
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

use jacknoordhuis\cheatdetector\CheatDetector;

trait CheatDetectionReference {

	/** @var CheatDetector */
	private $plugin = null;

	/**
	 * Set the plugin instance
	 *
	 * @param CheatDetector $plugin
	 */
	public function setCheatDetector(CheatDetector $plugin) {
		$this->plugin = $plugin;
	}

	/**
	 * Get the plugin instance
	 *
	 * @return CheatDetector|null
	 */
	public function getCheatDetector() : ?CheatDetector {
		return $this->plugin;
	}

	/**
	 * Destroy the reference to the plugin
	 */
	public function destroyCheatDetectorReference() : void {
		unset($this->plugin);
	}

}