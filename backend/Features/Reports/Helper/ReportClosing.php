<?php

declare(strict_types=1);

namespace Features\Reports\Helper;

use Core\Response;
use Features\Reports\Model\Report;
use Features\Reports\Repository\ReportRepository;
use Features\User\Model\User;

/** The checks and the status change behind dismissing or forwarding a report. */
final class ReportClosing
{
	public function __construct(private ReportRepository $reports = new ReportRepository()) {}

	/** Nobody judges their own case: not a report about their own project, and not a report they filed. */
	public static function refuseOwnCase(Report $report, User $handler): void
	{
		if ($report->projectOwnerId === $handler->id) {
			Response::fail(403, 'Anda tidak dapat menangani laporan tentang proyek Anda sendiri.');
		}
		if ($report->reporterId === $handler->id) {
			Response::fail(403, 'Anda tidak dapat menangani laporan yang Anda buat sendiri.');
		}
	}

	/**
	 * Closes the report, but only if it is still in one of the $from statuses.
	 * When someone else got there first it is a 409; otherwise this method simply returns.
	 * @param list<string> $from
	 */
	public function close(int $id, array $from, string $to, int $handlerId, ?string $note): void
	{
		$closed = $this->reports->resolve($id, $from, $to, $handlerId, $note);
		if (!$closed) {
			Response::fail(409, 'Laporan ini sudah ditangani atau tidak berada pada tahap yang sesuai.');
		}
	}
}
