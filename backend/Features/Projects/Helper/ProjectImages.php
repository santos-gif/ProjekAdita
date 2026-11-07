<?php

declare(strict_types=1);

namespace Features\Projects\Helper;

use Features\Projects\Model\AddProjectInput;
use Features\Projects\Model\Project;
use Shared\Helpers\ImageUpload;
use Shared\Stores\ImageStore;

/** Deciding which thumbnail a submitted project form ends up with. */
final class ProjectImages
{
	public function __construct(private ImageStore $store = new ImageStore()) {}

	/**
	 * A form that was loaded from the draft sends the draft's image URL back.
	 * Then the new project takes that image over. Returns that URL, or null when it does not apply.
	 */
	public function draftImageToKeep(AddProjectInput $in, ?Project $draft): ?string
	{
		if ($draft === null) {
			return null;
		}
		if ($in->thumbnailUrl === null) {
			return null;
		}
		if ($in->thumbnailUrl !== $draft->thumbnailUrl) {
			return null;
		}
		return $draft->thumbnailUrl;
	}

	/**
	 * The thumbnail URL to store. A new image is checked and written now (a bad one is a 422 before anything is saved);
	 * null removes it; otherwise the current one stays. The caller deletes the replaced file once the database agrees.
	 */
	public function resolve(AddProjectInput $in, ?string $current): ?string
	{
		if ($in->thumbnailData !== null) {
			return ImageUpload::save($this->store, $in->thumbnailData, 'thumbnail', 'Thumbnail');
		}
		if ($in->thumbnailClear) {
			return null;
		}
		return $current;
	}
}
