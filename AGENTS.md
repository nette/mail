# To My Agents!

It is my fervent wish that this file guide every AI coding agent working with code in this repository.

## Documentation

Any distilled, agent-facing documentation for this package - how it works
internally and the rationale behind key design decisions - lives in `docs/`.
Consult it before non-trivial changes; it is the source of truth from which the
public manual is distilled.

## Project Overview

Nette Mail is a standalone PHP library for creating and sending emails with support for SMTP, sendmail, DKIM signing, and fallback mechanisms. Part of the Nette Framework ecosystem but usable independently.

- **Requirements:** PHP 8.2 - 8.5, ext-iconv required
- **Optional extensions:** ext-dom (CssInliner, PHP 8.4+), ext-fileinfo (attachment type detection), ext-openssl (DKIM signing)
- **Main dependency:** nette/utils ^4.1

## Essential Commands

### Testing

```bash
# Run all tests
composer run tester
# or
vendor/bin/tester tests -s

# Run specific test file
vendor/bin/tester tests/Mail/Message.phpt -s

# Run tests in specific directory
vendor/bin/tester tests/Mail/ -s
```

### Static Analysis

```bash
# Run PHPStan analysis (level 8)
composer run phpstan
# or
vendor/bin/phpstan analyse
```

## Architecture

### Core Components

The library consists of five main areas:

1. **Email Creation** (`src/Mail/`)
   - `Message` - Main class for composing emails, extends MimePart
   - `MimePart` - Base class handling MIME encoding, headers, and structure
   - `HtmlComposer` - Pipeline behind `setHtmlBody()`: optional CSS inlining and image embedding, subject from `<title>`, plain-text alternative generation
   - Priority constants: `Message::High`, `Message::Normal`, `Message::Low`

2. **Email Sending** (`src/Mail/`)
   - `Mailer` interface - Contract for all mailer implementations
   - `SendmailMailer` - Uses PHP's `mail()` function
   - `SmtpMailer` - Full SMTP protocol implementation with TLS/SSL support
   - `FallbackMailer` - Retry mechanism across multiple mailers
   - `Interceptor` - Wraps any Mailer with optional redirect of outgoing emails to a fixed address (debugging/safety) and an `$onSent` event fired after each send (success or failure)

3. **Email Signing** (`src/Mail/`)
   - `Signer` interface - Contract for signing implementations
   - `DkimSigner` - DKIM (DomainKeys Identified Mail) signing using RSA-SHA256

4. **CSS Inlining** (`src/Mail/`)
   - `CssInliner` - Converts CSS rules to inline `style` attributes for email HTML (requires PHP 8.4+ for `Dom\HTMLDocument`)

5. **Tracy Bar Panel** (`src/Bridges/MailTracy/`)
   - `MailPanel` - IBarPanel showing sent emails in the Tracy Bar; subscribes to `Interceptor::$onSent`

### Dependency Injection Integration

`src/Bridges/MailDI/MailExtension.php` - Nette DI compiler extension for configuration.

**DI Services registered:**
- `mail.mailer` - autowired public Mailer; either the real transport directly (SendmailMailer/SmtpMailer) or, when `redirect` or `debugger: true` is configured, an `Interceptor` wrapping the real transport
- `mail.innerMailer` - private real transport (only present when `mail.mailer` is an `Interceptor`)
- `mail.signer` - DKIM Signer instance (only when DKIM is configured)
- `mail.panel` - `MailPanel` Tracy Bar panel (only in debug mode when the Interceptor is active and `Tracy\Bar` is registered)
- `nette.mailer` - alias to `mail.mailer` (for backward compatibility)

**Configuration:**

