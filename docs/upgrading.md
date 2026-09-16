# Upgrading nette/mail

## 4.2.2

- **Changed** `Nette\Mail\HtmlComposer::embedImages()` now replaces the `[[file]]` placeholder only with an existing image inside the base path, which also applies to `Nette\Mail\Message::setHtmlBody()` with `$basePath`; previously it embedded any file, including one reached through `..`, and threw `Nette\IOException` when the file did not exist; any other `[[...]]` is now left as text, so embed a non-image or a file outside the base path with `Nette\Mail\Message::addEmbeddedFile()` (839ee99d)

## 4.2.0

- **Changed** `Nette\Mail\SmtpMailer` with `encryption: 'tls'` and no explicit port now connects on port 587; previously on port 25; pass `port: 25` explicitly to keep the previous default (5bed7356)
- **Changed** `Nette\Mail\SmtpMailer` now negotiates only TLS 1.2 and 1.3 for STARTTLS; previously TLS 1.0 and 1.1 could be negotiated too, so a server offering only those can no longer be used (f2b792cc)
- **Changed** `Nette\Mail\SmtpMailer::send()` now throws `Nette\Mail\SmtpException` when the server advertises neither `PLAIN` nor `LOGIN`; previously it sent a blind `AUTH LOGIN` (96be59a7)
- **Changed** `Nette\Mail\Message` now converts the domain of every address header to punycode; previously a non-ASCII domain went on the wire verbatim; this needs `ext-intl`, without it the address is passed through unchanged (b8e7f346)
- **Changed** `Nette\Mail\MimePart` now matches header names case-insensitively and keeps the spelling first used; previously `getHeader()`, `setHeader()` and `clearHeader()` matched by exact spelling, so a differently spelled name added a second header instead of replacing it (a7bb9d5e)
- **Changed** `Nette\Mail\DkimSigner` no longer emits the `l=` body-length tag, so the signature covers the whole body; previously content appended after the signed length was still accepted by the receiver (6fddabe7)
- **Changed** `Nette\Mail\FallbackMailer::send()` no longer retries a mailer whose failure is permanent as reported by `Nette\Mail\SendException::isPermanent()`; previously every failure was retried `retryCount` times (afdaa63f)
- **Changed** `Nette\Mail\CssInliner::inline()` now resolves competing declarations by the CSS cascade (`!important`, an existing inline style, specificity, then source order) and writes each property once; previously the last rule mentioning a property won (6dd1042f)
- **Changed** `Nette\Mail\CssInliner::inline()` now writes a numeric compatibility attribute such as `width` only for a plain length or percentage; previously a keyword or `calc()` value was cast to an integer and emitted as `0` (70c3f542)
- **Changed** `Nette\Mail\CssInliner::inline()` now compares property names case-insensitively, custom properties excepted; previously `COLOR` and `color` were kept as two separate declarations (70c3f542)

## 4.1.2

- **Replaced** `Nette\Mail\Message::buildText()` with `Nette\Mail\HtmlComposer::htmlToText()`: the protected method is gone and `Nette\Mail\Message` no longer calls it, so a subclass that overrode it to customize the plain-text alternative must switch to `Nette\Mail\HtmlComposer` (0680699b)

## 4.1.0

- **Signature** `Nette\Mail\MimePart::getHeader()`: returns `string|array|null` instead of `mixed`; affects subclasses overriding it (968122d8)

## 4.0.4

- **Deprecated** `Nette\Mail\Message::HIGH`: use `Nette\Mail\Message::High`; the constant now carries `#[\Deprecated]`, so on PHP 8.4+ using the old name triggers `E_USER_DEPRECATED` (alias since 4.0.0) (2ce92d33)
- **Deprecated** `Nette\Mail\Message::NORMAL`: use `Nette\Mail\Message::Normal`; the constant now carries `#[\Deprecated]`, so on PHP 8.4+ using the old name triggers `E_USER_DEPRECATED` (alias since 4.0.0) (2ce92d33)
- **Deprecated** `Nette\Mail\Message::LOW`: use `Nette\Mail\Message::Low`; the constant now carries `#[\Deprecated]`, so on PHP 8.4+ using the old name triggers `E_USER_DEPRECATED` (alias since 4.0.0) (2ce92d33)

