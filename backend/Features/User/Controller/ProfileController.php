<?php

declare(strict_types=1);

namespace Features\User\Controller;

use Core\Auth;
use Core\Request;
use Core\Response;
use Features\User\Model\ProfileInput;
use Features\User\Repository\ProfileRepository;
use Shared\Helpers\Fail;
use Shared\Helpers\ImageUpload;
use Shared\Stores\ImageStore;

/** Profiles: anyone logged in can read anyone's public profile; you change only your own. */
final class ProfileController
{
	/** Room for a 2 MB picture as base64 (about 2.7 MB) plus the text fields. */
	private const BODY_LIMIT = 3500000;

	public function __construct(
		private ProfileRepository $profiles = new ProfileRepository(),
		private ImageStore $pictures = new ImageStore(),
	) {}

	/** GET /users/{id}: the public profile (no email, phone or ID number), with the published projects of this person. */
	public function show(int $id): void
	{
		Auth::requireUserId();

		$profile = $this->profiles->find($id);
		if ($profile === null) {
			Fail::notFound('Pengguna');
		}
		Response::ok(['profile' => $profile->toPublicArray()]);
	}

	/** GET /users/me: my own profile, with the private fields. */
	public function me(): void
	{
		$uid = Auth::requireUserId();

		$profile = $this->profiles->find($uid);
		if ($profile === null) {
			Response::fail(401, 'Silakan masuk terlebih dahulu.');
		}
		Response::ok(['profile' => $profile->toOwnArray()]);
	}

	/** PATCH /users/me: change my profile. Only the keys that are sent change; "" clears an optional field. */
	public function update(): void
	{
		$uid = Auth::requireUserId();

		$in = ProfileInput::fromRequest(Request::body(self::BODY_LIMIT));
		$errors = $in->errors();
		if ($errors !== []) {
			Response::fail(422, 'Data tidak valid.', $errors);
		}

		$before = $this->profiles->find($uid);
		if ($before === null) {
			Response::fail(401, 'Silakan masuk terlebih dahulu.');
		}

		$changes = $in->changes;
		$newPicture = null;
		if ($in->avatarData !== null) {
			$newPicture = ImageUpload::save($this->pictures, $in->avatarData, 'avatar', 'Foto');
			$changes['avatar_url'] = $newPicture;
		} elseif ($in->avatarClear) {
			$changes['avatar_url'] = null;
		}

		try {
			$saved = $this->profiles->update($uid, $changes);
		} catch (\Throwable $e) {
			$this->pictures->remove($newPicture);
			throw $e;
		}
		if (!$saved) {
			$this->pictures->remove($newPicture);
			Response::fail(409, 'NIM/NIDN ini sudah terdaftar pada akun lain.');
		}

		// The old picture is not needed any more once the database agrees.
		if (array_key_exists('avatar_url', $changes)) {
			$this->pictures->remove($before->avatarUrl);
		}

		$after = $this->profiles->find($uid);
		if ($after === null) {
			Response::fail(401, 'Silakan masuk terlebih dahulu.');
		}
		Response::ok(['profile' => $after->toOwnArray()], 200, 'Profil disimpan.');
	}
}
