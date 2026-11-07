<?php

declare(strict_types=1);

namespace Features\Auth\Controller;

use Core\Auth;
use Core\Csrf;
use Core\Mailer;
use Core\Request;
use Core\Response;
use Core\Session;
use Features\Auth\Model\LoginInput;
use Features\Auth\Model\RegisterInput;
use Features\Auth\Model\ResetConfirmInput;
use Features\Auth\Model\ResetInput;
use Features\Auth\Repository\PasswordResetRepository;
use Features\Auth\Repository\RateLimitRepository;
use Features\Notifications\NotificationType;
use Features\Notifications\Repository\NotificationRepository;
use Features\User\Repository\UserRepository;

final class AuthController
{
	// Rate limits (see RateLimitRepository): attempts allowed per window, in seconds.
	private const LOGIN_MAX_ATTEMPTS = 3;
	private const LOGIN_LOCKOUT_SECONDS = 180;
	private const RESET_MAX_ATTEMPTS = 3;
	private const RESET_LOCKOUT_SECONDS = 900;
	/** A new reset code is not sent while the previous one is younger than this. */
	private const RESET_COOLDOWN = 60;

	private static ?string $dummyHash = null;

	public function __construct(
		private UserRepository $users = new UserRepository(),
		private RateLimitRepository $limits = new RateLimitRepository(),
		private PasswordResetRepository $resets = new PasswordResetRepository(),
		private NotificationRepository $notifications = new NotificationRepository(),
	) {}

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
		if (strlen($in->email) > 190) {
			Response::fail(401, 'Email atau kata sandi salah.');
		}
		$ip = Request::ip();
		if (!$this->limits->lock("login|{$ip}|{$in->email}")) {
			Response::fail(429, 'Terlalu banyak permintaan. Coba lagi sebentar lagi.');
		}
		$wait = $this->limits->lockedFor('login', $ip, $in->email, self::LOGIN_MAX_ATTEMPTS);
		if ($wait !== null) {
			header('Retry-After: ' . $wait);
			Response::fail(429, 'Terlalu banyak percobaan masuk. Coba lagi dalam ' . (int) ceil($wait / 60) . ' menit.');
		}
		$found = $this->users->findByEmail($in->email);
		// Dummy hash is verified when the email is unknown, so timing does not reveal which emails exist.
		$valid = password_verify(
			$in->password,
			$found?->passwordHash ?? (self::$dummyHash ??= password_hash('x', PASSWORD_DEFAULT))
		);
		if ($found === null || !$valid) {
			// Unknown emails count too, so the lockout does not reveal which emails exist.
			$this->limits->hit('login', $ip, $in->email, self::LOGIN_MAX_ATTEMPTS, self::LOGIN_LOCKOUT_SECONDS);
			Response::fail(401, 'Email atau kata sandi salah.');
		}
		$this->limits->clear('login', $ip, $in->email);
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
			Response::fail(401, 'Sesi tidak valid atau telah berakhir.');
		}
		Response::ok(['user' => $this->users->findPublicById($id)?->toArray()]);
	}

	/** Step 1 of a password reset: email a 6-digit code. The answer is the same whether or not the email exists. */
	public function reset(): void
	{
		$in = ResetInput::fromRequest(Request::body());
		$errors = $in->errors();
		if ($errors) {
			Response::fail(422, 'Data tidak valid.', $errors);
		}
		$ip = Request::ip();
		// Per email, not per IP: the 60 s cooldown must hold even when the requests come from many addresses.
		if (!$this->limits->lock("reset|{$in->email}")) {
			Response::fail(429, 'Terlalu banyak permintaan. Coba lagi sebentar lagi.');
		}
		$wait = $this->limits->lockedFor('reset', $ip, $in->email, self::RESET_MAX_ATTEMPTS);
		if ($wait !== null) {
			header('Retry-After: ' . $wait);
			Response::fail(429, 'Terlalu banyak permintaan. Coba lagi dalam ' . (int) ceil($wait / 60) . ' menit.');
		}
		// Every request counts, found or not, so the limit does not reveal which emails exist.
		$this->limits->hit('reset', $ip, $in->email, self::RESET_MAX_ATTEMPTS, self::RESET_LOCKOUT_SECONDS);

		$found = $this->users->findByEmail($in->email);
		if ($found !== null && !$this->resets->createdWithin($found->user->id, self::RESET_COOLDOWN)) {
			$code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
			$this->resets->save($found->user->id, password_hash($code, PASSWORD_DEFAULT));
			try {
				Mailer::send(
					$found->user->email,
					'Kode reset kata sandi',
					"Kode reset kata sandi Anda: {$code}\n\nKode berlaku 10 menit. Abaikan email ini jika Anda tidak memintanya."
				);
			} catch (\Throwable $e) {
				// Logged only: a visible failure would reveal that the email is registered.
				error_log('Reset mail failed: ' . get_class($e) . ': ' . $e->getMessage());
			}
			// shortcut: a real account takes longer to answer (hash + mail) than an unknown email; move the
			// mail to after the response if that timing ever matters.
		}
		Response::ok(null, 200, 'Jika email terdaftar, kode reset telah dikirim.');
	}

	/** Step 2: check the code, set the new password, log the user out everywhere. */
	public function resetConfirm(): void
	{
		$in = ResetConfirmInput::fromRequest(Request::body());
		$errors = $in->errors();
		if ($errors) {
			Response::fail(422, 'Data tidak valid.', $errors);
		}
		$found = $this->users->findByEmail($in->email);
		$hash = $found === null ? null : $this->resets->consumeAttempt($found->user->id);
		// With nothing to check, a dummy hash is verified anyway so the answer takes the same time.
		$valid = password_verify($in->code, $hash ?? (self::$dummyHash ??= password_hash('x', PASSWORD_DEFAULT)));
		if ($found === null || $hash === null || !$valid) {
			Response::fail(422, 'Kode salah atau kedaluwarsa.', ['code' => 'Kode salah atau kedaluwarsa.']);
		}
		$this->users->updateHash($found->user->id, password_hash($in->newPassword, PASSWORD_DEFAULT));
		$this->resets->delete($found->user->id);
		Session::destroyAllFor($found->user->id);
		$this->notifications->add(
			$found->user->id,
			NotificationType::PASSWORD_CHANGED,
			'Kata sandi akun Anda diatur ulang. Jika ini bukan Anda, segera hubungi admin.',
		);
		Response::ok(null, 200, 'Kata sandi berhasil diubah. Silakan masuk.');
	}
}
