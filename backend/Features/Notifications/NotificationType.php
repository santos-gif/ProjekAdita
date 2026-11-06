<?php

declare(strict_types=1);

namespace Features\Notifications;

/**
 * The kinds of notification. notifications.type is a plain string column, so a new kind is a new constant here.
 * The frontend picks its icon and link from the type and the project_id.
 */
final class NotificationType
{
	// Project review
	public const PROJECT_SUBMITTED = 'project_submitted';          // to moderators
	public const PROJECT_MODERATED = 'project_moderated';          // moderator approved: to admins and the owner
	public const PROJECT_PUBLISHED = 'project_published';          // to the owner
	public const PROJECT_REJECTED = 'project_rejected';            // to the owner
	public const PROJECT_TAKEN_DOWN = 'project_taken_down';        // to the owner and the members

	// Applications and team
	public const APPLICATION_RECEIVED = 'application_received';    // to the owner and the managers
	public const APPLICATION_ACCEPTED = 'application_accepted';    // to the applicant
	public const APPLICATION_DECLINED = 'application_declined';    // to the applicant
	public const APPLICATION_BANNED = 'application_banned';        // to the applicant
	public const PROMOTION_REQUESTED = 'promotion_requested';      // to the owner
	public const PROMOTION_DECIDED = 'promotion_decided';          // to the manager who asked
	public const MEMBER_ROLE_CHANGED = 'member_role_changed';      // to the member whose role changed
	public const MEMBER_REMOVED = 'member_removed';                // to the removed member
	public const MEMBER_LEFT = 'member_left';                      // to the owner and the managers
	public const OWNERSHIP_TRANSFERRED = 'ownership_transferred';  // to the old and the new owner

	// Reports
	public const PROJECT_REPORTED = 'project_reported';            // to moderators
	public const REPORT_FORWARDED = 'report_forwarded';            // to admins

	// Account
	public const PASSWORD_CHANGED = 'password_changed';
	public const ACCOUNT_ROLE_CHANGED = 'account_role_changed';
}