## 4.0.1

- **Signature** `Nette\Mail\SendmailMailer::$commandArgs`: typed `string` with the default `''`; previously `?string` defaulting to `null`, so assigning `null` now throws a `TypeError` (6a2ee304)
- **Changed** `Nette\Mail\FallbackMailer` no longer uses `Nette\SmartObject`; invoking the handlers as `$mailer->onFailure(...)` throws an `\Error`, iterate `Nette\Mail\FallbackMailer::$onFailure` instead (90b67373)
- **Changed** `Nette\Mail\SmtpMailer::send()` adds a `To: undisclosed-recipients: ;` header when the message has Bcc recipients only; previously no `To` header was sent (f7c17fac)

## 4.0.0

- **Renamed** `Nette\Mail\MimePart::ENCODING_7BIT` → `Nette\Mail\MimePart::Encoding7Bit`; the old name was removed (3f815c15)
- **Renamed** `Nette\Mail\MimePart::ENCODING_8BIT` → `Nette\Mail\MimePart::Encoding8Bit`; the old name was removed (3f815c15)
- **Renamed** `Nette\Mail\MimePart::ENCODING_BASE64` → `Nette\Mail\MimePart::EncodingBase64`; the old name was removed (3f815c15)
- **Renamed** `Nette\Mail\MimePart::ENCODING_QUOTED_PRINTABLE` → `Nette\Mail\MimePart::EncodingQuotedPrintable`; the old name was removed (3f815c15)
- **Renamed** `Nette\Mail\Message::HIGH` → `Nette\Mail\Message::High`; the old name still works as an alias (3f815c15)
- **Renamed** `Nette\Mail\Message::NORMAL` → `Nette\Mail\Message::Normal`; the old name still works as an alias (3f815c15)
- **Renamed** `Nette\Mail\Message::LOW` → `Nette\Mail\Message::Low`; the old name still works as an alias (3f815c15)
- **Deprecated** `Nette\Mail\Message::$subject`: use `Nette\Mail\Message::setSubject()` and `Nette\Mail\Message::getSubject()`; using the old name triggers `E_USER_DEPRECATED` (c4220e94)
- **Deprecated** `Nette\Mail\Message::$htmlBody`: use `Nette\Mail\Message::setHtmlBody()` and `Nette\Mail\Message::getHtmlBody()`; using the old name triggers `E_USER_DEPRECATED` (c4220e94)
- **Deprecated** `Nette\Mail\MimePart::$body`: use `Nette\Mail\MimePart::setBody()` and `Nette\Mail\MimePart::getBody()`; using the old name triggers `E_USER_DEPRECATED` (c4220e94)
- **Signature** `Nette\Mail\SmtpMailer::__construct()`: takes `string $host, string $username, string $password, ?int $port = null, ?string $encryption = null, bool $persistent = false, int $timeout = 20, ?string $clientHost = null, ?array $streamOptions = null` instead of a single `array $options`; the former `secure` key is now `$encryption` and `context` is `$streamOptions` (19437673)
- **Signature** `Nette\Mail\DkimSigner::__construct()`: takes `string $domain, string $selector, string $privateKey, ?string $passPhrase = null` instead of a single `array $options`, followed by the existing `$signHeaders` (49045461)
- **Signature** `Nette\Mail\MimePart::setHeader()`: `$value` is typed `string|array|null` and the method returns `static`; previously `$value` was untyped, so passing another type now throws a `TypeError` (160d40bd)
- **Signature** `Nette\Mail\SmtpMailer::write()`: `$expectedCode` is typed `int|array|null`; previously untyped; affects subclasses overriding this protected method (160d40bd)
- **Signature** `Nette\Mail\SendmailMailer::setSigner()` and `Nette\Mail\SmtpMailer::setSigner()`: return `static` instead of `self`; affects subclasses overriding them (160d40bd)
- **Signature** native types were added throughout the package, so a value of another type now throws a `TypeError` and a subclass overriding the method must repeat the type: `Nette\Mail\FallbackMailer::$onFailure` (`array`), `Nette\Mail\FallbackMailerException::$failures` (`array`), `Nette\Mail\Message::$defaultHeaders` (`array`), `Nette\Mail\SendmailMailer::$commandArgs` (`?string`), `Nette\Mail\Message::build()`, `Nette\Mail\Message::setFrom()`, `Nette\Mail\Message::addTo()`, `Nette\Mail\Message::addCc()`, `Nette\Mail\Message::addBcc()`, `Nette\Mail\Message::addReplyTo()`, `Nette\Mail\Message::setSubject()`, `Nette\Mail\Message::setHtmlBody()`, `Nette\Mail\Message::setPriority()`, `Nette\Mail\Message::setReturnPath()`, `Nette\Mail\Message::addInlinePart()`, `Nette\Mail\MimePart::setBody()`, `Nette\Mail\MimePart::getHeader()`, `Nette\Mail\MimePart::clearHeader()`, `Nette\Mail\MimePart::setContentType()`, `Nette\Mail\MimePart::setEncoding()`, `Nette\Mail\FallbackMailer::addMailer()` (fea89dbc, 160d40bd)
- **Changed** `Nette\Mail\SendmailMailer::send()` passes `-f` with the address from the `From` header as an extra argument to `mail()`; previously only `Nette\Mail\SendmailMailer::$commandArgs` was passed, so remove a `-f` flag you set there yourself (0e236e47)
- **Config** `mail.dkim.domain`: now required; previously optional (49045461)
- **Config** `mail.dkim.selector`: now required; previously optional (49045461)

