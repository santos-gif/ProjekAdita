<?php

declare(strict_types=1);

namespace Features\User;

use Core\Auth;
use Core\Request;
use Core\Response;
use Core\Session;
use Features\Notifications\NotificationRepository;
use Features\Notifications\NotificationType;
use Features\User\Model\ChangePasswordInput;


final class UserController
{
	public function __construct(
		private UserRepository $users = new UserRepository(),
		private NotificationRepository $notifications = new NotificationRepository(),
	) {}

	/**
	 * POST /users/me/password {currentPassword, newPassword, confirmNewPassword}
	 * The other devices are logged out (this one stays) and the user gets a security notification.
	 */
	public function changePassword(): void
	{
		$id = Auth::requireUserId();

		$in = ChangePasswordInput::fromRequest(Request::body());
		$errors = $in->errors();
		if ($errors !== []) {
			Response::fail(422, 'Data tidak valid.', $errors);
		}

		$found = $this->users->findById($id);
		if ($found === null) {
			Response::fail(401, 'Silakan masuk terlebih dahulu.');
		}
		if (!password_verify($in->currentPassword, $found->passwordHash)) {
			Response::fail(422, 'Data tidak valid.', ['currentPassword' => 'Kata sandi sekarang salah.']);
		}

		$this->users->updateHash($id, password_hash($in->newPassword, PASSWORD_DEFAULT));
		Session::destroyOthersFor($id);
		$this->notifications->add(
			$id,
			NotificationType::PASSWORD_CHANGED,
			'Kata sandi akun Anda diubah. Jika ini bukan Anda, segera atur ulang kata sandi dan hubungi admin.',
		);
		Response::ok(null, 200, 'Kata sandi berhasil diubah.');
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
