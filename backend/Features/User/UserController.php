<?php

declare(strict_types=1);

namespace Features\User;

use Core\Auth;
use Core\Request;
use Core\Response;
use Features\User\Model\ChangePasswordInput;


final class UserController
{
	public function __construct(private UserRepository $users = new UserRepository()) {}
	public function changePassword(): void
	{
		$in = ChangePasswordInput::fromRequest(Request::body());
		$errors = $in->errors();
		$id = Auth::userId();
		if ($id === null || !is_int($id)) {
			Response::fail(401, "Invalid user ID", $errors);
		}
		$found = $this->users->findById($id);
	}
}
