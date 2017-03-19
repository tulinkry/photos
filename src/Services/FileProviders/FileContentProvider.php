<?php

namespace Tulinkry\Photos\Services;

use Tulinkry\Photos\Utils\TMetadata;
use Tulinkry\Photos\Utils\Symlink;

use Nette\Application\LinkGenerator;
use Nette\Utils\FileSystem;
use Nette\Utils\Finder;
use Nette\Caching\IStorage;
use Nette\Caching\Cache;
use Nette\Utils\Image;
use Nette\Http\FileUpload;

use Nette;
use Tracy\Debugger;

use SplFileInfo;

class FileContentProvider implements IContentProvider
{
	private $allowedSizes = array('default', 100, 150, 200, 250, 300, 500, 720, 1080);

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

    private $directory;

    use TMetadata;

	public function __construct(ParameterService $params, LinkGenerator $linkGenerator, IStorage $storage) {
		$this->parameters = $params;
		$this->linkGenerator = $linkGenerator;
		$this->cache = new Cache($storage, 'photos');
		$this->directory = $this->parameters->params['directory'];
	}

	private function doFind($id, $params = array())
	{
		if(isset($params['size']) && !in_array($params['size'], $this->allowedSizes)) {
			throw new \Exception(
				sprintf('Parameter "%s" is not in allowed options. "%s" was given.', 'size', $params['size'])
			);
		}

		$size = isset($params['size']) ? $params['size'] : 'default';
		
		$photoDir = $this->directory . DIRECTORY_SEPARATOR . 'photos' . DIRECTORY_SEPARATOR . $id;

		if(is_dir($photoDir)) {
			if($size !== 'default' && !is_dir($photoDir . DIRECTORY_SEPARATOR . $size)) {
				// generate this size
				$this->generateSize($id, $size);
			}
			if(is_dir($photoDir . DIRECTORY_SEPARATOR . $size)) {
				return $this->getPhoto($id, $photoDir . DIRECTORY_SEPARATOR . $size);
			}
		}
		return NULL;
		// throw new \Exception(sprintf("This photo '%s' does not exist.", $id));
	}

	/**
	 * Finds a photo with given id.
	 * @param  mixed $id id of the album
	 * @return mixed     album object or null if there is no such album
	 */
	public function find($id, $params = array())
	{
		$that = $this;
		$size = isset($params['size']) ? $params['size'] : 'default';
		return $this->cache->load("content-$id-$size", function(&$dependencies) use ($that, $id, $size, $params) {
			$dependencies[Cache::TAGS] = ["content-$id-$size"];
			return $that->doFind($id, $params);
		});
	}

	protected function getPhoto($id, $directory) {
		foreach(Finder::findFiles("*")->in($directory) as $key => $file) {
			if(!$file->isReadable())
				continue;

			$photo = new \StdClass;
			$photo->id = $id;
			$photo->name = $file->getFilename();
			$photo->path = $key;
			$photo->lastModified = $file->getMTime();
			$photo->contentType = mime_content_type($key);
			$photo->size = $file->getSize();
			return $photo;
		}
		return NULL;		
	}

	protected function generateSize($id, $size)
	{
		$photoDir = $this->directory . DIRECTORY_SEPARATOR . 'photos' . DIRECTORY_SEPARATOR . $id;
		$sizeDir = $photoDir . DIRECTORY_SEPARATOR . $size;
		$defaultDir = $photoDir . DIRECTORY_SEPARATOR . 'default';

		if(!is_dir($defaultDir)) {
			return false;
		}

		if(($photo = $this->getPhoto($id, $defaultDir)) !== NULL) {
			try {
				$this->create($id, $photo->path, array('size' => $size));
				return true;
			} catch (\Exception $e) {
				Debugger::log($e);
				return false;
			}
		}
		return false;
	}

	/**
	 * Create a photo with additional information
	 * @param albumId id of the album which should own this photo
	 * @param file make this content from this file (not changing this location - copying)
	 * @param params additional info stored besides the photo
	 * @return object containing at least the "id" property of the photo
	 */
	public function create($photoId, $file, $params = array())
	{

		if(isset($params['size']) && !in_array($params['size'], $this->allowedSizes)) {
			throw new \Exception(
				sprintf('Parameter "%s" is not in allowed options. "%s" was given.', 'size', $params['size'])
			);
		}

		$size = isset($params['size']) ? $params['size'] : 'default';
		$photoDir = $this->directory . DIRECTORY_SEPARATOR . 'photos' . DIRECTORY_SEPARATOR . $photoId . DIRECTORY_SEPARATOR . $size;

		
		FileSystem::createDir($photoDir);

		if($file instanceof SplFileInfo) {
			list($path, $filename) = array($file->getRealPath(), $file->getFilename());
		} else if($file instanceof FileUpload) {
			list($path, $filename) = array($file->temporaryFile, $file->sanitizedName);
		} else {
			list($path, $filename) = array($file, basename($file));
		}

		FileSystem::copy($path, $photoDir . DIRECTORY_SEPARATOR . $filename);

		if($size !== 'default') {
			$img = Image::fromFile($photoDir . DIRECTORY_SEPARATOR . $filename);
			$img->resize($size, NULL);
			$img->save($photoDir . DIRECTORY_SEPARATOR . $filename);
		}

		$this->cache->clean(array(
			Cache::TAGS => ["content-$photoId-$size"]
		));
	}
}