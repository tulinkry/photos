<?php

namespace Tulinkry\Photos\Services;

interface IContentProvider
{
	/**
	 * Finds all photos by the given user.
	 *
	 * @param  mixed $id id of the user
	 * @return array     array of photos
	 */
	public function create($photoId, $file, $params = array());

}