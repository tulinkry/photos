<?php

namespace Tulinkry\Photos\Services;

use Tulinkry;
use Tulinkry\Photos\Utils\TMetadata;
use Tulinkry\Photos\Utils\Symlink;
use Nette\Application\LinkGenerator;
use Nette\Utils\FileSystem;
use Nette\Utils\Finder;
use Nette\Caching\IStorage;
use Nette\Caching\Cache;
use Nette\Utils\Strings;
use Nette\Utils\Arrays;

class FileAlbumProvider implements IAlbumProvider
{
	const MAX_TRIES = 30;

	/**
	 * @var ParameterService
	 */
	private $parameters;

	/**
	 * @var LinkGenerator
	 */
	private $linkGenerator;

    /**
     * @var Cache
     */
    private $cache;

    /**
     * @var IUserProvider
     */
    private $users;

    use TMetadata;

	public function __construct(ParameterService $params, LinkGenerator $linkGenerator, IStorage $storage) {
		$this->parameters = $params;
		$this->linkGenerator = $linkGenerator;
		$this->cache = new Cache($storage, 'photos');
	}

	private function doFind($id, $params) {
		$directory = $this->parameters->params['directory'];

		$albumDir = $directory . DIRECTORY_SEPARATOR . 'albums' . DIRECTORY_SEPARATOR . $id;
		if($id !== NULL && is_dir($albumDir)) {
			$album = new \StdClass;
			$album->photos = [];
			$album->id = $id;
			$metadata = $this->loadMetadata($albumDir);

			$photoDir = $albumDir . DIRECTORY_SEPARATOR . 'photos';

			if(is_dir($photoDir)) {
				foreach(Finder::findDirectories("*")->in($photoDir) as $key => $file) {
					$album->photos[] = $photo = new \StdClass;
					$photo->id = $file->getFilename();

					$urlParams = array_filter(array(
						'photoId' => $photo->id,
						//'albumId' => isset($cachedAlbum->id) ? $cachedAlbum->id : NULL,
						//'userId' => isset($cachedAlbum->user->id) ? $cachedAlbum->user->id : NULL,
					), function($el) {
						return $el !== NULL;
					});

					$photo->url = $this->linkGenerator->link("Photos:Download:photo", $urlParams);
					$photo->downloadUrl = $this->linkGenerator->link("Photos:Download:content", $urlParams);
				}
			}

			if($metadata) {
				$album->metadata = $metadata;
			}
			return $album;
		}
		return NULL;		
	}

	/**
	 * Finds an album with given id.
	 * @param  mixed $id id of the album
	 * @return mixed     album object or null if there is no such album
	 */
	public function find($id, $params = array())
	{
		$that = $this;
		return $this->cache->load("album-$id", function(&$dependencies) use ($that, $id, $params) {
			$dependencies[Cache::TAGS] = ["album-$id"];
			return $that->doFind($id, $params);
		});
	}

	/**
	 * Create an album with additional information
	 * @param userId id of the user which should own this album
	 * @param params additional info stored besides the album
	 * @return object containing at least the "id" property of the album
	 */
	public function create($userId, $params = array())
	{
		$directory = $this->parameters->params['directory'];

		$albumsDir = $directory . DIRECTORY_SEPARATOR . 'albums';

		FileSystem::createDir($albumsDir);

		$i = 0;
		do {
			$id = Strings::random(32);
			$i ++;
		} while ($i <= self::MAX_TRIES && is_dir($albumsDir . DIRECTORY_SEPARATOR . $id));

		if($i > self::MAX_TRIES) {
			throw new \Exception(sprintf("Unable to find unique id for the new album."));
		}

		$albumDir = $albumsDir . DIRECTORY_SEPARATOR . $id;
		FileSystem::createDir($albumDir);
		
		$params = Arrays::mergeTree(array('id' => $userId), $params);

		$this->saveMetadata($albumDir, $params);

		$this->addAlbumToUser($id, $userId);

		$this->cache->clean(array(
			Cache::TAGS => ["user-$userId"]
		));

		return $this->find($id);
	}

	public function update($id, $params = array())
	{
		if (($album = $this->find($id, $params)) === NULL)
			throw new \Exception(sprintf('No album with id \'%s\' found.', $id));

		$directory = $this->parameters->params['directory'];
		$albumDir = $directory . DIRECTORY_SEPARATOR . 'albums' . DIRECTORY_SEPARATOR . $id;
		$this->cache->clean(array(
    		Cache::TAGS => ["album-$id"],
		));
		if(is_dir($albumDir)) {
			$this->addMetadata($albumDir, $params);
			return $album;
		}
		throw new \Exception(sprintf('The record for album \'%s\' does not exist.', $id));
	}



	public function addAlbumToUser($id, $userId) {
		$directory = $this->parameters->params['directory'];

		$a = $directory . DIRECTORY_SEPARATOR . 'users' . DIRECTORY_SEPARATOR . $userId;
		FileSystem::createDir($a);
		$target = $directory . DIRECTORY_SEPARATOR . 'albums' . DIRECTORY_SEPARATOR . $id;
		$name = $a . DIRECTORY_SEPARATOR . 'albums' . DIRECTORY_SEPARATOR . $id;
		Symlink::create($target, $name);
		//FileSystem::createDir($a . DIRECTORY_SEPARATOR . 'albums' . DIRECTORY_SEPARATOR . $id);
		FileSystem::createDir($directory . DIRECTORY_SEPARATOR . 'albums' . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR . 'user');
		$target = $a;
		$name = $directory . DIRECTORY_SEPARATOR . 'albums' . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR . $userId;
		Symlink::create($target, $name);
		//FileSystem::createDir($directory . DIRECTORY_SEPARATOR . 'albums' . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR . $userId);
	}
}