# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Nette Mail is a standalone PHP library for creating and sending emails with support for SMTP, sendmail, DKIM signing, and fallback mechanisms. Part of the Nette Framework ecosystem but usable independently.

- **Requirements:** PHP 8.0 - 8.5, ext-iconv required
- **Optional extensions:** ext-fileinfo (attachment type detection), ext-openssl (DKIM signing)
- **Main dependency:** nette/utils ^4.0

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
# Run PHPStan analysis (level 5)
composer run phpstan
# or
vendor/bin/phpstan analyse
```

## Architecture

### Core Components

The library consists of three main areas:

1. **Email Creation** (`src/Mail/`)
   - `Message` - Main class for composing emails, extends MimePart
   - `MimePart` - Base class handling MIME encoding, headers, and structure
   - Priority constants: `Message::High`, `Message::Normal`, `Message::Low`

2. **Email Sending** (`src/Mail/`)
   - `Mailer` interface - Contract for all mailer implementations
   - `SendmailMailer` - Uses PHP's `mail()` function
   - `SmtpMailer` - Full SMTP protocol implementation with TLS/SSL support
   - `FallbackMailer` - Retry mechanism across multiple mailers

3. **Email Signing** (`src/Mail/`)
   - `Signer` interface - Contract for signing implementations
   - `DkimSigner` - DKIM (DomainKeys Identified Mail) signing using RSA-SHA256

### Dependency Injection Integration

`src/Bridges/MailDI/MailExtension.php` - Nette DI compiler extension for configuration.

**DI Services registered:**
- `mail.mailer` - Mailer instance (SendmailMailer or SmtpMailer based on config)
- `mail.signer` - DKIM Signer instance (if DKIM is configured)
- `nette.mailer` - Alias to mail.mailer (for backward compatibility)

**Configuration:**

```neon
mail:
	# Use SmtpMailer instead of SendmailMailer
	smtp: true              # (bool) defaults to false

	# SMTP connection settings
	host: smtp.gmail.com    # (string) SMTP server hostname
	port: 587               # (int) defaults: 25, 465 for ssl, 587 for tls
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
- Default ports: 25 (unencrypted), 465 (SSL), 587 (TLS)
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

- **31 test files** covering all major functionality
- Test fixtures in `tests/Mail/fixtures/` for email samples
- Bootstrap in `tests/bootstrap.php` provides `test()` helper function
- Tests run on PHP 8.0-8.5 in CI

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
public function send(Message $mail):
{
	// method body
}
```

### phpDoc Examples

```php
/**
 * Adds email recipient.
 * @param  string|array  $email  Address or [address => name] pairs
 */
public function addTo(string|array $email, ?string $name = null): static

/**
 * Sets message priority.
 */
public function setPriority(int $priority): static
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

### Encoding Detection

The library automatically handles encoding with these patterns:
- Uses `mb_detect_encoding()` for content detection
- Defaults to UTF-8 for all string operations
- Converts to ASCII for headers when needed

### Header Management

Headers are case-insensitive and normalized:
- Storage: lowercase with first letter capitalized
- Access: case-insensitive lookup
- Special handling for To, Cc, Bcc, From headers

### Image Embedding

Automatic embedding supports:
- `<img src="...">`
- `<body background="...">`
- CSS `url(...)` in style attributes
- Special `[[filename]]` syntax

### SendmailMailer Configuration

`SendmailMailer` uses PHP's `mail()` function. To set return path when server overwrites it:

```php
$mailer = new Nette\Mail\SendmailMailer;
$mailer->commandArgs = '-fmy@email.com';  // Set return path
```

### SMTP Connection

`SmtpMailer` handles SMTP protocol details:
- Automatic STARTTLS negotiation
- AUTH PLAIN and LOGIN support
- Proper QUIT handling in persistent mode
- Full error message parsing
- Connection reuse with persistent mode
