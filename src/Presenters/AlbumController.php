<?php

namespace Tulinkry\Photos\Presenters;

use Tulinkry\Application\UI\Presenter;

use Tulinkry\Photos\Services\IAlbumProvider;

use Tulinkry\Photos\Forms\AlbumFormFactory;

class AlbumController extends Presenter
{
	/**
	 * @var AlbumFormFactory
	 * @inject
	 */
	public $albumFormFactory;

	/**
	 * @var IAlbumProvider
	 * @inject
	 */
	public $albums;

	/**
	 * Album form factory
	 * @return Tulinkry\Application\UI\Form
	 */
	protected function createComponentAlbumForm()
	{
		return $this->albumFormFactory->create(
			$this->albums->find($this->getParameter('albumId', -1)), 
			function ($album) {
				$this->redirect(':Photos:Download:photos', array('albumId' => $album->id));
			}
		);
	}
}