## 3.1.10

- **Config** `mail.secure` → `mail.encryption`: the old key still works as an alias (23380ff0)
- **Config** `mail.dkim.testMode`: removed, the key is now rejected as unexpected; override `Nette\Mail\DkimSigner::getTime()` to fix the signing time instead (23380ff0)

## 3.1.9

- **Renamed** `Nette\Mail\MimePart::LINE_LENGTH` → `Nette\Mail\MimePart::LineLength`; the old name was removed (a76c903d)

## 3.1.8

- **Changed** `Nette\Mail\Message::getPriority()` returns `int|null`; previously the raw header value as a string; affects code comparing the result with a string (44afd13d)

## 3.1.4

- **Replaced** the `testMode` option of `Nette\Mail\DkimSigner::__construct()` with `Nette\Mail\DkimSigner::getTime()`: override the protected method to return a fixed timestamp instead of passing the option (cb6d72ce)
- **Changed** `Nette\Mail\MimePart::LINE_LENGTH` is `76` again; it was `500` in 3.1.2 and 3.1.3, so encoded lines are wrapped at the shorter length once more (1944d333)

## 3.1.3

- **Changed** `Nette\Mail\Message::addAttachment()` and `Nette\Mail\Message::addEmbeddedFile()` throw `Nette\IOException` when the file cannot be read; previously `Nette\FileNotFoundException`; affects code catching it (b4027a00)

## 3.1.2

- **Changed** `Nette\Mail\MimePart::LINE_LENGTH` is `500` instead of `76`, so encoded lines are wrapped at the longer length (856c152d)
- **Config** `mail.dkim.privateKey`: no longer accepts a dynamic `%...%` value resolved per request, it must be a compile-time value (374dba88)

## 3.1.0

- **Renamed** `Nette\Mail\IMailer` → `Nette\Mail\Mailer`; the old name still works as an alias (d109c9ea)
- **Signature** `Nette\Mail\Message::build()`: visibility changed from `protected` to `public`; affects subclasses overriding it, which must be declared `public` as well (e5f79588)
