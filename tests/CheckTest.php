<?php
/**
 * Tests for the Check class.
 *
 * @package WPSimpleAntiSpam
 */

use WPSimpleAntiSpam\Check;

describe(
	'Check::is_only_digits',
	function () {
		it(
			'flags digit-only values as spam',
			function ( string $value ) {
				expect( Check::is_only_digits( $value ) )->toBeTrue();
			}
		)->with( array( '1', '1234567890', '007' ) );

		it(
			'allows non-digit values',
			function ( string $value ) {
				expect( Check::is_only_digits( $value ) )->toBeFalse();
			}
		)->with( array( '', '123abc', '12 34', 'abc' ) );
	}
);

describe(
	'Check::email_is_blacklisted',
	function () {
		it(
			'flags blacklisted emails',
			function ( string $email ) {
				expect( Check::email_is_blacklisted( $email ) )->toBeTrue();
			}
		)->with(
			array(
				'ericjones@gmail.com',
				'user@temp.mail.com',
				'foo@spam.com',
				'contact@t.me',
				'marketing.hoi@jm.me',
				'seo.leads@example.com',
				'sales-team@company.org',
			)
		);

		it(
			'flags emails with a digit-only local part',
			function ( string $email ) {
				expect( Check::email_is_blacklisted( $email ) )->toBeTrue();
			}
		)->with(
			array(
				'1@gmail.com',
				'123456@gmail.com',
				'007@yahoo.com',
			)
		);

		it(
			'allows emails with letters in the local part',
			function ( string $email ) {
				expect( Check::email_is_blacklisted( $email ) )->toBeFalse();
			}
		)->with(
			array(
				'hello123@gmail.com',
				'user1@company.com',
			)
		);

		it(
			'allows clean emails',
			function ( string $email ) {
				expect( Check::email_is_blacklisted( $email ) )->toBeFalse();
			}
		)->with(
			array(
				'',
				'   ',
				'hello@gmail.com',
				'jane.doe@company.com',
			)
		);
	}
);

describe(
	'Check::email_name_pattern',
	function () {
		it(
			'flags first_last provider emails',
			function ( string $email ) {
				expect( Check::email_name_pattern( $email ) )->toBeTrue();
			}
		)->with(
			array(
				'john_doe@yahoo.com',
				'jane_smith@gmail.com',
				'bob_jones@hotmail.com',
				'alice_bob@msn.com',
			)
		);

		it(
			'allows other email formats',
			function ( string $email ) {
				expect( Check::email_name_pattern( $email ) )->toBeFalse();
			}
		)->with(
			array(
				'',
				'hello@gmail.com',
				'john.doe@yahoo.com',
				'john_doe@company.com',
			)
		);
	}
);

describe(
	'Check::email_is_approved',
	function () {
		it(
			'returns true when email exists in approved comments',
			function () {
				mock_wpdb( '42' );

				expect( Check::email_is_approved( 'trusted@example.com' ) )->toBeTrue();
			}
		);

		it(
			'returns false when email is not approved',
			function () {
				mock_wpdb( null );

				expect( Check::email_is_approved( 'unknown@example.com' ) )->toBeFalse();
			}
		);
	}
);

describe(
	'Check::email_and_author_url',
	function () {
		it(
			'flags pattern email with author url',
			function () {
				expect( Check::email_and_author_url( 'john_doe@yahoo.com', 'https://example.com' ) )->toBeTrue();
			}
		);

		it(
			'does not flag pattern email without author url',
			function () {
				expect( Check::email_and_author_url( 'john_doe@yahoo.com', '' ) )->toBeFalse();
			}
		);

		it(
			'does not flag approved emails even with url',
			function () {
				mock_wpdb( '99' );

				expect( Check::email_and_author_url( 'john_doe@yahoo.com', 'https://example.com' ) )->toBeFalse();
			}
		);
	}
);

