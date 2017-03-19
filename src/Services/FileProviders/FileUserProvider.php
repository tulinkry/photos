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

class FileUserProvider implements IUserProvider
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
     * @var IAlbumProvider
     */
    private $albums;

    use TMetadata;

	public function __construct(ParameterService $params, LinkGenerator $linkGenerator, IStorage $storage, IAlbumProvider $albums) {
		$this->parameters = $params;
		$this->linkGenerator = $linkGenerator;
		$this->cache = new Cache($storage, 'photos');
		$this->albums = $albums;
	}

	private function doFind($id, $params) {
		$directory = $this->parameters->params['directory'];

		$userDir = $directory . DIRECTORY_SEPARATOR . 'users' . DIRECTORY_SEPARATOR . $id;
		if($id !== NULL && is_dir($userDir)) {
			$user = new \StdClass;
			$user->id = $id;
			$user->albums = [];
			$metadata = $this->loadMetadata($userDir);

			if(!isset($metadata->defaultAlbum)) {
				return NULL;
				// throw new \Exception(sprintf("This user '%s' does not have the default album in metadata.", $id));
			}

			$this->albums->find($metadata->defaultAlbum); // throws exception

			$albumDir = $userDir . DIRECTORY_SEPARATOR . 'albums';

			if(is_dir($albumDir)) {
				foreach(Finder::findDirectories("*")->in($albumDir) as $key => $file) {
					$user->albums[] = $album = new \StdClass;
					$album->id = $file->getFilename();
					
					$album->url = $this->linkGenerator->link("Photos:Download:photos", array(
						'albumId' => $album->id,
						'userId' => $id
					));

					if($album->id === $metadata->defaultAlbum) {
						$album->name = 'default';
						$album->default = true;
					} else if(($ax = $this->albums->find($album->id)) !== null && isset($ax->metadata->name)) {
						$album->name = $ax->metadata->name;
					}
				}
			}

			if($metadata) {
				$user->metadata = $metadata;
			}

			return $user;
		}
		return NULL;		
	}

	/**
	 * Finds all photos by the given user.
	 *
	 * @param  mixed $id id of the user
	 * @return array     array of photos
	 */
	public function find($id, $params = array())
	{
		$that = $this;
		return $this->cache->load("user-$id", function(&$dependencies) use ($that, $id, $params) {
			$dependencies[Cache::TAGS] = ["user-$id"];
			return $that->doFind($id, $params);
		});
	}


	public function create($params = array())
	{
		$directory = $this->parameters->params['directory'];
		$user = new \StdClass;

		$usersDir = $directory . DIRECTORY_SEPARATOR . 'users';
		$albumsDir = $directory . DIRECTORY_SEPARATOR . 'albums';
		
		FileSystem::createDir($usersDir);

		$i = 0;
		do {
			$id = Strings::random(32);
			$i ++;
		} while ($i <= self::MAX_TRIES && is_dir($usersDir . DIRECTORY_SEPARATOR . $id));

		if($i > self::MAX_TRIES) {
			throw new \Exception(sprintf("Unable to find unique id for the new user."));
		}

		$userDir = $usersDir . DIRECTORY_SEPARATOR . $id;
		FileSystem::createDir($userDir);

		$this->saveMetadata($userDir, $params);

		$defaultAlbum = $this->albums->create($id, ['name' => 'default']);
		if ( $defaultAlbum === NULL || !$this->addMetadata($userDir, array('defaultAlbum' => $defaultAlbum->id))) {
			FileSystem::delete($userDir);
			throw new \Exception(sprintf('Unable to create a default album for new user with id \'%s\'', $id));
		}

		FileSystem::createDir($userDir . DIRECTORY_SEPARATOR . 'default');
		Symlink::create(
			$albumsDir . DIRECTORY_SEPARATOR . $defaultAlbum->id, 
			$userDir . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . $defaultAlbum->id);
		//FileSystem::createDir($userDir . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . $defaultAlbum->id);
		return $this->find($id);
	}

	public function update($id, $params = array())
	{
		if (($user = $this->find($id, $params)) === NULL)
			throw new \Exception(sprintf('No user with id \'%s\' found.', $id));

		$directory = $this->parameters->params['directory'];
		$userDir = $directory . DIRECTORY_SEPARATOR . 'users' . DIRECTORY_SEPARATOR . $id;
		$this->cache->clean(array(
			Cache::TAGS => ["user-$id"]
		));
		if(is_dir($userDir)) {
			$this->addMetadata($userDir, $params);
			return $user;
		}
		throw new \Exception(sprintf('The record for user \'%s\' does not exist.', $id));
	}

	public function getUserDefaultAlbum($id) {
		$directory = $this->parameters->params['directory'];
		$userDir = $directory . DIRECTORY_SEPARATOR . 'users' . DIRECTORY_SEPARATOR . $id;
		if($id !== NULL && is_dir($userDir)) {
			$metadata = $this->loadMetadata($userDir);
			if(!isset($metadata->defaultAlbum)) {
				return NULL;
				// throw new \Exception(sprintf("This user '%s' does not have the default album in metadata.", $id));
			}

			return $this->albums->find($metadata->defaultAlbum);
		}
		return NULL;
	}
}