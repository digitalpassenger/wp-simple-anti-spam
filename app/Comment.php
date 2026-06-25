<?php
/**
 * WordPress comment spam integration.
 *
 * @package WPSimpleAntiSpam
 */

namespace WPSimpleAntiSpam;

/**
 * Handles WordPress comment spam validation.
 */
class Comment {

	/**
	 * Register comment spam hooks.
	 */
	public function __construct() {
		add_filter( 'pre_comment_approved', array( $this, 'pre_comment_approved' ), 10, 2 );
	}

	/**
	 * Filter comment approval before it is saved.
	 *
	 * @param mixed $approved    One of 1, 0, 'spam', 'trash', WP_Error.
	 * @param array $commentdata {
	 *     Comment data.
	 *
	 *     @type string $comment_author       The name of the comment author.
	 *     @type string $comment_author_email The comment author email address.
	 *     @type string $comment_author_url   The comment author URL.
	 *     @type string $comment_content      The content of the comment.
	 *     @type string $comment_date         The date the comment was submitted. Default is the current time.
	 *     @type string $comment_date_gmt     The date the comment was submitted in the GMT timezone.
	 *                                        Default is `$comment_date` in the GMT timezone.
	 *     @type string $comment_type         Comment type. Default 'comment'.
	 *     @type int    $comment_parent       The ID of this comment's parent, if any. Default 0.
	 *     @type int    $comment_post_ID      The ID of the post that relates to the comment.
	 *     @type int    $user_id              The ID of the user who submitted the comment. Default 0.
	 *     @type int    $user_ID              Kept for backward-compatibility. Use `$user_id` instead.
	 *     @type string $comment_agent        Comment author user agent. Default is the value of 'HTTP_USER_AGENT'
	 *                                        in the `$_SERVER` superglobal sent in the original request.
	 *     @type string $comment_author_IP    Comment author IP address in IPv4 format. Default is the value of
	 *                                        'REMOTE_ADDR' in the `$_SERVER` superglobal sent in the original request.
	 * }
	 */
	public function pre_comment_approved( $approved, $commentdata ) {
		if ( is_wp_error( $approved ) || 'spam' === $approved || 'trash' === $approved ) {
			return $approved;
		}

		if ( $this->is_spam_comment( $commentdata ) ) {
			return new \WP_Error( 'Sorry, your comment was flagged as spam.' );
		}

		return $approved;
	}

	/**
	 * Determine whether comment data looks like spam.
	 *
	 * @param array $commentdata Comment data passed to pre_comment_approved.
	 */
	public function is_spam_comment( array $commentdata ): bool {

		$check = new Check();

		return (
			// All comments without HTTP User-Agent header: spam.
			empty( $commentdata['comment_agent'] )

			// No author name: spam.
			|| empty( $commentdata['comment_author'] )

			// If email looks like "xyz@yahoo.com" and author url contains an URL, probably spam.
			|| $check->email_and_author_url( $commentdata['comment_author_email'], $commentdata['comment_author_url'] )

			// If URL is given check
			|| $check->url( $commentdata['comment_author_url'] )

			// Comment content checks (russian char, no space, digits only, buy+link, greeting pattern).
			|| $check->text( $commentdata['comment_content'] )

			// If comment author consists of only digits.
			|| $check->is_only_digits( $commentdata['comment_author'] )
		);
	}
}
