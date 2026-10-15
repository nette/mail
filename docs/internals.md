# Mail internals

How `nette/mail` builds and delivers a message, for agents editing it. Medium
depth, one file: several subsystems (MIME construction, header encoding, DKIM,
SMTP, HTML composition, CSS inlining) but all resting on the `Message`/`MimePart`
substrate, so not enough independent models to warrant a directory.

## `build()` is non-mutating and assembles a nested MIME tree

`Message::generateMessage()` = `build()->getEncodedMessage()`. **`build()` clones
`$this`** and returns a new object — it never mutates the message, so it is safe to
call repeatedly (SMTP + DKIM both call it). Repeated calls are safe but **not
deterministic**: each `build()` generates a fresh `Message-ID` (unless set
explicitly) and each encode uses fresh random boundaries. The clone is where the flat message
(subject, bodies, attachments, inlines) becomes a **nested MIME part tree**, and
the nesting order is the emergent model you only get by tracing the
`$cursor`/`$tmp`/`$alt` juggling:

```
multipart/mixed          ← only if attachments present
├─ multipart/alternative ← only if an HTML body is set
│  ├─ text/plain         ← always written last, at the cursor
│  └─ multipart/related  ← only if inline (embedded) files present
│     ├─ text/html
│     └─ <inline parts, cid: referenced>
└─ <attachment parts>
```

Each wrapper appears **only when its content exists**, and the order is
deliberate: `text/plain` is emitted **before** `text/html` inside `alternative`
(clients pick the last they understand → HTML), `related` binds the HTML to its
`cid:` images, and `mixed` adds attachments outermost. Reordering these branches
breaks client rendering. Attachments are always `base64` (except
`multipart`/`message` types → `8bit`). Attachment content types are silently
coerced in `createAttachment`: `message/rfc822` → `application/octet-stream`,
`image/svg` → `image/svg+xml` — the part may not carry the type you passed in.

## Content-Transfer-Encoding is content-sniffed — and `7bit` deletes bytes

For each text/HTML body, the encoding is chosen (identically in three places):

- a line **≥ 990 chars** → `quoted-printable` (stays under the 998-octet SMTP line
  limit);
- else any **byte ≥ 0x80** → `8bit`;
- else → `7bit`.

The trap lives in `MimePart::getEncodedMessage`: **`7bit` silently strips all
high bytes** (`preg_replace('#[\x80-\xFF]+#', '', $body)`, then falls through to
the `8bit` newline normalization). So the encoding choice is not cosmetic — force
`7bit` on non-ASCII content and characters vanish from the sent mail. `8bit`
normalizes `\n`→CRLF and drops `\0`/stray `\r`.

Sub-parts are serialized recursively; a `boundary=` is appended to `Content-Type`
**only when the part actually has children**, and children are wrapped in
`--boundary` delimiters with a random boundary per part.

## Header encoding: base64 words, injection stripped at set time

- **Header names are case-insensitive, but the stored key keeps the spelling it
  was first set with.** `resolveName` maps every `setHeader`/`getHeader`/
  `clearHeader`/`getEncodedHeader` call onto the existing key, so `setHeader('to')`
  after `addTo()` replaces `To` and is emitted as `To`. Code that inspects
  `getHeaders()` keys directly, or compares names, must not assume the canonical
  spelling (`getEncodedMessage` finds `Content-Type` with `strcasecmp` for this
  reason).
- **Injection prevention happens in `setHeader`, not at encode time.** String
  header values have `\r\n` collapsed to a space and must be valid UTF-8; email
  maps validate each address (`email`) and reject names containing line
  separators. Treat `setHeader` as the security boundary.
- **Addresses are normalized at set time too:** an internationalized domain in an
  email map key is converted to punycode (`toAsciiEmail`, needs ext-intl, the
  local part untouched; without ext-intl the address stays as given). This runs
  only for email maps; string headers that carry an address (`Return-Path`,
  `List-Unsubscribe`) are converted by their own setters (`setReturnPath`,
  `setUnsubscribe`), not by `setHeader`.
