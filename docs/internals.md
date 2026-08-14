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

- **Injection prevention happens in `setHeader`, not at encode time.** String
  header values have `\r\n` collapsed to a space and must be valid UTF-8; email
  maps validate each address (`email`) and reject names containing line
  separators. Treat `setHeader` as the security boundary.
- `getEncodedHeader` produces RFC 2047 encoded-words via `iconv_mime_encode` with
  **scheme `B` (base64) because "Q is broken"** (per the code). Three shapes are
  handled specially: email lists (encoded display-name + `<addr>`, comma-joined),
  `Content-Disposition` filenames (quoted, RFC 2231 value), and plain values.
  Folding at `LineLength` (76) with tab continuation is volatile line-level
  mechanics — do not treat its exact offsets as contract.
- **A display name is quoted on two different tests, one per path.** Emitted
  literally, a `phrase` holding anything outside `atext` (a dot, a comma, ...) has
  to become a `quoted-string` — that is real RFC 5322 syntax and the quotes are
  delimiters. Emitted as an encoded-word, quotes are *not* syntax: per RFC 2047
  §6.2 decoding happens **after** the field body is parsed into tokens, so the
  encoding already shields whatever the name contains, and quotes added before
  encoding land inside the base64 payload and decode as part of the name the
  recipient sees. The encoded path therefore quotes only for receivers that
  re-parse the decoded phrase, and only for `,;:<>@"\` — characters able to
  restructure an address list (#102 was a Gmail DKIM failure caused by a comma).
  Diacritics and dots cannot restructure anything, so they stay bare.
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

## CssInliner: order-based cascade, silent selectors, re-serialized output

`CssInliner::inline()` parses the document with `Dom\HTMLDocument` (PHP 8.4+) and
applies collected rules as inline styles. Three deviations from real CSS matter:

- **No selector specificity.** The cascade is rule order only: `<style>` tag
  rules first, then `addCss()` rules; per element, later declarations win
  (`array_merge` on `property => value`). The element's **existing inline
  `style` always wins** — collected declarations are prepended before it.
  `!important` has no special handling.
- **Selector errors are silent, parse errors loud.** A selector that
  `querySelectorAll` rejects (`DOMException`) is skipped — the rule simply never
  applies, with no error. Unlexable CSS, by contrast, throws
  `InvalidArgumentException` from the tokenizer.
- **Output is re-serialized** via `saveHtml()`, not surgically edited: a
  fragment gets wrapped in `<html><body>`, formatting may be normalized.
  `<style>` tags are preserved (keeps `@media` intact); the parser skips
  @-rule blocks entirely and resolves CSS nesting (incl. `&`).

A few properties additionally generate HTML attributes on allowed tags
(`bgcolor`, `width`, `height`, `cellspacing`) for Outlook; `align`/`valign` are
deliberately excluded (different semantics than CSS).

## DKIM signs the built message, in a fixed header order

`DkimSigner::generateSignedMessage` signs `$message->build()` output:

- Canonicalization is **`relaxed/simple`**: headers relaxed (lowercased name,
  runs of whitespace collapsed), body simple (only newline-normalized to CRLF with
  a single trailing CRLF). `bh` is the base64 SHA-256 of that body; `l` is its
  length.
- The signed header set is `signHeaders` **filtered to those present**, plus the
  `DKIM-Signature` header itself (with an empty `b=`).
- **Signed headers are ordered by their index in `signHeaders`, not by their order
  in the message** (`array_search` → `ksort`). Change the `signHeaders` list and
  you change what is signed *and in what order*. Matching is **case-sensitive**
  against the header names as emitted — a header set with different casing than
  its `signHeaders` entry silently drops out of the signature.
- The `DKIM-Signature` header is **prepended** to the final message.

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
  same stripped copy.
- Connection details (SSL port 465 vs 25, `STARTTLS`, `EHLO`/`HELO` fallback,
  `AUTH PLAIN` vs `AUTH LOGIN` by advertised mechanisms, `persistent` reuse) are
  standard SMTP; `read()` treats a line whose 4th character is a space or line
  break (`NNN ` / bare `NNN`) as the final response line.

## SendmailMailer strips To/Subject *after* signing

`mail()` adds `To` and `Subject` itself (they are passed as separate arguments),
so `SendmailMailer::send` removes both lines from the generated header block —
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

## Navigation map

| Concern | Where |
|---|---|
| MIME tree shape/order | `Message::build` |
| Body encoding heuristic + `7bit` strip | `Message::build`, `MimePart::getEncodedMessage` |
| Header encoding, injection guard | `MimePart::setHeader`, `getEncodedHeader`, `encodeSequence` |
| Subject/alt/cid side effects | `HtmlComposer::applyTo`, `embedImagesInHtml` |
| CSS cascade, selector handling | `CssInliner::inline`, `parseBlock` |
| DKIM canonicalization & header order | `DkimSigner::computeSignature`, `getSignature` |
| Bcc envelope, dot-stuffing, auth | `SmtpMailer::send`, `connect` |
| To/Subject stripping, envelope sender | `SendmailMailer::send` |
| Redirect rewrite, `$onSent` event | `Interceptor::send`, `rewrite` |
| Retry/fallback | `FallbackMailer::send` |