```neon
mail:
	# Use SmtpMailer instead of SendmailMailer
	smtp: true              # (bool) defaults to false

	# SMTP connection settings
	host: smtp.gmail.com    # (string) SMTP server hostname
	port: 587               # (int) defaults: 465 for ssl, otherwise 25
	username: user@example.com
	password: ****
	encryption: tls         # (ssl|tls|null) null = no encryption
	timeout: 20             # (int) connection timeout in seconds, default 20
	persistent: false       # (bool) use persistent connection
	clientHost: localhost   # (string) defaults to $_SERVER['HTTP_HOST'] or 'localhost'

	# SSL/TLS context options for SMTP connection
	context:
		ssl:
			verify_peer: true           # NEVER set to false in production!
			verify_peer_name: true
			allow_self_signed: false    # Do not allow self-signed certificates
			# See https://www.php.net/manual/en/context.ssl.php for all options

	# DKIM signing configuration
	dkim:
		domain: example.com             # Your domain name
		selector: dkim                  # DKIM selector from DNS
		privateKey: %appDir%/../dkim/private.key  # Path to private key file
		passPhrase: ****                # Optional passphrase for private key

	# Redirect all outgoing emails to a fixed address (debugging/safety)
	# Shortcut form: just the address
	redirect: dev@example.com
	# Or full form with subject prefix:
	# redirect:
	#     to: dev@example.com
	#     subjectPrefix: '[debug]'

	# Tracy panel:
	#  - default: attached when Interceptor is active, in debug mode, and Tracy\Bar exists
	#  - 'debugger: true' explicitly opts in (and forces Interceptor wiring even without redirect)
	#  - 'debugger: false' explicitly opts out
	debugger: true
```

**Security Warning:** Never disable SSL certificate verification (`verify_peer: false`) as it makes your application vulnerable to man-in-the-middle attacks. Instead, add certificates to the trust store if needed.

### Exception Hierarchy

All exceptions in `src/Mail/exceptions.php`:
- `SendException` - Base exception for sending failures
- `SmtpException` - SMTP-specific errors (extends SendException)
- `FallbackMailerException` - All mailers failed (contains array of failures)
- `SignException` - Signing/verification errors

### Key Features

**Message Creation:**
- Fluent API with method chaining
- Automatic text alternative generation from HTML
- Auto-embedding images from filesystem using `[[...]]` syntax or `<img src=...>`
- Subject auto-extraction from `<title>` element
- Attachment support with auto-detection of MIME types

**MIME Handling:**
- Encoding methods: Base64, 7bit, 8bit, quoted-printable
- Line length management (76 characters default)
- Full UTF-8 support throughout

**SMTP Features:**
- TLS/SSL encryption support (`encryption: 'ssl'` or `'tls'`)
- Default ports: 465 (SSL), otherwise 25 (incl. TLS/STARTTLS; set `port: 587` explicitly for submission)
- Persistent connections
- Configurable timeout (default 20s)
- Custom stream options for SSL context
- Envelope sender support
- AUTH PLAIN and LOGIN authentication methods

**DKIM Signing:**
- RSA-SHA256 signing algorithm
- Private key passphrase support
- Automatic header canonicalization
- Compatible with Gmail, Outlook, and other major providers

## Testing Strategy

Uses Nette Tester with `.phpt` format:

```php
<?php
declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

test('Message correctly sets recipient', function () {
	$mail = new Nette\Mail\Message;
	$mail->addTo('test@example.com');

	Assert::same(['test@example.com' => null], $mail->getHeader('To'));
});
```

- Test files in `tests/Mail/` cover all major functionality
- Test fixtures in `tests/Mail/fixtures/` for email samples
- Bootstrap in `tests/bootstrap.php` provides `test()` helper function
- Tests run on PHP 8.2-8.5 in CI

## Coding Standards

Follows Nette Coding Standard (PSR-12 based) with these requirements:

- **Mandatory:** `declare(strict_types=1)` in all PHP files
- **Indentation:** Tabs (not spaces)
- **Method spacing:** Two empty lines between methods
- **Types:** All properties, parameters, and return values must be typed
- **Documentation:** Only when adding information beyond PHP types
  - Document array contents: `@return string[]`
  - Document nullable relationships: `@param ?string`
  - Skip obvious parameters (width, height, name)
- **String quotes:** Single quotes unless containing apostrophes
- **Naming:** PascalCase for classes, camelCase for methods/properties
- **No prefixes:** No `Abstract`, `Interface`, or `I` prefixes

### Return Type Format

Opening brace on separate line after return type:

```php
public function send(Message $mail): void
{
	// method body
}
```

### phpDoc Examples

```php
/**
 * Adds email recipient. Email or format "John Doe" <doe@example.com>
 */
public function addTo(string $email, ?string $name = null): static

/**
 * Sets a header.
 * @param  string|array<string, ?string>|null  $value  value or pair email => name
 */
public function setHeader(string $name, string|array|null $value, bool $append = false): static
```

## Development Workflow

1. **Before making changes:**
   - Read existing code to understand patterns
   - Check related test files
   - Verify PHPStan passes: `composer run phpstan`

2. **When adding features:**
   - Add corresponding tests in `tests/Mail/`
   - Use `test()` helper for test cases
   - Run tests: `vendor/bin/tester tests -s`

