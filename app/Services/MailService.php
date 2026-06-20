<?php

namespace App\Services;

use App\Models\MailLog;
use App\Models\SiteSetting;
use PHPMailer\PHPMailer\PHPMailer;

class MailService
{
    protected SiteSetting $settings;
    protected MailLog $logs;

    public function __construct(?SiteSetting $settings = null, ?MailLog $logs = null)
    {
        $this->settings = $settings ?? new SiteSetting();
        $this->logs = $logs ?? new MailLog();
    }

    public function isEnabled(): bool
    {
        return $this->settings->get('mail_enabled', '0') === '1' && $this->isConfigured();
    }

    public function isConfigured(): bool
    {
        return $this->settings->get('mail_host') !== ''
            && (int)$this->settings->get('mail_port', '0') > 0
            && filter_var($this->settings->get('mail_from_email'), FILTER_VALIDATE_EMAIL);
    }

    public function sendTemplate(array $to, string $subject, string $template, array $data = [], array $attachments = []): bool
    {
        return $this->send($to, $subject, view($template, $data, false), '', $attachments);
    }

    public function send(array $to, string $subject, string $htmlBody = '', string $textBody = '', array $attachments = []): bool
    {
        $recipients = array_values(array_filter(array_map('trim', $to), static fn(string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false));
        if (!$recipients) {
            return false;
        }

        if (!$this->isEnabled() || !class_exists(PHPMailer::class)) {
            $this->logRecipients($recipients, $subject, 'failed', return_translation('mail_error_not_configured'));
            return false;
        }

        try {
            $mail = new PHPMailer(true);
            $config = $this->mailConfig();

            $mail->SMTPDebug = 0;
            $mail->isSMTP();
            $mail->Host = $config['host'];
            $mail->Port = $config['port'];
            $mail->SMTPAuth = $config['username'] !== '';
            $mail->Username = $config['username'];
            $mail->Password = $config['password'];
            if ($config['encryption'] !== 'none') {
                $mail->SMTPSecure = $config['encryption'];
            }

            $mail->CharSet = 'UTF-8';
            $mail->setFrom($config['from_email'], $config['from_name']);
            if ($config['reply_to_email'] !== '') {
                $mail->addReplyTo($config['reply_to_email']);
            }
            foreach ($recipients as $recipient) {
                $mail->addAddress($recipient);
            }
            foreach ($attachments as $attachment) {
                if (is_string($attachment) && is_file($attachment)) {
                    $mail->addAttachment($attachment);
                }
            }

            $mail->Subject = $subject;
            $mail->isHTML($htmlBody !== '');
            $mail->Body = $htmlBody !== '' ? $htmlBody : nl2br(htmlSC($textBody));
            if ($textBody !== '') {
                $mail->AltBody = $textBody;
            }

            $sent = $mail->send();
            $this->logRecipients($recipients, $subject, $sent ? 'success' : 'failed', $sent ? '' : $mail->ErrorInfo);

            return $sent;
        } catch (\Throwable $exception) {
            $this->logRecipients($recipients, $subject, 'failed', $exception->getMessage());
            log_error_details('Mail send error', [
                'Subject' => $subject,
                'Recipients' => $recipients,
            ], $exception);
            return false;
        }
    }

    public function sendTest(string $email): bool
    {
        return $this->send(
            [$email],
            return_translation('admin_mail_test_subject'),
            '<p>' . htmlSC(return_translation('admin_mail_test_body')) . '</p>',
            return_translation('admin_mail_test_body')
        );
    }

    public function mailConfig(): array
    {
        $encryption = strtolower($this->settings->get('mail_encryption', $this->settings->get('mail_secure', 'none')));
        if (!in_array($encryption, ['none', 'ssl', 'tls'], true)) {
            $encryption = 'none';
        }

        return [
            'host' => $this->settings->get('mail_host'),
            'port' => max(0, (int)$this->settings->get('mail_port', '0')),
            'encryption' => $encryption,
            'username' => $this->settings->get('mail_username'),
            'password' => $this->settings->get('mail_password'),
            'from_email' => $this->settings->get('mail_from_email'),
            'from_name' => $this->settings->get('mail_from_name', $this->settings->get('site_title', SITE_NAME)),
            'reply_to_email' => $this->settings->get('mail_reply_to_email'),
        ];
    }

    protected function logRecipients(array $recipients, string $subject, string $status, string $error = ''): void
    {
        foreach ($recipients as $recipient) {
            $this->logs->record($recipient, $subject, $status, $error);
        }
    }
}
