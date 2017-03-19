<?php

namespace Tulinkry\Photos\Services;

interface IPhotosProvider
{
	/**
	 * Finds a photo with given id.
	 * @param  mixed $id id of the photo
	 * @return mixed     photo object or null if there is no such photo
	 */
	public function find($id, $params = array());

	/**
	 * Create a photo with additional information
	 * @param albumId id of the album which should own this photo
	 * @param params additional info stored besides the photo
	 * @return object containing at least the "id" property of the photo
	 */
	public function create($albumId, $file, $params = array());	
}