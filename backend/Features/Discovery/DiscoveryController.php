<?php

declare(strict_types=1);

namespace Features\Discovery;

use Core\Auth;
use Core\Response;
use Features\Discovery\Model\ProjectFilter;
use Features\Projects\MemberRepository;

/** Finding projects: the search, a project's detail, and the projects I joined. Everything needs a login. */
final class DiscoveryController
{
	public function __construct(
		private DiscoveryRepository $discovery = new DiscoveryRepository(),
		private MemberRepository $members = new MemberRepository(),
	) {}

	/** GET /projects?q=&tags=&selection_method=&open=1&study_program=&sort=&page=&per_page= : published projects only. */
	public function index(): void
	{
		$uid = Auth::requireUserId();
		$filter = ProjectFilter::fromQuery($_GET);

		$result = $this->discovery->search($uid, $filter);

		$list = [];
		foreach ($result['items'] as $view) {
			$list[] = $view->toArray();
		}
		Response::ok([
			'projects' => $list,
			'total' => $result['total'],
			'page' => $filter->page,
			'per_page' => $filter->perPage,
		]);
	}

	/**
	 * GET /projects/{id}: a published project is visible to every logged-in user; a project that is not published
	 * (pending, rejected) only to the people on it. Everyone else gets a 404, which does not reveal that it exists.
	 */
	public function show(int $id): void
	{
		$uid = Auth::requireUserId();

		$view = $this->discovery->find($id, $uid);
		if ($view === null) {
			$this->notFound();
		}
		if ($view->project->status !== 'published' && $view->myRole === null) {
			$this->notFound();
		}

		$members = [];
		foreach ($this->members->listFor($id) as $member) {
			$members[] = $member->toArray();
		}
		Response::ok(['project' => $view->toArray(), 'members' => $members]);
	}

	/** GET /projects/joined: "Projek yang diikuti": where I am a manager or a member. */
	public function joined(): void
	{
		$uid = Auth::requireUserId();

		$list = [];
		foreach ($this->discovery->joined($uid) as $view) {
			$list[] = $view->toArray();
		}
		Response::ok(['projects' => $list]);
	}

	private function notFound(): never
	{
		Response::fail(404, 'Proyek tidak ditemukan.');
	}
}
