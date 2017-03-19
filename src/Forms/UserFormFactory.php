<?php

namespace Tulinkry\Photos\Forms;

use Tulinkry\Photos\Services\IUserProvider;
use Tulinkry\Application\UI\Form;
use Nette\Utils\Arrays;

class UserFormFactory
{
	/** @var FormFactory */
	private $factory;

	/** @var IUserProvider */
	private $users;


	public function __construct(FormFactory $factory, IUserProvider $users)
	{
		$this->factory = $factory;
		$this->users = $users;
	}

	public function create($user, callable $onSuccess)
	{
		$form = $this->factory->create();
		$form->addText('name', 'Název uživatele')
			 ->setRequired('Zadejte prosím název uživatele');
		$form->addEmail('email', 'Email uživatele')
			 ->setRequired('Zadejte prosím email uživatele');

		$form->addHidden('id');

		$defaults = array();

		if($user !== NULL) {
			$defaults = Arrays::mergeTree($defaults, array(
				'name' => isset($user->metadata->name) ? $user->metadata->name : NULL,
				'email' => isset($user->metadata->email) ? $user->metadata->email : NULL,
				'id' => $user->id,
			));
			$form->addSubmit('submit', 'Uložit');
		} else {
			$form->addSubmit('submit', 'Vložit');
		}

		$defaults = array_filter($defaults, function($e) { return $e !== NULL; });

		$form->setDefaults($defaults);

		$users = $this->users;


		$form->onSuccess[] = function (Form $form, $values) use ($users, $onSuccess) {
			try {
				if(!empty($values['id'])) {
					$id = $values['id'];
					unset($values['id']);
					$user = $users->update($id, $values);
				} else {
					unset($values['id']);
					$user = $users->create($values);
				}
			} catch (\Exception $e) {
				$form->addError($e->getMessage());
				return;
			}
			$onSuccess($user);
		};

		return $form;
	}

}