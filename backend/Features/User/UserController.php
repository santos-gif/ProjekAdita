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
		if ($errors) {
			Response::fail(401, "Invalid Inputs", $errors);
		}
		$id = Auth::userId();

		$found = $this->users->findById($id);
	}
	public function getUser(): void
	{
		$id = Auth::userId();
		if ($id === null || !is_int($id)) {
			Response::fail(401, "Invalid user ID");
		}
		$publicUser = $this->users->findPublicById($id);
		if ($publicUser === null) {
			Response::fail(404, "User not found");
		}
		Response::ok($publicUser, 200, "Successfully retrieved user");
	}
}
