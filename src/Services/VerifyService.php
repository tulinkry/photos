<?php

namespace Tulinkry\Photos\Services;

class VerifyService implements IVerifyService
{
	/**
	 * Verifies if the given key is authorized to see the content.
	 *
	 * @param mixed $operation requested operation, one of the IVerifyService constants
	 * @param mixed $id id of the requested object
	 * @param  string $key api key, may be null
	 * @return boolean      true if the key is authorized
	 */
	public function verify($operation, $id, $key) {
		return true;
	}
}