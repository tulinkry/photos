<?php

namespace Tulinkry\Photos\Utils;

use Nette\IOException;

class Symlink
{
	public static function create($target, $name) {
		if(@symlink($target, $name) === FALSE) {
			throw new Nette\IOException("Unable to create symlink '$name' -> $target'. " . error_get_last()['message']);
		}
	}
};