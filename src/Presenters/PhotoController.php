<?php

namespace Tulinkry\Photos\Presenters;

use Tulinkry\Application\UI\Presenter;

use Tulinkry\Photos\Services\IPhotosProvider;
use Tulinkry\Photos\Services\IAlbumProvider;

use Tulinkry\Photos\Forms\UploadFormFactory;

class PhotoController extends Presenter
{
	/**
	 * @var UploadFormFactory
	 * @inject
	 */
	public $uploadFormFactory;

	/**
	 * @var IPhotosProvider
	 * @inject
	 */
	public $photos;

	/**
	 * @var IAlbumProvider
	 * @inject
	 */
	public $albums;

	/**
	 * Upload form factory
	 * @return Tulinkry\Application\UI\Form
	 */
	protected function createComponentPhotoForm()
	{
		return $this->uploadFormFactory->create(
			$this->albums->find($this->getParameter('albumId', -1)), 
			$this->photos->find($this->getParameter('photoId', -1)), 
			function ($photo) {
				$this->redirect(':Photos:Download:photos', array('photoId' => $photo->id));
			}
		);
	}
}
