<?php

namespace Tulinkry\Photos\Forms;

use Tulinkry\Photos\Services\IAlbumProvider;
use Tulinkry\Application\UI\Form;
use Nette\Utils\Arrays;

class AlbumFormFactory
{
	/** @var FormFactory */
	private $factory;

	/** @var IAlbumProvider */
	private $albums;


	public function __construct(FormFactory $factory, IAlbumProvider $albums)
	{
		$this->factory = $factory;
		$this->albums = $albums;
	}

	public function create($album, callable $onSuccess)
	{
		$form = $this->factory->create();
		$form->addText('name', 'Název alba')
			 ->setRequired('Zadejte prosím název alba');
		$form->addTextArea('description', 'Popis alba')
			 ->setRequired(FALSE);

		$form->addHidden('user');
		$form->addHidden('id');

		$defaults = array(
			'user' => 1
		);

		if($album !== NULL) {
			$defaults = Arrays::mergeTree($defaults, array(
				'name' => isset($album->metadata->name) ? $album->metadata->name : NULL,
				'description' => isset($album->metadata->description) ? $album->metadata->description : NULL,
				'id' => $album->id,
			));
			$form->addSubmit('submit', 'Uložit');
		} else {
			$form->addSubmit('submit', 'Vložit');
		}

		$defaults = array_filter($defaults, function($e) { return $e !== NULL; });

		$form->setDefaults($defaults);

		$albums = $this->albums;


		$form->onSuccess[] = function (Form $form, $values) use ($albums, $onSuccess) {
			try {
				if(!empty($values['id'])) {
					$id = $values['id'];
					unset($values['id']);
					unset($values['user']);
					$album = $albums->update($id, $values);
				} else {
					$userId = $values['user'];
					unset($values['user']);
					unset($values['id']);
					$album = $albums->create($userId, $values);
				}
			} catch (\Exception $e) {
				$form->addError($e->getMessage());
				return;
			}
			$onSuccess($album);
		};

		return $form;
	}

}