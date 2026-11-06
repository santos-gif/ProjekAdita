<?php

declare(strict_types=1);

use Core\Csrf;
use Core\Db;
use Core\Response;
use Core\Router;
use Features\Admin\AdminController;
use Features\Applications\ApplicationController;
use Features\Auth\AuthController;
use Features\Dashboard\DashboardController;
use Features\Discovery\DiscoveryController;
use Features\Notifications\NotificationController;
use Features\Projects\ProjectController;
use Features\Projects\TeamController;
use Features\Reports\ReportController;
use Features\Review\ReviewController;
use Features\Tags\TagController;
use Features\User\ProfileController;
use Features\User\UserController;

// Namespace root -> folder under backend/ (Core\Db => Core/Db.php); no Composer.
spl_autoload_register(static function (string $class): void {
	$file = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';
	if (preg_match('/^(Core|Features)\\\\/', $class) && is_file($file)) {
		require $file;
	}
});

ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
error_reporting(E_ALL);
set_error_handler(static function (int $no, string $msg, string $file, int $line): bool {
	if (!(error_reporting() & $no)) {
		return false;
	}
	throw new ErrorException($msg, 0, $no, $file, $line);
});
set_exception_handler(static function (Throwable $e): void {
	error_log(get_class($e) . ': ' . $e->getMessage());
	if (!headers_sent()) {
		Response::fail(500, 'Terjadi kesalahan pada server.');
	}
});

$auth = new AuthController();
$router = new Router();
$router->add('GET', '/csrf', static fn() => Response::ok(['token' => Csrf::token()]));
$router->add('GET', '/health', static function (): void {
	try {
		Db::pdo()->query('SELECT 1');
	} catch (PDOException $e) {
		error_log(get_class($e) . ': ' . $e->getMessage());
		Response::fail(503, 'Database tidak tersedia.');
	}
	Response::ok(['db' => 'ok']);
});
$router->add('POST', '/auth/register', [$auth, 'register']);
$router->add('POST', '/auth/login', [$auth, 'login']);
$router->add('POST', '/auth/logout', [$auth, 'logout']);
$router->add('GET', '/auth/me', [$auth, 'me']);
$router->add('POST', '/auth/reset', [$auth, 'reset']);
$router->add('POST', '/auth/reset/confirm', [$auth, 'resetConfirm']);

$tags = new TagController();
$router->add('GET', '/tags', [$tags, 'index']);

// "/projects/draft", "/projects/mine" and "/projects/joined" are exact paths, so they win over "/projects/{id}".
// The owner's side of a project: submit, edit, withdraw, my list, my draft.
$projects = new ProjectController();
$router->add('GET', '/projects/mine', [$projects, 'mine']);
$router->add('GET', '/projects/draft', [$projects, 'draft']);
$router->add('PUT', '/projects/draft', [$projects, 'saveDraft']);
$router->add('POST', '/projects', [$projects, 'create']);
$router->add('PATCH', '/projects/{id}', [$projects, 'update']);
$router->add('DELETE', '/projects/{id}', [$projects, 'destroy']);

// Finding projects: the search, a project's detail, the projects I joined.
$discovery = new DiscoveryController();
$router->add('GET', '/projects', [$discovery, 'index']);
$router->add('GET', '/projects/joined', [$discovery, 'joined']);
$router->add('GET', '/projects/{id}', [$discovery, 'show']);

// Joining a project: applications (the owner and the managers decide).
$applications = new ApplicationController();
$router->add('POST', '/projects/{id}/applications', [$applications, 'apply']);
$router->add('GET', '/projects/{id}/applications', [$applications, 'forProject']);
$router->add('GET', '/applications/mine', [$applications, 'mine']);
$router->add('POST', '/applications/{id}/accept', [$applications, 'accept']);
$router->add('POST', '/applications/{id}/reject', [$applications, 'reject']);
$router->add('DELETE', '/applications/{id}', [$applications, 'withdraw']);

// The team of a published project.
$team = new TeamController();
$router->add('GET', '/projects/{id}/members', [$team, 'members']);
$router->add('PATCH', '/projects/{id}/members/{userId}', [$team, 'changeMember']);
$router->add('DELETE', '/projects/{id}/members/{userId}', [$team, 'removeMember']);
$router->add('POST', '/projects/{id}/members/{userId}/promotion-requests', [$team, 'requestPromotion']);
$router->add('GET', '/projects/{id}/promotion-requests', [$team, 'promotionRequests']);
$router->add('POST', '/promotion-requests/{id}/approve', [$team, 'approvePromotion']);
$router->add('POST', '/promotion-requests/{id}/deny', [$team, 'denyPromotion']);
$router->add('POST', '/projects/{id}/leave', [$team, 'leave']);
$router->add('POST', '/projects/{id}/step-down', [$team, 'stepDown']);
$router->add('POST', '/projects/{id}/transfer-ownership', [$team, 'transfer']);
$router->add('PATCH', '/projects/{id}/applications-open', [$team, 'setApplicationsOpen']);

// Review: moderators (and admins) first, then admins.
$review = new ReviewController();
$router->add('GET', '/moderation/projects', [$review, 'moderationQueue']);
$router->add('GET', '/moderation/projects/{id}', [$review, 'moderationShow']);
$router->add('POST', '/moderation/projects/{id}/review', [$review, 'moderationReview']);
$router->add('GET', '/moderation/history', [$review, 'moderationHistory']);

$router->add('GET', '/admin/projects', [$review, 'adminQueue']);
$router->add('GET', '/admin/projects/{id}', [$review, 'adminShow']);
$router->add('POST', '/admin/projects/{id}/review', [$review, 'adminReview']);
$router->add('POST', '/admin/projects/{id}/takedown', [$review, 'takedown']);

// Reports: any user reports a published project; moderators handle them first, then admins.
$reports = new ReportController();
$router->add('POST', '/projects/{id}/reports', [$reports, 'create']);
$router->add('GET', '/moderation/reports', [$reports, 'moderationList']);
$router->add('POST', '/moderation/reports/{id}/resolve', [$reports, 'moderationResolve']);
$router->add('GET', '/admin/reports', [$reports, 'adminList']);
$router->add('POST', '/admin/reports/{id}/resolve', [$reports, 'adminResolve']);

// Admin: people and the overview.
$admin = new AdminController();
$router->add('GET', '/admin/users', [$admin, 'users']);
$router->add('PATCH', '/admin/users/{id}/role', [$admin, 'changeRole']);
$router->add('GET', '/admin/stats', [$admin, 'stats']);

// Profiles and my account. "/users/me" is an exact path, so it wins over "/users/{id}".
$profiles = new ProfileController();
$router->add('GET', '/users/me', [$profiles, 'me']);
$router->add('PATCH', '/users/me', [$profiles, 'update']);
$router->add('GET', '/users/{id}', [$profiles, 'show']);
$user = new UserController();
$router->add('POST', '/users/me/password', [$user, 'changePassword']);

$dashboard = new DashboardController();
$router->add('GET', '/dashboard', [$dashboard, 'index']);

$notifications = new NotificationController();
$router->add('GET', '/notifications', [$notifications, 'index']);
$router->add('POST', '/notifications/read-all', [$notifications, 'readAll']);
$router->add('POST', '/notifications/{id}/read', [$notifications, 'read']);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'] ?? '/');
