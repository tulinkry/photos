<?php

namespace Tulinkry\Photos\Services;

interface IUserProvider
{
	/**
	 * Finds all photos by the given user.
	 *
	 * @param  mixed $id id of the user
	 * @return array     array of photos
	 */
	public function find($id, $params = array());

	public function create($params = array());

	public function update($id, $params = array());

	public function getUserDefaultAlbum($id);
}