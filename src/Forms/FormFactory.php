<?php

namespace Tulinkry\Photos\Forms;

use Tulinkry\Application\UI\Form;


class FormFactory
{
	/**
	 * @return Form
	 */
	public function create()
	{
		$form = new Form;
		return $form;
	}

}
