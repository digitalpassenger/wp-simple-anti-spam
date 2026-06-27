# wp-simple-anti-spam

No config, no big complicated 3rd party spam check. Just simple local, maybe not 100% (but enough) anti-spam solution :)

## WordPress comments

Comments are checked on `pre_comment_approved`. A comment is flagged as spam when **any** of the following match:

| Field | Check |
|-------|-------|
| **User-Agent** | Missing / empty |
| **Author name** | Missing / empty, or digits only (e.g. `123456`) |
| **Author email + URL** | See [Email checks](#email-checks) — `email_and_author_url` |
| **Author URL** | See [URL checks](#url-checks) |
| **Comment content** | See [Text checks](#text-checks) |

Flagged comments return a `WP_Error` and are not saved.

## Gravity Forms

When Gravity Forms is active, entries are checked via `gform_entry_is_spam`.

Only non-administrative fields of these types are scanned:

- Hidden
- Single line text
- Paragraph (textarea)
- Email
- Website

| Field type | Check |
|------------|-------|
| **Text, textarea, hidden** | [Text checks](#text-checks) |
| **Website** | [URL checks](#url-checks) |
| **Email** | [Email checks](#email-checks) — `Check::email()` |

If any checked field matches, the entire entry is marked as spam.

## Email checks

### Full email check — `Check::email()`

Used by Gravity Forms email fields.

- Matches the [email blacklist](#email-blacklist) (literal, wildcard, or regex patterns)
- Matches `first_last@yahoo.com`, `first_last@gmail.com`, `first_last@hotmail.com`, or `first_last@msn.com`
- Skipped if that address already has an approved comment

### Email + URL check — `Check::email_and_author_url()`

Used for comment author email when an author URL is also provided.

- Same `first_last@…` provider pattern as above, but **only when the author URL is filled in**
- Skipped if that address already has an approved comment

Filter: `wp_simple_anti_spam/email_blacklist`

## URL checks

Used for comment author URLs and Gravity Forms website fields.

- URL is set but contains no dot (e.g. `localhost`)
- Hostname is digits only
- Hostname contains a [stop word](#stop-words)
- URL contains a blacklisted shortener: `bit.ly`, `bitly`, `rb.gy`, `tinyurl.com`
- URL matching inside text (`Check::url_is_present_in_text`) is exact per extracted URL token:
  - `https://jaimemartinez.nl` matches `https://jaimemartinez.nl`
  - `https://jaimemartinez.nl` does **not** match `https://jaimemartinez.nl/about`

Filter: `wp_simple_anti_spam/url_blacklist`

## Text checks

Used for comment content and Gravity Forms text / textarea / hidden fields.

- Contains Cyrillic character `н` (can be disabled)
- Content is digits only
- Contains `buy` and an HTML link (`<a `)
- Starts with a common spam greeting: `Hi! I just …`, `Hey! I just …`, etc.
- Contains a [stop word](#stop-words)
- Contains the current site URL (e.g. “I visited your site …”)

Filter: `wp_simple_anti_spam/russian_character_check_enabled`  
Filter: `wp_simple_anti_spam/stop_words`

## Stop words

Default stop words include spammy terms and phrases such as `binance`, `click here`, `take a look`, `work from home`, `ai tools`, and many others.

The full list lives in `Check::get_stop_words()` and can be extended or replaced via the `wp_simple_anti_spam/stop_words` filter.

## Email blacklist

Default patterns include:

- Literal substrings, e.g. `ericjones`
- Wildcards, e.g. `*@temp.*`, `*t.me*`, `*@spam.com`
- Regex, e.g. `/^seo.*@/`, `/^marketing.*@/`, `/^sales.*@/`, `/^\d+@/` (digit-only local part)

Used by `Check::email()` and `Check::email_is_blacklisted()`.

Filter: `wp_simple_anti_spam/email_blacklist`

## Development

```bash
composer install
npm install
composer test   # runs Pest + PHPCS
```