- `getEncodedHeader` produces RFC 2047 encoded-words via `iconv_mime_encode` with
  **scheme `B` (base64) because "Q is broken"** (per the code). Three shapes are
  handled specially: email lists (encoded display-name + `<addr>`, comma-joined),
  `Content-Disposition` filenames (quoted, RFC 2231 value), and plain values.
  Folding at `LineLength` (76) with tab continuation is volatile line-level
  mechanics — do not treat its exact offsets as contract.
- **A display name is quoted on two different tests, one per path.** Emitted
  literally, anything outside `atext` becomes a `quoted-string` (RFC 5322 syntax).
  Emitted as an encoded-word, quotes are not syntax: RFC 2047 decoding happens
  after the field is parsed, so quotes added before encoding stay visible in the
  decoded name. That path quotes only for `,;:<>@"\`, the characters a receiver
  re-parsing the decoded phrase could turn into another address (#102).
- `Message::$defaultHeaders` is a **mutable static** (`MIME-Version`,
  `X-Mailer`), applied in the constructor — changing it affects every
  subsequently created message, and since `X-Mailer` is in DKIM's default
  `signHeaders`, removing it also changes what gets signed.

## `setHtmlBody` has three implicit side effects

`setHtmlBody` runs the `HtmlComposer` pipeline, which does more than set the HTML:

1. optional image embedding (when `$basePath` is given); **CSS inlining is never
   triggered by `setHtmlBody`** — it requires using `HtmlComposer` directly with
   `inlineCss()`;
2. **if the subject is unset, it is taken from `<title>`**;
3. `setRawHtmlBody` stores the HTML;
4. **if the plain-text body is unset, a text alternative is auto-generated** from
   the HTML (`htmlToText`).

Both (2) and (4) fire **only when the target is empty** (subject via intentional
loose `== null`, body via `=== ''`), so **call order matters**:
`setSubject`/`setBody` before vs after `setHtmlBody` changes the result. Image embedding rewrites `<img src>`,
`<body background>`, `url(...)`, and `[[file]]` placeholders that are **relative**
(no scheme, not `/`- or `#`-anchored) into `cid:` references, adding an embedded
file per unique path (matches are replaced in reverse to keep byte offsets valid).
`[[file]]` can come from escaped user text, so it embeds only an image inside the
base path (no `..` segment); anything else is left as text. The check is lexical
on purpose: symlinks inside the base path are followed, like a shared `images/`
directory of a deployment.

## CssInliner: per-property cascade, silent selectors, re-serialized output

`CssInliner::inline()` parses the document with `Dom\HTMLDocument` (PHP 8.4+) and
applies collected rules as inline styles. What matters when changing it:

- **The cascade is resolved per property, one winner written.** `compete` ranks
  each declaration as `[!important, inline tier, ids, classes, types, source
  order]` and keeps the highest, so the order is `!important` > existing inline
  `style` > specificity > source order (`<style>` tag rules first, then
  `addCss()` rules). The rank always ends with the order, so two ranks never tie.
  Winners are written in source order, the existing inline declarations last;
  the `!important` suffix stays in the written value.
- **Specificity is per comma-separated part.** `splitSelector` splits the list
  (commas inside `[]`/`()` do not count) and each part is queried and scored on
  its own by `computeSpecificity`. `:where()` scores zero; `:not()`/`:is()`/
  `:has()` **sum** their arguments instead of taking the most specific one, a
  deliberate approximation.
- **Property names are lowercased, custom properties (`--x`) are not.** Folding
  happens in `parseBlock`, so `COLOR` and `color` compete as one property.
- **An existing inline style that cannot be parsed is kept verbatim.** If it
  contains a brace (it would smuggle rules into the `x{...}` wrapper of
  `parseDeclarations`) or fails to tokenize, it does not compete and is appended
  unchanged after the generated declarations, where it still wins in the client.
  An element no rule matches is not touched at all.
