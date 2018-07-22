<?php

/**
 * Destroyable.php – CheatDetector
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

trait Destroyable {

	private $destroyed = false;

	public function isDestroyed() : bool {
		return $this->destroyed;
	}

	protected function setDestroyed() {
		$this->destroyed = true;
	}

	abstract public function destroy();

}