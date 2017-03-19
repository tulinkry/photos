<?php

namespace Tulinkry\Photos\Services;

interface IVerifyService
{
	const ALBUMS_LISTING = "albums.get",
		  ALBUM_SINGLE = "album.get",
		  PHOTOS_LISTING = "photos.get",
		  PHOTO_SINGLE = "photo.get",
		  CONTENT_SINGLE = "content.get";
	/**
	 * Verifies if the given key is authorized to see the content.
	 *
	 * @param mixed $operation requested operation, one of the IVerifyService constants
	 * @param mixed $id id of the requested object
	 * @param  string $key api key, may be null
	 * @return boolean      true if the key is authorized
	 */
	public function verify($operation, $id, $key);
}