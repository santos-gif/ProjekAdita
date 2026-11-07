<?php

declare(strict_types=1);

namespace Features\Reports\Helper;

use Features\Reports\Model\Report;
use Features\Reports\Repository\ReportRepository;
use Shared\Helpers\Fail;

/** Finding the report an endpoint is about. */
final class ReportLookup
{
	public function __construct(private ReportRepository $reports = new ReportRepository()) {}

	/** The report, or a 404 when there is none. */
	public function findOrFail(int $id): Report
	{
		$report = $this->reports->find($id);
		if ($report === null) {
			Fail::notFound('Laporan');
		}
		return $report;
	}
}
