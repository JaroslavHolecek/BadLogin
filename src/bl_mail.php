<?php

declare(strict_types=1);

/**
 * Sends an HTML email using the configured sender address.
 *
 * @param string $to        Recipient email address.
 * @param string $subject   Email subject.
 * @param string $body      HTML email body.
 * @param array  $bl_config BadLogin configuration.
 *
 * @return bool True when the mail function reports success.
 */
function bl_mail_send(string $to, string $subject, string $body, array $bl_config): bool
{
    $mail_from = bl_config_get('mail_from', $bl_config);

    $sanitize = static fn(string $v): string => str_replace(["\r", "\n"], '', $v);

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
    ];

    if ($mail_from !== '') {
        $headers[] = 'From: ' . $sanitize($mail_from);
    }

    return mail($to, $sanitize($subject), $body, implode("\r\n", $headers));
}
