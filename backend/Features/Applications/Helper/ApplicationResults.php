<?php

declare(strict_types=1);

namespace Features\Applications\Helper;

use Core\Response;
use Features\Applications\Model\ApplyResult;
use Features\Applications\Model\DecisionResult;
use Shared\Helpers\Fail;

/** Turns what the repository reported about an application into the error that fits; a good result lets the request go on. */
final class ApplicationResults
{
	/** Answers with the error that fits when the application did not go through. */
	public static function failUnlessApplied(ApplyResult $result): void
	{
		if ($result === ApplyResult::AlreadyMember) {
			Response::fail(409, 'Anda sudah menjadi anggota proyek ini.');
		}
		if ($result === ApplyResult::AlreadyPending) {
			Response::fail(409, 'Lamaran Anda masih menunggu keputusan.');
		}
		if ($result === ApplyResult::Banned) {
			Response::fail(403, 'Anda tidak dapat melamar ke proyek ini.');
		}
		if ($result === ApplyResult::Closed) {
			Response::fail(409, 'Proyek ini sedang tidak menerima lamaran.');
		}
		if ($result === ApplyResult::NotAvailable) {
			Fail::notFound('Proyek');
		}
	}

	/** Answers with the error that fits when an accept or a decline did not go through. */
	public static function failUnlessDone(DecisionResult $result): void
	{
		if ($result === DecisionResult::AlreadyDecided) {
			Response::fail(409, 'Lamaran ini sudah diputuskan atau ditarik.');
		}
		if ($result === DecisionResult::NotAvailable) {
			Response::fail(409, 'Proyek tidak sedang dipublikasikan.');
		}
	}
}