- **Selector errors are silent, parse errors loud.** A selector part that
  `querySelectorAll` rejects (`DOMException`) is skipped, without taking the other
  parts of its list down; the rule simply never applies there, with no error.
  Unlexable stylesheet CSS, by contrast, throws `InvalidArgumentException` from
  the tokenizer.
- **Output is re-serialized** via `saveHtml()`, not surgically edited: a
  fragment gets wrapped in `<html><body>`, formatting may be normalized.
  `<style>` tags are preserved (keeps `@media` intact); the parser skips
  @-rule blocks entirely and resolves CSS nesting (incl. `&`).

A few winning properties additionally generate HTML attributes on allowed tags
(`bgcolor`, `width`, `height`, `cellspacing`) for Outlook; `align`/`valign` are
deliberately excluded (different semantics than CSS). The numeric ones are
emitted only for a plain number with an optional unit (the unit dropped, `%`
kept); `auto`, `calc()` and the like produce no attribute, since a cast would
yield `width="0"` (`attributeValue`).

## DKIM signs the built message, in a fixed header order

`DkimSigner::generateSignedMessage` signs `$message->build()` output:

- The algorithm follows the key (`detectAlgorithm`): a raw base64 key of 32 or
  64 bytes is Ed25519 (`ed25519-sha256`, RFC 8463, needs ext-sodium), anything
  else is treated as PEM RSA (`rsa-sha256`, needs ext-openssl). The constructor
  throws `NotSupportedException` when the needed extension is missing.
- Canonicalization is **`relaxed/simple`**: headers relaxed (lowercased name,
  every whitespace run collapsed to one space), body simple (only newline-normalized
  to CRLF with a single trailing CRLF). `bh` is the base64 SHA-256 of that body.
  There is deliberately **no `l=` tag**: a body length limit would let anyone
  append content under a valid signature.
- The hashed header set is `signHeaders` plus any `oversignHeaders` not already in
  it, plus the `DKIM-Signature` header itself (with an empty `b=`). The `h=` tag
  lists those present in the message, then **every `oversignHeaders` name once
  more** (RFC 6376, §3.7): the extra mention binds a non-existent instance, so a
  header added in transit breaks the signature. `getSignedHeaders` (what `h=`
  says) and `getHashedHeaders` (what is canonicalized) must stay in step.
- **Signed headers are ordered by their index in the hashed list, not by their
  order in the message** (`array_search` → `ksort`). Change `signHeaders` and you
  change what is signed *and in what order*. Matching is case-insensitive, so the
  message spelling need not match the configuration.
- The `DKIM-Signature` header is **appended at the end of the header block**.

## SMTP transport: Bcc travels in the envelope, dots are stuffed

`SmtpMailer::send`:

- **`Bcc` is stripped from the message copy but still used for delivery.**
  Recipients for `RCPT TO` are gathered from `To` + `Cc` + `Bcc` headers; the
  `Bcc` header is removed before the body is generated so recipients never see it.
  If neither `To` nor `Cc` remains (Bcc-only mail), a synthetic
  `To: undisclosed-recipients: ;` header is added to the copy.
  `MAIL FROM` is `Return-Path` if set, else the first `From` address.
- **DATA is dot-stuffed** (`^.` → `..`) per SMTP, and terminated by a lone `.`.
- Signing (if a `Signer` is set) replaces `generateMessage()` and happens on the
  same stripped copy, **before** connecting.
- **Any throwable tears the connection down** (`catch (\Throwable)` →
  `disconnect()` → rethrow), not just `SmtpException`: an access-token callback may
  throw anything and the session state is then unknown. A persistent connection
  is probed with `NOOP` before reuse (`isAlive`) and reopened if the server
  dropped it.
- Without an explicit port: 465 for `ssl` (implicit TLS), 587 for `tls`
  (STARTTLS), 25 otherwise. STARTTLS negotiates **only TLS 1.2/1.3**.