3. **When fixing bugs:**
   - Add regression test first
   - Ensure fix doesn't break existing tests
   - Update PHPDoc if behavior changes

4. **Before committing:**
   - Run full test suite: `composer run tester`
   - Run static analysis: `composer run phpstan`
   - Check code style with Nette Code Checker

## Usage in Nette Application

When using Nette Mail within a full Nette Application (with presenters), you can integrate it with Latte templates and create absolute links using `LinkGenerator`.

### Email Templates with Links

To use `n:href` and `{link}` in email templates, inject both `TemplateFactory` and `LinkGenerator`:

```php
use Nette;

class MailSender
{
	public function __construct(
		private Nette\Application\LinkGenerator $linkGenerator,
		private Nette\Bridges\ApplicationLatte\TemplateFactory $templateFactory,
	) {
	}


	private function createTemplate(): Nette\Application\UI\Template
	{
		$template = $this->templateFactory->createTemplate();
		// Add LinkGenerator as 'uiControl' provider for n:href and {link}
		$template->getLatte()->addProvider('uiControl', $this->linkGenerator);
		return $template;
	}


	public function sendOrderConfirmation(int $orderId): void
	{
		$template = $this->createTemplate();
		$html = $template->renderToString(__DIR__ . '/templates/orderEmail.latte', [
			'orderId' => $orderId,
		]);

		$mail = new Nette\Mail\Message;
		$mail->setFrom('shop@example.com')
			->addTo('customer@example.com')
			->setHtmlBody($html);

		$this->mailer->send($mail);
	}
}
```

**Template with absolute links:**

```latte
<p>Your order #{$orderId} has been confirmed.</p>
<p><a n:href="Order:detail $orderId">View order details</a></p>
```

All links created via `LinkGenerator` are absolute (include full domain), which is required for emails.

## Important Patterns

### Encoding

- All input strings must be valid UTF-8 (`setHeader` validates via `Strings::checkEncoding` and throws otherwise)
- Non-ASCII header values are encoded as RFC 2047 base64 encoded-words
- Body transfer encoding (7bit/8bit/quoted-printable) is chosen automatically by content sniffing

### Header Management

- Header names are stored and looked up **case-sensitively**, exactly as passed to `setHeader()`/`getHeader()` (use the canonical `To`, `Content-Type`, ... spelling)
- Email headers (To, Cc, Bcc, From, Reply-To) are stored as `[email => name]` arrays; each address is validated
- `setHeader` strips `\r\n` from values - it is the injection-prevention boundary

### Image Embedding

Automatic embedding supports:
- `<img src="...">`
- `<body background="...">`
- CSS `url(...)` in style attributes
- Special `[[filename]]` syntax

### SendmailMailer Configuration

`SendmailMailer` uses PHP's `mail()` function. The envelope sender (`-f` option) is set automatically from the From header; disable with `setEnvelopeSender(false)` or pass custom arguments:

```php
$mailer = new Nette\Mail\SendmailMailer;
$mailer->commandArgs = '-fmy@email.com';  // Set return path manually
```

### SMTP Connection

`SmtpMailer` handles SMTP protocol details:
- Automatic STARTTLS negotiation
- AUTH PLAIN and LOGIN support
- Proper QUIT handling in persistent mode
- Full error message parsing
- Connection reuse with persistent mode

### CSS Inlining

`CssInliner` converts CSS rules to inline `style` attributes for email HTML compatibility. Uses `Dom\HTMLDocument` (PHP 8.4+) for DOM manipulation and CSS selectors.

```php
// From <style> tags in HTML (automatically extracted, tag preserved)
$html = (new CssInliner)->inline($html);

// External CSS string
$html = (new CssInliner)->addCss('p { margin: 0; }')->inline($html);
```

**Architecture:**
- Single-regex tokenizer (comment, whitespace, string, url, escape, at-ident, hash, number, ident, char)
- Token-based stylesheet parser with CSS nesting and @-rule skipping
- Declarations parsed into `property => value` arrays (enables deduplication and HTML attribute generation)
- Cascade: `<style>` → `addCss()` → existing inline `style=""` wins (last declaration wins)
- `<style>` tags are preserved (keeps @media queries intact)
- Automatic HTML attribute generation for Outlook compatibility (background-color→bgcolor, width→width, height→height, border-spacing→cellspacing; align/valign intentionally excluded, their semantics differ from CSS)
