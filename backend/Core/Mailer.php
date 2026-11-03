<?php

declare(strict_types=1);

namespace Core;

/**
 * Plain-text mail over SMTP, no library. Settings (all optional except MAIL_HOST):
 * MAIL_HOST, MAIL_PORT, MAIL_SECURE ('tls' = encrypted from the first byte, usually port 465;
 * 'starttls' = upgrade after the greeting, usually 587; empty = plain, never use it over the internet),
 * MAIL_USER, MAIL_PASS, MAIL_FROM.
 * Without MAIL_HOST, outside production, the message is only written to the error log so development works offline.
 */
final class Mailer
{
	/** @throws \RuntimeException when the SMTP server cannot be reached or refuses the message */
	public static function send(string $to, string $subject, string $body): void
	{
		$host = Env::get('MAIL_HOST');
		if ($host === '') {
			if (Env::get('APP_ENV') === 'production') {
				throw new \RuntimeException('MAIL_HOST is not set.');
			}
			error_log("MAIL (not sent, MAIL_HOST is empty) to {$to}: {$subject} | {$body}");
			return;
		}
		// No line breaks allowed in header values: they would let a caller inject extra headers.
		$clean = static fn(string $s): string => str_replace(["\r", "\n"], '', $s);
		$to = $clean($to);
		$from = $clean(Env::get('MAIL_FROM') ?: 'no-reply@localhost');
		$secure = Env::get('MAIL_SECURE');
		$port = (int) (Env::get('MAIL_PORT') ?: ($secure === 'tls' ? 465 : ($secure === 'starttls' ? 587 : 25)));

		$fp = @stream_socket_client(($secure === 'tls' ? 'tls://' : 'tcp://') . "{$host}:{$port}", $errno, $errstr, 10);
		if ($fp === false) {
			throw new \RuntimeException("SMTP connect failed: {$errstr}");
		}
		stream_set_timeout($fp, 10);
		$hello = 'EHLO ' . (gethostname() ?: 'localhost');
		try {
			self::expect($fp, '220');
			self::command($fp, $hello, '250');
			if ($secure === 'starttls') {
				self::command($fp, 'STARTTLS', '220');
				if (stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
					throw new \RuntimeException('SMTP STARTTLS failed.');
				}
				self::command($fp, $hello, '250');
			}
			$user = Env::get('MAIL_USER');
			if ($user !== '') {
				self::command($fp, 'AUTH LOGIN', '334');
				self::command($fp, base64_encode($user), '334');
				self::command($fp, base64_encode(Env::get('MAIL_PASS')), '235');
			}
			self::command($fp, "MAIL FROM:<{$from}>", '250');
			self::command($fp, "RCPT TO:<{$to}>", '250');
			self::command($fp, 'DATA', '354');
			$headers = [
				'Date: ' . date('r'),
				"From: <{$from}>",
				"To: <{$to}>",
				'Subject: =?UTF-8?B?' . base64_encode($clean($subject)) . '?=',
				'Message-ID: <' . bin2hex(random_bytes(8)) . '@' . (explode('@', $from)[1] ?? 'localhost') . '>',
				'MIME-Version: 1.0',
				'Content-Type: text/plain; charset=UTF-8',
				'Content-Transfer-Encoding: base64',
			];
			// A base64 body has no line starting with ".", so no dot-stuffing is needed; the lone "." ends DATA.
			self::command($fp, implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($body), 76, "\r\n") . '.', '250');
			fwrite($fp, "QUIT\r\n");
		} finally {
			fclose($fp);
		}
	}

	/** @param resource $fp */
	private static function command($fp, string $line, string $expect): void
	{
		fwrite($fp, $line . "\r\n");
		self::expect($fp, $expect);
	}

	/** Reads one (possibly multi-line) SMTP reply and checks its status code. @param resource $fp */
	private static function expect($fp, string $code): void
	{
		do {
			$line = fgets($fp, 512);
			if ($line === false) {
				throw new \RuntimeException('SMTP connection lost.');
			}
		} while (isset($line[3]) && $line[3] === '-');
		if (!str_starts_with($line, $code)) {
			throw new \RuntimeException('SMTP error: ' . trim($line));
		}
	}
}
