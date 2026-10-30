<?php

declare(strict_types=1);

namespace Features\Auth;

use Core\Auth;
use Core\Csrf;
use Core\Response;

final class AuthController
{
    private static ?string $dummyHash = null;

    public function __construct(private UserRepository $users = new UserRepository())
    {
    }

    public function register(): void
    {
        $in = Response::body();
        $name = is_string($in['name'] ?? null) ? trim($in['name']) : '';
        $email = is_string($in['email'] ?? null) ? strtolower(trim($in['email'])) : '';
        $password = is_string($in['password'] ?? null) ? $in['password'] : '';
        $confirm = is_string($in['confirmPassword'] ?? null) ? $in['confirmPassword'] : '';

        $errors = [];
        $len = mb_strlen($name);
        if ($len < 2 || $len > 80) {
            $errors['name'] = 'Nama harus 2-80 karakter.';
        }
        if (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email tidak valid.';
        }
        // bcrypt only uses the first 72 bytes, hence a byte-length cap.
        if (strlen($password) < 8 || strlen($password) > 72) {
            $errors['password'] = 'Kata sandi harus 8-72 karakter.';
        }
        if (!hash_equals($password, $confirm)) {
            $errors['confirmPassword'] = 'Konfirmasi kata sandi tidak cocok.';
        }
        if ($errors) {
            Response::fail(422, 'Data tidak valid.', $errors);
        }

        $id = $this->users->create($name, $email, password_hash($password, PASSWORD_DEFAULT));
        if ($id === null) {
            Response::fail(409, 'Email sudah terdaftar.');
        }
        Auth::login($id, false);
        Response::ok(['user' => $this->users->findPublicById($id)], 201, 'Registrasi berhasil.');
    }

    public function login(): void
    {
        $in = Response::body();
        $email = is_string($in['email'] ?? null) ? strtolower(trim($in['email'])) : '';
        $password = is_string($in['password'] ?? null) ? $in['password'] : '';

        $user = $this->users->findByEmail($email);
        // Dummy hash is verified when the email is unknown, so timing does not reveal which emails exist.
        $valid = password_verify($password, $user['password_hash'] ?? (self::$dummyHash ??= password_hash('x', PASSWORD_DEFAULT)));
        if (!$user || !$valid) {
            Response::fail(401, 'Email atau kata sandi salah.');
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $this->users->updateHash((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }
        Auth::login((int) $user['id'], ($in['remember'] ?? false) === true);
        unset($user['password_hash']);
        Response::ok(['user' => $user]);
    }

    public function logout(): void
    {
        Auth::logout();
        Response::ok(['token' => Csrf::token()], 200, 'Berhasil keluar.');
    }

    public function me(): void
    {
        // No cookie = anonymous visitor: do not create a session for them.
        $id = isset($_COOKIE[session_name()]) ? Auth::userId() : null;
        Response::ok(['user' => $id === null ? null : $this->users->findPublicById($id)]);
    }

    public function reset(): void
    {
        Response::fail(501, 'Reset kata sandi belum tersedia.');
    }
}