describe(
	'Check::email',
	function () {
		it(
			'returns false for approved emails',
			function () {
				mock_wpdb( '1' );

				expect( Check::email( 'trusted@example.com' ) )->toBeFalse();
			}
		);

		it(
			'flags blacklisted emails',
			function () {
				expect( Check::email( 'marketing.hoi@jm.me' ) )->toBeTrue();
			}
		);

		it(
			'flags name-pattern emails',
			function () {
				expect( Check::email( 'john_doe@gmail.com' ) )->toBeTrue();
			}
		);

		it(
			'allows clean emails',
			function () {
				expect( Check::email( 'hello@company.com' ) )->toBeFalse();
			}
		);
	}
);

describe(
	'Check::url',
	function () {
		it(
			'flags urls without a dot',
			function () {
				expect( Check::url( 'localhost' ) )->toBeTrue();
			}
		);

		it(
			'flags blacklisted url hosts',
			function ( string $url ) {
				expect( Check::url( $url ) )->toBeTrue();
			}
		)->with(
			array(
				'https://bit.ly/abc',
				'https://www.bitly.com/foo',
				'https://rb.gy/xyz',
				'https://tinyurl.com/abc',
			)
		);

		it(
			'flags urls with stop words in the domain host',
			function ( string $url ) {
				expect( Check::url( $url ) )->toBeTrue();
			}
		)->with(
			array(
				'https://binance.com',
				'https://www.binance.com/signup',
			)
		);

		it(
			'allows clean urls',
			function () {
				expect( Check::url( '' ) )->toBeFalse();
				expect( Check::url( 'https://example.com' ) )->toBeFalse();
			}
		);
	}
);

describe(
	'Check::str_contains',
	function () {
		it(
			'matches words with boundaries by default',
			function () {
				expect( Check::str_contains( 'please buy now', 'buy' ) )->toBeTruthy();
				expect( Check::str_contains( 'buyer guide', 'buy' ) )->toBeFalsy();
			}
		);

		it(
			'matches anywhere when make_space is disabled',
			function () {
				expect( Check::str_contains( 'buyer guide', 'buy', 0 ) )->toBeTrue();
			}
		);

		it(
			'returns false for empty input',
			function () {
				expect( Check::str_contains( '', 'buy' ) )->toBeFalse();
				expect( Check::str_contains( 'hello', '' ) )->toBeFalse();
			}
		);
	}
);

describe(
	'Check::text',
	function () {
		it(
			'flags russian characters when enabled',
			function () {
				expect( Check::text( 'Привет н world' ) )->toBeTrue();
			}
		);

		it(
			'skips russian check when filter disabled',
			function () {
				add_filter( 'wp_simple_anti_spam/russian_character_check_enabled', static fn() => false );

				expect( Check::text( 'Привет н world' ) )->toBeFalse();

				remove_all_filters( 'wp_simple_anti_spam/russian_character_check_enabled' );
			}
		);

		it(
			'flags digit-only content',
			function () {
				expect( Check::text( '123456' ) )->toBeTrue();
			}
		);

		it(
			'flags buy with hyperlink',
			function () {
				expect( Check::text( 'please buy <a href="https://spam.test">now</a>' ) )->toBeTrue();
			}
		);

		it(
			'flags greeting spam pattern',
			function () {
				expect( Check::text( 'Hi! I just found your site' ) )->toBeTrue();
			}
		);

		it(
			'flags stop words',
			function ( string $content ) {
				expect( Check::text( $content ) )->toBeTrue();
			}
		)->with(
			array(
				'Please take a look at this',
				'You should check out my offer',
				'We create modern websites',
				'Get instant access today',
				'Try our ai tools now',
			)
		);

		it(
			'flags content containing the site url',
			function () {
				expect( Check::text( 'I visited https://example.test and loved it' ) )->toBeTrue();
			}
		);

		it(
			'allows clean content',
			function () {
				expect( Check::text( 'Thanks for the helpful article about WordPress.' ) )->toBeFalse();
			}
		);
	}
);
