<?php

declare(strict_types=1);

namespace Shared\Helpers;

use Core\Response;
use Shared\Stores\ImageStore;

/** Saving an image that arrived as a data URL in a JSON field (a project thumbnail, a profile picture). */
final class ImageUpload
{
	/**
	 * Checks the image and writes it. A bad one is a 422 on that field, before anything is saved.
	 * @param string $field the JSON field the error belongs to, for example "thumbnail"
	 * @param string $label what the message calls the image, for example "Thumbnail" or "Foto"
	 * @return string the URL of the file just written
	 */
	public static function save(ImageStore $store, string $dataUrl, string $field, string $label): string
	{
		$image = $store->decode($dataUrl);
		if ($image === null) {
			Response::fail(422, 'Data tidak valid.', [$field => $label . ' harus gambar JPG, PNG, atau WEBP (maks. 2MB).']);
		}
		return $store->write($image);
	}
}
