<?php

namespace Tulinkry\Photos\Services;

use Tulinkry\Photos\Utils\TMetadata;
use Tulinkry\Photos\Utils\Symlink;

use Nette\Application\LinkGenerator;
use Nette\Utils\FileSystem;
use Nette\Utils\Finder;
use Nette\Utils\Strings;
use Nette\Caching\IStorage;
use Nette\Caching\Cache;

use Nette\Http\FileUpload;

use SplFileInfo;
use Nette;

class FilePhotosProvider implements IPhotosProvider
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
	 * @var IContentProvider
	 */
	private $content;

    /**
     * @var Cache
     */
    private $cache;

    use TMetadata;

	public function __construct(ParameterService $params, 
		LinkGenerator $linkGenerator, 
		IContentProvider $content, 
		IStorage $storage) {
		$this->parameters = $params;
		$this->linkGenerator = $linkGenerator;
		$this->content = $content;
		$this->cache = new Cache($storage, 'photos');
	}

	private function doFind($id, $params) {
		$directory = $this->parameters->params['directory'];

		$photoDir = $directory . DIRECTORY_SEPARATOR . 'photos' . DIRECTORY_SEPARATOR . $id;

		if($id !== NULL && is_dir($photoDir) && is_dir($photoDir . DIRECTORY_SEPARATOR . 'default')) {
			$metadata = $this->loadMetadata($photoDir);
			foreach(Finder::findFiles("*")->in($photoDir . DIRECTORY_SEPARATOR . 'default') as $key => $file) {
				$photo = new \StdClass;
				$photo->id = $id;
				$photo->filename = $photo->originalFilename = $file->getFilename();

				$urlParams = array_filter(array(
					'photoId' => $id,
					//'albumId' => isset($cachedPhoto->album->id) ? $cachedPhoto->album->id : NULL,
					//'userId' => isset($cachedPhoto->album->user->id) ? $cachedPhoto->album->user->id : NULL,
				), function($el) {
					return $el !== NULL;
				});

				$photo->url = $this->linkGenerator->link("Photos:Download:photo", $urlParams);
				$photo->downloadUrl = $this->linkGenerator->link("Photos:Download:content", $urlParams);

				if($metadata) {
					$photo->metadata = $metadata;
					if(isset($photo->metadata->originalFilename)) {
						$photo->originalFilename = $photo->metadata->originalFilename;
						unset($photo->metadata->originalFilename);
					}
				}
				return $photo;
			}
		}
		return NULL;
	}

	/**
	 * Finds a photo with given id.
	 * @param  mixed $id id of the album
	 * @return mixed     album object or null if there is no such album
	 */
	public function find($id, $params = array())
	{
		$that = $this;
		return $this->cache->load("photo-$id", function(&$dependencies) use ($that, $id, $params) {
			$dependencies[Cache::TAGS] = ["photo-$id"];
			return $that->doFind($id, $params);
		});
		// throw new \Exception(sprintf("This photo '%s' does not exist.", $id));
	}

	/**
	 * Create a photo with additional information
	 * @param albumId id of the album which should own this photo
	 * @param params additional info stored besides the photo
	 * @return object containing at least the "id" property of the photo
	 */
	public function create($albumId, $file, $params = array())
	{
		$directory = $this->parameters->params['directory'];

		$photosDir = $directory . DIRECTORY_SEPARATOR . 'photos';

		FileSystem::createDir($photosDir);

		$i = 0;
		do {
			$id = Strings::random(32);
			$i ++;
		} while ($i <= self::MAX_TRIES && is_dir($photosDir . DIRECTORY_SEPARATOR . $id));

		if($i > self::MAX_TRIES) {
			throw new \Exception(sprintf("Unable to find unique id for the new album."));
		}

		if($file instanceof SplFileInfo) {
			list($path, $filename) = array($file->getRealPath(), $file->getFilename());
		} else if($file instanceof FileUpload) {
			list($path, $filename) = array($file->temporaryFile, $file->name);
		} else {
			list($path, $filename) = array($file, basename($file));
		}

		$params['originalFilename'] = $filename;

		$photoDir = $photosDir . DIRECTORY_SEPARATOR . $id;
		FileSystem::createDir($photoDir);
		$this->saveMetadata($photoDir, $params);


		// TODO: check for failure
		$this->addToAlbum($id, $albumId);

		$this->content->create($id, $file, $params);
		return $this->find($id);
	}

	public function addToAlbum($id, $albumId) {
		$directory = $this->parameters->params['directory'];
		$a = $directory . DIRECTORY_SEPARATOR . 'albums' . DIRECTORY_SEPARATOR . $albumId;
		FileSystem::createDir($a . DIRECTORY_SEPARATOR . 'photos');
		//Symlink::create($directory . DIRECTORY_SEPARATOR . 'photos' . DIRECTORY_SEPARATOR . $id,
		//		$a . DIRECTORY_SEPARATOR . 'photos' .DIRECTORY_SEPARATOR . $id);
		FileSystem::write($a . DIRECTORY_SEPARATOR . 'photos' .DIRECTORY_SEPARATOR . $id, "", NULL);

//			FileSystem::createDir($a . DIRECTORY_SEPARATOR . 'photos' .DIRECTORY_SEPARATOR . $id);
		FileSystem::createDir($directory . DIRECTORY_SEPARATOR . 'photos' . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR . 'album');
		FileSystem::write($directory . DIRECTORY_SEPARATOR . 'photos' . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR . 'album' . DIRECTORY_SEPARATOR . $albumId, "", NULL);
		//Symlink::create($a, 
		//	$directory . DIRECTORY_SEPARATOR . 'photos' . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR . 'album' . DIRECTORY_SEPARATOR . $albumId);
//			FileSystem::createDir($directory . DIRECTORY_SEPARATOR . 'photos' . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR . 'album' . DIRECTORY_SEPARATOR . $albumId);

		$this->cache->clean(array(
			Cache::TAGS => ["album-$albumId"]
		));

		foreach(Finder::find('*')->in($a . DIRECTORY_SEPARATOR . 'user') as $path => $file) {
			$user = $directory . DIRECTORY_SEPARATOR . 'users' . DIRECTORY_SEPARATOR . $file->getFilename();
			// $this->cache->clean(array(
			// 	Cache::TAGS => ["user-" . $file->getFilename()]
			// ));
			foreach(Finder::find('*')->in($user . DIRECTORY_SEPARATOR . 'default') as $path1 => $file1) {
				if ($albumId !== $file1->getFilename()) {
					$this->addToAlbum($id, $file1->getFilename());
				}
				break;
			}
			break;
		}
	}
}