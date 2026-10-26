<?php declare(strict_types=1);

/**
 * Test: MailPanel.
 */

use Nette\Bridges\MailTracy\MailPanel;
use Nette\Mail\{Mailer, Message};
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


function createMailer(): Mailer
{
	return new class implements Mailer {
		public function send(Message $mail): void
		{
		}
	};
}


test('panel shows the envelope', function () {
	$mail = new Message;
	$mail->setFrom('John Doe <doe@example.com>');
	$mail->addTo('jane@example.com');
	$mail->addCc('cc@example.com');
	$mail->addBcc('bcc@example.com');
	$mail->setSubject('Hello Jane!');
	$mail->setBody('Sample text');

	$panel = new MailPanel;
	Assert::null($panel->getPanel());
	$panel->recordSent(createMailer(), $mail, null);
	$html = $panel->getPanel();

	Assert::contains('Emails sent: 1', $html);
	Assert::contains('John Doe &lt;doe@example.com&gt;', $html);
	Assert::contains('jane@example.com', $html);
	Assert::contains('Cc: cc@example.com', $html);
	Assert::contains('Bcc: bcc@example.com', $html);
	Assert::contains('Hello Jane!', $html);
	Assert::contains('<td>OK</td>', $html);
});


test('text-only message shows the text, no iframe and no attachments', function () {
	$mail = new Message;
	$mail->setBody("Line <1>\nLine 2");

	$panel = new MailPanel;
	$panel->recordSent(createMailer(), $mail, null);
	$html = $panel->getPanel();

	Assert::contains('<a class="tracy-toggle tracy-collapsed"', $html);
	Assert::contains("<pre>Line &lt;1&gt;\nLine 2</pre>", $html);
	Assert::notContains('<iframe', $html);
	Assert::notContains(' B</td>', $html);
});


test('HTML body is rendered in a sandboxed iframe with escaped srcdoc', function () {
	$mail = new Message;
	$mail->setHtmlBody('<p class="x">Hello &amp; <b>world</b></p>');

	$panel = new MailPanel;
	$panel->recordSent(createMailer(), $mail, null);
	$html = $panel->getPanel();

	Assert::contains(
		'<iframe sandbox srcdoc="&lt;p class=&quot;x&quot;&gt;Hello &amp;amp; &lt;b&gt;world&lt;/b&gt;&lt;/p&gt;"></iframe>',
		$html,
	);
	Assert::contains('<pre>Hello &amp; world</pre>', $html); // generated text alternative
});


test('cid: references become data: URIs', function () {
	$mail = new Message;
	$part = $mail->addEmbeddedFile('logo.png', 'PNG-DATA', 'image/png');
	$cid = substr($part->getHeader('Content-ID'), 1, -1);
	$mail->setHtmlBody("<img src='cid:$cid'>");

	$panel = new MailPanel;
	$panel->recordSent(createMailer(), $mail, null);
	$html = $panel->getPanel();

	Assert::contains('srcdoc="&lt;img src=&apos;data:image/png;base64,' . base64_encode('PNG-DATA') . '&apos;&gt;"', $html);
	Assert::notContains('cid:', $html);
});


test('attachments are listed with name, type and size', function () {
	$mail = new Message;
	$mail->setBody('See attached');
	$mail->addAttachment('report "final".pdf', str_repeat('x', 1234), 'application/pdf');

	$panel = new MailPanel;
	$panel->recordSent(createMailer(), $mail, null);
	$html = $panel->getPanel();

	Assert::contains('<td>report &quot;final&quot;.pdf</td>', $html);
	Assert::contains('<td>application/pdf</td>', $html);
	Assert::contains('<td>1234 B</td>', $html);
	Assert::notContains(str_repeat('x', 1234), $html);
});


test('beyond maxMessages nothing is stored, but the count goes on', function () {
	$panel = new MailPanel;
	$panel->maxMessages = 1;
	foreach (['First', 'Second', 'Third'] as $subject) {
		$mail = new Message;
		$mail->setSubject($subject);
		$panel->recordSent(createMailer(), $mail, null);
	}

	$messages = (fn() => $this->messages)->call($panel);
	Assert::count(1, $messages);

	$html = $panel->getPanel();
	Assert::contains('Emails sent: 3', $html);
	Assert::contains('First', $html);
	Assert::notContains('Second', $html);
	Assert::contains('...and 2 more', $html);
});


test('getEmbeddedFiles() returns embedded parts as a list', function () {
	$mail = new Message;
	Assert::same([], $mail->getEmbeddedFiles());

	$a = $mail->addEmbeddedFile('a.png', 'A', 'image/png');
	$b = $mail->addEmbeddedFile('b.png', 'B', 'image/png');
	$part = new Nette\Mail\MimePart;
	$mail->addInlinePart($part);

	Assert::same([$a, $b, $part], $mail->getEmbeddedFiles());
});
