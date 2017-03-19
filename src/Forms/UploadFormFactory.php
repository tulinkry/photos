<?php

namespace Tulinkry\Photos\Forms;

use Tulinkry\Photos\Services\IPhotosProvider;
use Tulinkry\Photos\Services\IAlbumProvider;
use Tulinkry\Photos\Services\IContentProvider;
use Tulinkry\Application\UI\Form;
use Nette\Utils\Arrays;

class UploadFormFactory
{
	/** @var FormFactory */
	private $factory;

	/** @var IPhotosProvider */
	private $photos;

	/** @var IAlbumProvider */
	private $albums;

	/** @var IContentProvider */
	private $content;


	public function __construct(FormFactory $factory, IAlbumProvider $albums, IPhotosProvider $photos, IContentProvider $content)
	{
		$this->factory = $factory;
		$this->albums = $albums;
		$this->photos = $photos;
		$this->content = $content;
	}

	public function create($album, $photo, callable $onSuccess)
	{
		$form = $this->factory->create();

		$defaults = array();
		if ($album !== NULL) {
			$form->addMultiUpload('photos', 'Fotky')
				 ->setRequired('Musíte nahrát alespoň jednu fotku!');
			$form->addSubmit('submit', 'Nahrát');
			$defaults = Arrays::mergeTree($defaults, array(
				'album_id' => $album->id
			));
		} else if ($photo !== NULL) {
			$form->addMultiUpload('photo', 'Fotka')
				 ->setRequired('Musíte nahrát jednu fotku!');
			$form->addText('name', 'Název fotky');
			$form->addEmail('description', 'Popis fotky');
			$defaults = Arrays::mergeTree($defaults, array(
				'name' => isset($photo->metadata->name) ? $photo->metadata->name : NULL,
				'description' => isset($photo->metadata->description) ? $photo->metadata->description : NULL,
				'id' => $photo->id,
			));
			$form->addSubmit('submit', 'Uložit');
		} else {
			return null;
		}

		$form->addHidden('id');
		$form->addHidden('album_id');

		$defaults = array_filter($defaults, function($e) { return $e !== NULL; });

		$form->setDefaults($defaults);

		$photos = $this->photos;

		$that = $this;
		$form->onSuccess[] = function (Form $form, $values) use ($that, $photos, $onSuccess) {
			try {
				if(!empty($values['id'])) {
					$id = $values['id'];
					unset($values['id']);
					$photo = $photos->update($id, $values);
				} else {
					unset($values['id']);
					$that->upload($values);
				}
			} catch (\Exception $e) {
				$form->addError($e->getMessage());
				return;
			}
			$onSuccess($photo);
		};

		return $form;
	}

	public function upload($values) {
		dump($values);
		if (($album = $this->albums->find($values['album_id'])) === NULL) {
			throw new \Exception(sprintf('The album "%s" does not exist.', $values['album_id']));
		}

		foreach($values['photos'] as $i => $file) {
			if (!$file->isOk() || !$file->isImage()) {
				throw new \Exception('Unable to create the photo - malformed content');
			}
			if (($photo = $this->photos->create($album->id, $file)) === NULl) {
				throw new \Exception('Unable to create the photo - entity error');
			}
		}

		dump($photo);

		throw new \Exception("aef");
	}

}