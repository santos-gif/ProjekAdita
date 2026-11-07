<?php

declare(strict_types=1);

namespace Shared\Stores;

/**
 * Uploaded images (project thumbnails, profile pictures) as files. The API container writes them to uploads/
 * (a Docker volume shared with the frontend container); Apache serves them at /uploads/<name>, and that URL is
 * what projects.thumbnail_url and users.avatar_url hold.
 * Names are random, so nobody can guess one, and the extension comes from the image's real type.
 */
final class ImageStore
{
	private const MAX_BYTES = 2097152; // 2 MB, the limit the form shows
	private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
	private const HEADS = ['data:image/jpeg;base64', 'data:image/jpg;base64', 'data:image/png;base64', 'data:image/webp;base64'];

	private string $dir;

	public function __construct(?string $dir = null)
	{
		$this->dir = $dir ?? dirname(__DIR__, 2) . '/uploads'; // /var/www/app/uploads in the container
	}

	/**
	 * Checks a "data:image/...;base64,..." string without writing anything.
	 * The bytes are inspected, because a data-URL prefix is only the sender's claim.
	 * @return array{bytes:string,ext:string}|null null when it is not a JPG/PNG/WEBP of at most 2 MB
	 */
	public function decode(string $dataUrl): ?array
	{
		$comma = strpos($dataUrl, ',');
		if ($comma === false || !in_array(substr($dataUrl, 0, $comma), self::HEADS, true)) {
			return null;
		}
		$b64 = substr($dataUrl, $comma + 1);
		if (strlen($b64) > (int) (self::MAX_BYTES * 4 / 3) + 8) {
			return null;
		}
		$bytes = base64_decode($b64, true);
		if ($bytes === false || $bytes === '' || strlen($bytes) > self::MAX_BYTES) {
			return null;
		}
		// The @ keeps a corrupt image from turning into an exception: we only want the false.
		$info = @getimagesizefromstring($bytes);
		if ($info === false) {
			return null;
		}

		$mime = $info['mime'];
		if (!isset(self::TYPES[$mime])) {
			return null;
		}
		return ['bytes' => $bytes, 'ext' => self::TYPES[$mime]];
	}

	/** @param array{bytes:string,ext:string} $image from decode() @return string the URL to store */
	public function write(array $image): string
	{
		$name = bin2hex(random_bytes(16)) . '.' . $image['ext'];
		if (file_put_contents($this->dir . '/' . $name, $image['bytes'], LOCK_EX) === false) {
			throw new \RuntimeException('Cannot write the image file.');
		}
		return '/uploads/' . $name;
	}

	/** Deletes the file behind a stored URL. Anything that is not one of our own names is ignored. */
	public function remove(?string $url): void
	{
		if ($url !== null && preg_match('#^/uploads/([a-f0-9]{32}\.(?:jpg|png|webp))$#', $url, $m) === 1) {
			@unlink($this->dir . '/' . $m[1]);
		}
	}
}
