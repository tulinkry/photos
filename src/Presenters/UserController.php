<?php

namespace Tulinkry\Photos\Presenters;

use Tulinkry\Application\UI\Presenter;

use Tulinkry\Photos\Services\IUserProvider;

use Tulinkry\Photos\Forms\UserFormFactory;

class UserController extends Presenter
{
	/**
	 * @var UserFormFactory
	 * @inject
	 */
	public $userFormFactory;

	/**
	 * @var IUserProvider
	 * @inject
	 */
	public $users;

	/**
	 * User form factory
	 * @return Tulinkry\Application\UI\Form
	 */
	protected function createComponentUserForm()
	{
		return $this->userFormFactory->create(
			$this->users->find($this->getParameter('userId', -1)), 
			function ($user) {
				$this->redirect(':Photos:Download:albums', array('userId' => $user->id));
			}
		);
	}
}
