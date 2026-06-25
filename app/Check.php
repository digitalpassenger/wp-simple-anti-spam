<?php
/**
 * Spam detection checks.
 *
 * @package WPSimpleAntiSpam
 */

namespace WPSimpleAntiSpam;

/**
 * Class that checks if data is spam.
 *
 * Every method returns TRUE if is spam, otherwise FALSE.
 */
class Check {

	/**
	 * Check whether an email and author URL combination looks like spam.
	 *
	 * @param string $email Email address.
	 * @param string $url   Author URL.
	 */
	public static function email_and_author_url( string $email, $url = '' ): bool {

		if ( self::email_is_approved( $email ) ) {
			return false;
		}

		// if email looks like "first_last@yahoo.com" and comment author url contains an URL, probably spam.
		return strlen( $email ) > 0
			&& preg_match( '/^\w+_\w+@(yahoo|gmail|hotmail|msn)\.com$/', $email )
			&& strlen( $url ) > 0;
	}

	/**
	 * Check whether an email address looks like spam.
	 *
	 * @param string $email Email address.
	 */
	public static function email( string $email ): bool {

		if ( self::email_is_approved( $email ) ) {
			return false;
		}

		if ( self::email_is_blacklisted( $email ) ) {
			return true;
		}

		if ( self::email_name_pattern( $email ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check whether an email matches the first_last provider pattern.
	 *
	 * @param string $email Email address.
	 */
	public static function email_name_pattern( string $email ): bool {
		return strlen( $email ) > 0
			&& preg_match( '/^\w+_\w+@(yahoo|gmail|hotmail|msn)\.com$/', $email );
	}

	/**
	 * Check for an already approved e-mail address.
	 *
	 * @since  2.0
	 * @since  2.5.1
	 *
	 * @param   string $email E-mail address.
	 * @return  boolean       True for a found entry.
	 */
	public static function email_is_approved( $email ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `comment_ID` FROM `$wpdb->comments` WHERE `comment_approved` = '1' AND `comment_author_email` = %s LIMIT 1",
				wp_unslash( $email )
			)
		);

		if ( $result ) {
			return true;
		}

		return false;
	}

	/**
	 * Check whether an email address is blacklisted.
	 *
	 * @param string $email Email address.
	 */
	public static function email_is_blacklisted( string $email ): bool {

		$email = strtolower( trim( $email ) );

		if ( empty( $email ) ) {
			return false;
		}

		$blacklist = apply_filters(
			'wp_simple_anti_spam/email_blacklist',
			array(
				'ericjones',
				'*@temp.*',
				'*t.me*',
				'*@spam.com',
				'/^seo.*@/',
				'/^marketing.*@/',
				'/^sales.*@/',
				'/^\d+@/', // 57370088@outlook.com digits only
			)
		);

		foreach ( $blacklist as $pattern ) {
			if ( self::email_matches_pattern( $email, $pattern ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Match email against a blacklist pattern (literal, wildcard, or regex).
	 *
	 * @param string $email   Lowercased email address.
	 * @param string $pattern Blacklist pattern.
	 */
	private static function email_matches_pattern( string $email, string $pattern ): bool {

		// Regex pattern: /.../ or /.../i.
		if ( str_starts_with( $pattern, '/' ) && strrpos( $pattern, '/', 1 ) !== false ) {
			return (bool) preg_match( $pattern, $email );
		}

		// Wildcard pattern: * and ?.
		if ( strpbrk( $pattern, '*?' ) !== false ) {
			return fnmatch( strtolower( $pattern ), $email, FNM_CASEFOLD );
		}

		// Literal substring.
		return str_contains( $email, strtolower( $pattern ) );
	}

	/**
	 * Check if field value exists in string.
	 *
	 * @param mixed $haystack  Value to search in.
	 * @param mixed $needle    Value to search for.
	 * @param int   $make_space Whether to match whole words only.
	 */
	public static function str_contains( $haystack, $needle, $make_space = 1 ) {

		// Return false if either string is empty.
		if ( ! $needle || ! $haystack ) {
			return false;
		}

		// Convert both strings to lowercase and trim whitespace.
		$needle   = strtolower( trim( $needle ) );
		$haystack = strtolower( trim( $haystack ) );

		// If make_space is 1, check for word boundaries and optional punctuation.
		if ( 1 === $make_space ) {
			$needle = preg_quote( $needle, '/' );
			return preg_match( '/(?:^|\s)' . $needle . '[.,!?]?(?:$|\s)/i', $haystack );
		}

		// Otherwise, check if string exists anywhere in the text.
		return strpos( $haystack, $needle ) !== false;
	}

	/**
	 * Check whether a URL looks like spam.
	 *
	 * @param string $url URL to check.
	 */
	public static function url( string $url ): bool {

		// If URL is given and does not contain at least one dot.
		if ( strlen( $url ) > 0 && ! str_contains( $url, '.' ) ) {
			return true;
		}

		// If purly the host ( domain without TLD) is digits only, then yupp, also spam.
		$domain = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( self::is_only_digits( $domain ) ) {
			return true;
		}

		// Especially you binance.com ;).
		foreach ( self::get_stop_words() as $stop_word ) {
			if ( str_contains( $domain, $stop_word ) ) {
				return true;
			}
		}

		$blacklist = apply_filters( 'wp_simple_anti_spam/url_blacklist', array( 'bit.ly', 'bitly', 'rb.gy', 'tinyurl.com', 'mmk365app.com' ) );

		foreach ( $blacklist as $item ) {
			if ( str_contains( $url, $item ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether a URL appears in the comment text. But check is precise.
	 * So https://jaimemartinez.nl is a hit, but https://jaimemartinez.nl/about should not be a hit.
	 *
	 * @param string $url  URL to look for.
	 * @param string $text Text to search in.
	 * @return bool
	 */
	public static function url_is_present_in_text( string $url, string $text ): bool {
		$url = trim( $url );

		if ( '' === $url || '' === trim( $text ) ) {
			return false;
		}

		$urls_in_text = wp_extract_urls( $text );

		if ( empty( $urls_in_text ) ) {
			return false;
		}

		return in_array( $url, $urls_in_text, true );
	}

	/**
	 * Check whether comment content looks like spam.
	 *
	 * @param string $content Comment content.
	 */
	public static function text( string $content ): bool {

		// If text is just one word, then it's probably spam.
		$content = trim( strip_tags( $content ) );

		if ( '' !== $content && 1 === preg_match( '/^\S+$/u', $content ) ) {
			return true;
		}

		// If comment contains a russian character.
		// 'lang_needed' => '\p{Latin}',
		// 'lang_forbidden' => '\p{Han}\p{Arabic}\p{Cyrillic}'.
		if ( apply_filters( 'wp_simple_anti_spam/russian_character_check_enabled', true ) && mb_strpos( $content, 'н' ) !== false ) {
			return true;
		}

		// If content consists of only digits.
		if ( self::is_only_digits( $content ) ) {
			return true;
		}

		// If comment contains "buy" and a hyperlink.
		if ( str_contains( $content, 'buy' ) && str_contains( $content, '<a ' ) ) {
			return true;
		}

		// If comment starts with certain pattern.
		if ( preg_match( '/^(Hi|Hey|Hey there|Hello there|Hi there|Hello)! I just /', $content ) ) {
			return true;
		}

		$lower_content = strtolower( $content );

		foreach ( self::get_stop_words() as $stop_word ) {
			if ( str_contains( $lower_content, $stop_word ) ) {
				return true;
			}
		}

		/**
		 * If content contains the current site, then most of the time it's an:
		 *
		 * "I visited your site digitalpassenger.at and found out"
		 */
		if ( str_contains( $lower_content, strtolower( get_site_url() ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the default list of stop words.
	 *
	 * @return array<int, string>
	 */
	private static function get_stop_words(): array {

		return \apply_filters(
			'wp_simple_anti_spam/stop_words',
			array(
				'$100+',
				'(sms)',
				'365/24',
				'binance',
				'bitch',
				'bonus',
				'casino',
				'chatgpt-4',
				'congratulations',
				'crypto',
				'dating',
				'fiverr',
				'free',
				'fuck',
				'hacker',
				'instantly',
				'investment',
				'leads',
				'midjourney',
				'money',
				'penis',
				'poker',
				'porn',
				'pussy',
				'rb.gy/',
				'scam',
				'scotsindallas',
				'sex',
				'spam',
				'subscribe',
				'unsubcribe',
				'urgent',
				'viagra',
				'winner',
				'xxx',
				'→',
				'✅',
				'⮕',
				'ai power',
				'ai tools',
				'check out',
				'click here',
				'content generation',
				'dall·e 3',
				'google ads',
				'high-quality content',
				'instagram growth',
				'instant access',
				'just visited',
				'learn more',
				'no experience',
				'no limits',
				'no subscriptions',
				'them today!',
				'unlock the',
				'your website',
				'youtube channel',
				'are you looking',
				'check my blog',
				'get 50% off',
				'get rich quick',
				'make money fast',
				'social media growth',
				'take a look',
				'we create modern',
				'we run a',
				'work from home',
				'too good to be true',
			)
		);
	}

	/**
	 * Check whether a value consists of only digits.
	 *
	 * @param string $value Value to check.
	 */
	public static function is_only_digits( string $value ): bool {
		// If value consists of only digits.
		return (bool) preg_match( '/^\d+$/', $value );
	}
}
