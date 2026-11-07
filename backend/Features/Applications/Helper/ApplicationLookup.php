<?php

declare(strict_types=1);

namespace Features\Applications\Helper;

use Features\Applications\Model\Application;
use Features\Applications\Repository\ApplicationRepository;
use Shared\Helpers\Fail;

/** Finding the application an endpoint is about. */
final class ApplicationLookup
{
	public function __construct(private ApplicationRepository $applications = new ApplicationRepository()) {}

	/** The application, or a 404 when there is none. */
	public function findOrFail(int $id): Application
	{
		$application = $this->applications->find($id);
		if ($application === null) {
			Fail::notFound('Lamaran');
		}
		return $application;
	}
}