- Authentication runs when a username or an access token is set, and uses only a
  mechanism the server advertises in `EHLO`: XOAUTH2 when `setAccessToken()` was
  called (the callback is resolved per connection; a username is still required),
  else `PLAIN`, else `LOGIN`. A missing mechanism throws a **permanent**
  `SmtpException`.
- `write()` loops over partial `fwrite()` in fixed chunks; `read()` enforces the
  timeout as a deadline even while data keeps arriving, caps the response size,
  and treats a line whose 4th character is a space or line break (`NNN ` / bare
  `NNN`) as the final response line.
- A rejected reply becomes `SmtpException::fromReply()`: 5xx is permanent, 4xx
  transient (see FallbackMailer).

## SendmailMailer strips To/Subject *after* signing

`mail()` adds `To` and `Subject` itself (they are passed as separate arguments),
so `SendmailMailer::send` removes both (in any spelling, with their folded
continuation lines) from the generated header block,
**after** DKIM signing, so the signature still covers them. The envelope sender
(`-f` + first `From` address) is appended to `commandArgs` automatically unless
disabled via `setEnvelopeSender(false)`.

## Interceptor: redirect rewrites a clone, `$onSent` gets the original

`Interceptor::send` with a redirect address rewrites a **clone**: every leaky
header (`To`, `Cc`, `Bcc`, `Disposition-Notification-To`,
`X-Confirm-Reading-To`) is moved to an `X-Original-*` counterpart, `To` becomes
the redirect address, and the subject gets an optional prefix. The `$onSent`
event always receives the **original** message (with real recipients — this is
what `MailPanel` displays), and fires on failure too (the exception is
rethrown).

## FallbackMailer

Tries each mailer in order, repeating the whole list `retryCount` times (default
3) with `retryWaitTime` between rounds; fires `onFailure` per failed attempt and
throws `FallbackMailerException` (carrying all `$failures`) only when every attempt
fails. **Only `SendException` triggers the fallback** — anything else a mailer
throws propagates immediately, aborting the whole chain with no `onFailure`.
A failure whose `isPermanent()` is true (a 5xx reply, an unsupported auth
mechanism) drops that mailer from the remaining rounds; the loop ends early once
no candidate is left. Mailers that throw `SendException` should therefore set
`permanent` only when a retry is pointless, or they lose their later attempts.

## FileMailer

`FileMailer::send` writes the generated (optionally signed) message as is to
`<dir>/<Y-m-d-H-i-s>-<random>.eml`; unlike SMTP it strips nothing, so the `Bcc`
header stays in the file. A write failure becomes `SendException`.

## Navigation map

| Concern | Where |
|---|---|
| MIME tree shape/order | `Message::build` |
| Body encoding heuristic + `7bit` strip | `Message::build`, `MimePart::getEncodedMessage` |
| Header encoding, injection guard | `MimePart::setHeader`, `getEncodedHeader`, `encodeSequence` |
| Header name case, IDN punycode | `MimePart::resolveName`, `toAsciiEmail` |
| Subject/alt/cid side effects | `HtmlComposer::applyTo`, `embedImagesInHtml` |
| CSS cascade, specificity, attributes | `CssInliner::inline`, `compete`, `computeSpecificity`, `attributeValue` |
| CSS parsing, property case | `CssInliner::parseBlock`, `parseDeclarations`, `splitSelector` |
| DKIM algorithm, canonicalization, header order | `DkimSigner::detectAlgorithm`, `computeSignature`, `getSignedHeaders` |
| Bcc envelope, dot-stuffing | `SmtpMailer::send` |
| SMTP connection, TLS, auth | `SmtpMailer::connect`, `authenticate`, `isAlive` |
| Permanent vs transient failure | `SendException::isPermanent`, `SmtpException::fromReply` |
| To/Subject stripping, envelope sender | `SendmailMailer::send` |
| Redirect rewrite, `$onSent` event | `Interceptor::send`, `rewrite` |
| Retry/fallback | `FallbackMailer::send` |
| .eml output | `FileMailer::send` |
