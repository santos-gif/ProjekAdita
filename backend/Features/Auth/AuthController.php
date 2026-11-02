<?php

declare(strict_types=1);

namespace Features\Auth;

use Core\Auth;
use Core\Csrf;
use Core\Response;
use Core\Request;
use Features\Auth\Model\LoginInput;
use Features\Auth\Model\RegisterInput;
use Features\Auth\Model\ResetInput;
use Features\User\UserRepository;

final class AuthController
{
	private static ?string $dummyHash = null;

	public function __construct(private UserRepository $users = new UserRepository()) {}

	public function register(): void
	{
		$in = RegisterInput::fromRequest(Request::body());
		$errors = $in->errors();
		if ($errors) {
			Response::fail(422, 'Data tidak valid.', $errors);
		}

		$id = $this->users->create($in->name, $in->email, password_hash($in->password, PASSWORD_DEFAULT));
		if ($id === null) {
			Response::fail(409, 'Email sudah terdaftar.');
		}
		Auth::login($id, false);
		Response::ok(['user' => $this->users->findPublicById($id)?->toArray()], 201, 'Registrasi berhasil.');
	}

	public function login(): void
	{
		$in = LoginInput::fromRequest(Request::body());
		$found = $this->users->findByEmail($in->email);
		// Dummy hash is verified when the email is unknown, so timing does not reveal which emails exist.
		$valid = password_verify(
			$in->password,
			$found?->passwordHash ?? (self::$dummyHash ??= password_hash('x', PASSWORD_DEFAULT))
		);
		if ($found === null || !$valid) {
			Response::fail(401, 'Email atau kata sandi salah.');
		}
		if (password_needs_rehash($found->passwordHash, PASSWORD_DEFAULT)) {
			$this->users->updateHash($found->user->id, password_hash($in->password, PASSWORD_DEFAULT));
		}
		Auth::login($found->user->id, $in->remember);
		Response::ok(['user' => $found->user->toArray()]);
	}

	public function logout(): void
	{
		Auth::logout();
		Response::ok(['token' => Csrf::token()], 200, 'Berhasil keluar.');
	}

	public function me(): void
	{
		// No cookie = anonymous visitor: do not create a session for them.
		$id = Auth::userId();
		if ($id === null) {
			Response::fail(401, "User ID Invalid");
		}
		Response::ok(['user' => $id === null ? null : $this->users->findPublicById($id)?->toArray()]);
	}
	public function reset(): void
	{
		/** Receives Email, generate 6 digit code, send code to email
		 *  
		 */
		$in = ResetInput::fromRequest(Request::body());
		$errors = $in->errors();
		if ($errors) {
			Response::fail(422, 'Data tidak valid.', $errors);
		}
	}
}
