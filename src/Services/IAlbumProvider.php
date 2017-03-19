<?php

namespace Tulinkry\Photos\Services;

interface IAlbumProvider
{
	/**
	 * Finds an album with given id.
	 * @param  mixed $id id of the album
	 * @return mixed     album object or null if there is no such album
	 */
	public function find($id, $params = array());

	/**
	 * Create an album with additional information
	 * @param userId id of the user which should own this album
	 * @param params additional info stored besides the album
	 * @return object containing at least the "id" property of the album
	 */
	public function create($userId, $params = array());

	public function update($id, $params = array());

}