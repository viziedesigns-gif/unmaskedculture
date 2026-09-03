<?php
/**
 * Email Service for CompassionCloud
 * Handles sending emails via SMTP using PHPMailer
 */

// Suppress errors during loading
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/follow_up_tasks.php';
require_once __DIR__ . '/../includes/auth_return.php';

// Load PHPMailer classes - do this before use statements
$phpmailer_loaded = false;
$phpmailer_paths = [
    __DIR__ . '/phpmailer/src/PHPMailer.php',
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php'
];

foreach ($phpmailer_paths as $path) {
    if (file_exists($path)) {
        try {
            require_once $path;
            // Also load Exception and SMTP if using direct file
            if (strpos($path, 'PHPMailer.php') !== false) {
                $base_dir = dirname($path);
                if (file_exists($base_dir . '/Exception.php')) {
                    require_once $base_dir . '/Exception.php';
                }
                if (file_exists($base_dir . '/SMTP.php')) {
                    require_once $base_dir . '/SMTP.php';
                }
            }
            $phpmailer_loaded = true;
            break;
        } catch (\Exception $e) {
            error_log("Failed to load PHPMailer from {$path}: " . $e->getMessage());
            continue;
        } catch (\Error $e) {
            error_log("Failed to load PHPMailer from {$path}: " . $e->getMessage());
            continue;
        }
    }
}

class EmailService {
    private $link;
    private $phpmailer_available;
    
    public function __construct($db_link) {
        $this->link = $db_link;
        $this->phpmailer_available = class_exists('PHPMailer\PHPMailer\PHPMailer');
        ensureEmailQueueSchema($this->link);
    }
    
    /**
     * Send email using PHPMailer or fallback to mail()
     * This is a public method that can be called from send_email.php
     */
    public function sendEmail($to, $subject, $body, $is_html = true) {
        if ($this->phpmailer_available) {
            return $this->sendEmailPHPMailer($to, $subject, $body, $is_html);
        } else {
            return $this->sendEmailBasic($to, $subject, $body, $is_html);
        }
    }
    
    /**
     * Send email using PHPMailer
     */
    private function sendEmailPHPMailer($to, $subject, $body, $is_html = true) {
        $mail = null;
        try {
            // Check if constants are defined
            if (!defined('SMTP_HOST') || !defined('SMTP_USER') || !defined('SMTP_PASS')) {
                throw new \Exception('SMTP configuration not found in config.php');
            }
            
            // Try different PHPMailer class names depending on installation
            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            } elseif (class_exists('PHPMailer')) {
                $mail = new \PHPMailer(true);
            } else {
                throw new \Exception('PHPMailer class not found');
            }
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->Port = SMTP_PORT;
            $mail->Timeout = 15;
            $mail->CharSet = 'UTF-8';
            $mail->SMTPDebug = 0; // Set to 2 for debugging, 0 for production
            $mail->Debugoutput = function($str, $level) {
                error_log("PHPMailer: $str");
            };
            
            // Recipients
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML($is_html);
            $mail->Subject = $subject;
            $mail->Body = $body;
            // Always provide plain text alternative
            $mail->AltBody = strip_tags($body);
            
            $mail->send();
            return ['success' => true, 'message' => 'Email sent successfully'];
        } catch (\Exception $e) {
            $error_info = ($mail && isset($mail->ErrorInfo)) ? $mail->ErrorInfo : $e->getMessage();
            error_log("PHPMailer error: " . $error_info);
            return ['success' => false, 'message' => "Email could not be sent. Error: {$error_info}"];
        } catch (\Error $e) {
            $error_info = ($mail && isset($mail->ErrorInfo)) ? $mail->ErrorInfo : $e->getMessage();
            error_log("PHPMailer fatal error: " . $error_info);
            return ['success' => false, 'message' => "Email could not be sent. Error: {$error_info}"];
        }
    }
    
    /**
     * Fallback: Send email using basic mail() function
     */
    private function sendEmailBasic($to, $subject, $body, $is_html = true) {
        $headers = "From: " . (defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'CompassionCloud') . " <" . (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'noreply@compassioncloud.org') . ">\r\n";
        $headers .= "Reply-To: " . (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'noreply@compassioncloud.org') . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        if ($is_html) {
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $body = strip_tags($body);
        }
        
        if (mail($to, $subject, $body, $headers)) {
            return ['success' => true, 'message' => 'Email sent successfully'];
        } else {
            return ['success' => false, 'message' => 'Email could not be sent'];
        }
    }
    
    /**
     * Queue an email for later sending
     */
    public function queueEmail($to, $subject, $body, $scheduled_for = null, $related_type = null, $related_id = null, $event_key = null, $dedupe_key = null) {
        ensureEmailQueueSchema($this->link);
        $sql = "INSERT INTO email_queue (to_email, subject, body, scheduled_for, related_type, related_id, event_key, dedupe_key)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE to_email = VALUES(to_email), subject = VALUES(subject), body = VALUES(body),
                    scheduled_for = VALUES(scheduled_for), related_type = VALUES(related_type), related_id = VALUES(related_id),
                    event_key = VALUES(event_key), status = 'pending', attempts = 0, error_message = NULL, sent_at = NULL";
        if ($stmt = mysqli_prepare($this->link, $sql)) {
            mysqli_stmt_bind_param($stmt, "sssssiss", $to, $subject, $body, $scheduled_for, $related_type, $related_id, $event_key, $dedupe_key);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return ['success' => true, 'message' => 'Email queued successfully'];
            } else {
                $error = mysqli_error($this->link);
                mysqli_stmt_close($stmt);
                return ['success' => false, 'message' => "Failed to queue email: {$error}"];
            }
        } else {
            return ['success' => false, 'message' => 'Failed to prepare queue statement'];
        }
    }

    /**
     * Deliver a just-queued email immediately while retaining the queue row for
     * delivery history and retry handling if SMTP is temporarily unavailable.
     */
    public function deliverQueuedEmailNow($dedupe_key) {
        if (!$dedupe_key) return ['success' => false, 'message' => 'Missing email delivery key'];

        $stmt = mysqli_prepare($this->link, "SELECT id, to_email, subject, body, status, attempts, scheduled_for FROM email_queue WHERE dedupe_key = ? LIMIT 1");
        if (!$stmt) return ['success' => false, 'message' => 'Unable to load queued email'];
        mysqli_stmt_bind_param($stmt, 's', $dedupe_key);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        if (!$row) return ['success' => false, 'message' => 'Queued email was not found'];
        if ($row['status'] === 'sent') return ['success' => true, 'message' => 'Email already sent'];
        if ($row['status'] !== 'pending') return ['success' => false, 'message' => 'Email is not pending'];
        if (!empty($row['scheduled_for']) && strtotime($row['scheduled_for']) > time()) {
            return ['success' => true, 'message' => 'Email queued for scheduled delivery'];
        }

        $send_result = $this->sendEmail($row['to_email'], $row['subject'], $row['body']);
        $email_id = (int)$row['id'];
        if (!empty($send_result['success'])) {
            $update = mysqli_prepare($this->link, "UPDATE email_queue SET status = 'sent', sent_at = NOW(), error_message = NULL WHERE id = ?");
            if ($update) {
                mysqli_stmt_bind_param($update, 'i', $email_id);
                mysqli_stmt_execute($update);
                mysqli_stmt_close($update);
            }
            return $send_result;
        }

        $attempts = (int)$row['attempts'] + 1;
        $status = $attempts >= 3 ? 'failed' : 'pending';
        $error_message = substr($send_result['message'] ?? 'Email delivery failed', 0, 255);
        $update = mysqli_prepare($this->link, "UPDATE email_queue SET attempts = ?, status = ?, error_message = ? WHERE id = ?");
        if ($update) {
            mysqli_stmt_bind_param($update, 'issi', $attempts, $status, $error_message, $email_id);
            mysqli_stmt_execute($update);
            mysqli_stmt_close($update);
        }
        error_log('Immediate queued email delivery failed: ' . $error_message);
        return $send_result;
    }

    private function staffTemplate($eyebrow, $title, $intro, $details, $button_label, $button_url, $footer_note) {
        $safe_eyebrow = htmlspecialchars($eyebrow, ENT_QUOTES, 'UTF-8');
        $safe_title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safe_intro = htmlspecialchars($intro, ENT_QUOTES, 'UTF-8');
        $safe_button = htmlspecialchars($button_label, ENT_QUOTES, 'UTF-8');
        $safe_url = htmlspecialchars($button_url, ENT_QUOTES, 'UTF-8');
        $safe_footer = htmlspecialchars($footer_note, ENT_QUOTES, 'UTF-8');
        $rows = '';
        foreach ($details as $label => $value) {
            if ($value === null || $value === '') continue;
            $rows .= '<tr><td style="padding:9px 12px;color:#64748b;font-size:13px;border-bottom:1px solid #e6edf5;">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</td><td style="padding:9px 12px;color:#17263b;font-size:13px;font-weight:700;text-align:right;border-bottom:1px solid #e6edf5;">' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        }
        return '<!doctype html><html><body style="margin:0;background:#eef3f8;font-family:Arial,sans-serif;color:#334155;">'
            . '<div style="max-width:620px;margin:0 auto;padding:28px 16px;">'
            . '<div style="padding:28px;border-radius:14px 14px 0 0;background:#17263b;color:#fff;">'
            . '<div style="font-size:12px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#8bc4ff;">' . $safe_eyebrow . '</div>'
            . '<h1 style="margin:8px 0 0;font-size:24px;line-height:1.25;">' . $safe_title . '</h1></div>'
            . '<div style="padding:28px;border:1px solid #dce6f0;border-top:0;border-radius:0 0 14px 14px;background:#fff;">'
            . '<p style="margin:0 0 20px;line-height:1.65;">' . $safe_intro . '</p>'
            . '<table role="presentation" style="width:100%;border-collapse:collapse;border:1px solid #e6edf5;border-radius:10px;overflow:hidden;">' . $rows . '</table>'
            . '<div style="margin:24px 0;text-align:center;"><a href="' . $safe_url . '" style="display:inline-block;padding:13px 22px;border-radius:8px;background:#347fca;color:#fff;text-decoration:none;font-weight:700;">' . $safe_button . '</a></div>'
            . '<p style="margin:0;color:#8492a6;font-size:12px;line-height:1.55;">' . $safe_footer . '</p>'
            . '</div></div></body></html>';
    }

    public function queueNewFamilySubmission($email, $staff_name, $recipient_key, $family_id, $family_first_name, $contact_date, $task = null, $labels = [], $submitted_by = '') {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['success' => false, 'message' => 'No valid staff email'];
        $details = [
            'Family first name' => $family_first_name,
            'Date of contact' => $contact_date ? date('M j, Y', strtotime($contact_date)) : '',
            'Follow-up reminder' => $task ? date('M j, Y', strtotime($task['due_date'])) : 'No follow-up scheduled'
        ];
        $labels = array_values(array_unique(array_filter(array_map('trim', (array)$labels), static function ($label) {
            return $label !== '';
        })));
        if (!empty($labels)) {
            $details[count($labels) === 1 ? 'Label' : 'Labels'] = implode(', ', $labels);
        }
        if (trim((string)$submitted_by) !== '') {
            $details['Added by'] = trim((string)$submitted_by);
        }
        $body = $this->staffTemplate('New family added', 'A new family was added to your church', 'Hi ' . ($staff_name ?: 'there') . ', a new family record is ready for your team.', $details, 'View family in CompassionCloud', ccFamilyLoginUrl($family_id), 'Sign in to CompassionCloud to view the full family record.');
        $family_id = (int)$family_id;
        $dedupe_key = 'family:' . $family_id . ':submitted:user:' . (int)$recipient_key;
        $queue_result = $this->queueEmail($email, 'New family added: ' . $family_first_name, $body, date('Y-m-d H:i:s'), 'family', $family_id, 'family_submitted', $dedupe_key);
        if (empty($queue_result['success'])) return $queue_result;
        return $this->deliverQueuedEmailNow($dedupe_key);
    }

    public function queueFollowUpTaskReminders($task_id, $email, $staff_name, $family_id, $family_name, $request_number, $contact_date, $due_date, $preset, $holiday_name = null, $suppress_due_today = false) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['success' => false, 'message' => 'No valid staff email'];
        $label = followUpPresetLabel($preset, $holiday_name);
        $details = [
            'Family' => $family_name,
            'Request number' => $request_number,
            'Date of contact' => $contact_date ? date('M j, Y', strtotime($contact_date)) : '',
            'Follow-up' => $label,
            'Deadline' => date('M j, Y', strtotime($due_date))
        ];
        $url = ccFamilyLoginUrl($family_id);
        $due_at = $due_date . ' 08:00:00';
        $overdue_at = date('Y-m-d 08:00:00', strtotime($due_date . ' +1 day'));
        $today = date('Y-m-d');
        if ($due_date > $today || ($due_date === $today && !$suppress_due_today)) {
            $due_body = $this->staffTemplate('Follow-up due', 'A family follow-up is due today', 'Hi ' . ($staff_name ?: 'there') . ', this task is now due.', $details, 'Review family record', $url, 'Complete or reschedule the task in CompassionCloud. This email contains only limited internal task details.');
            $this->queueEmail($email, 'Follow-up due: ' . $family_name, $due_body, $due_at, 'follow_up_task', $task_id, 'due', 'followup:' . $task_id . ':due:' . $due_date);
        }
        $overdue_body = $this->staffTemplate('Follow-up overdue', 'A family follow-up needs attention', 'Hi ' . ($staff_name ?: 'there') . ', this task passed its deadline yesterday and is still open.', $details, 'Review overdue task', $url, 'If the follow-up already happened, mark the task complete. Otherwise, reschedule it from CompassionCloud.');
        return $this->queueEmail($email, 'Overdue follow-up: ' . $family_name, $overdue_body, $overdue_at, 'follow_up_task', $task_id, 'overdue', 'followup:' . $task_id . ':overdue:' . $due_date);
    }
    
    /**
     * Send welcome email to new user
     */
    public function sendWelcomeEmail($email, $name, $church_name) {
        $subject = "Welcome to " . $church_name . " on CompassionCloud";
        $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4A90E2; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background: #f9f9f9; padding: 20px; border-radius: 0 0 5px 5px; }
                .button { display: inline-block; padding: 12px 24px; background: #4A90E2; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Welcome to CompassionCloud!</h1>
                </div>
                <div class='content'>
                    <p>Hi {$name},</p>
                    <p>Welcome to <strong>{$church_name}</strong> on CompassionCloud! We're excited to have you on board.</p>
                    <p>You can now log in and start helping families in your community.</p>
                    <p>Best regards,<br>The CompassionCloud Team</p>
                </div>
            </div>
        </body>
        </html>";
        
        // Send immediately (no queueing needed for welcome emails)
        return $this->sendEmail($email, $subject, $body);
    }
    
    /**
     * Send invite email with one-time login link
     */
    public function sendInviteEmail($email, $invite_link, $church_name, $inviter_name, $expires_days = 7) {
        $subject = "You've been invited to join " . $church_name . " on CompassionCloud";
        $days_label = $expires_days == 1 ? "1 day" : "{$expires_days} days";
        $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #2F3A4A 0%, #4A90E2 100%); color: white; padding: 30px 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .header h1 { margin: 0 0 6px; font-size: 22px; }
                .header p { margin: 0; opacity: 0.9; font-size: 14px; }
                .content { background: #f9f9f9; padding: 28px 24px; border-radius: 0 0 8px 8px; border: 1px solid #e8e8e8; border-top: none; }
                .church-badge { display: inline-block; background: #4A90E2; color: white; padding: 4px 14px; border-radius: 12px; font-size: 12px; font-weight: 700; margin-bottom: 16px; }
                .button { display: inline-block; padding: 14px 32px; background: #4A90E2; color: white; text-decoration: none; border-radius: 6px; margin: 20px 0; font-weight: 600; font-size: 15px; }
                .warning { background: #fff8e1; border-left: 4px solid #ffc107; padding: 12px 16px; margin: 16px 0; border-radius: 0 4px 4px 0; font-size: 14px; }
                .footer { color: #999; font-size: 12px; margin-top: 24px; padding-top: 16px; border-top: 1px solid #e8e8e8; text-align: center; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>You're Invited!</h1>
                    <p>Join your church team on CompassionCloud</p>
                </div>
                <div class='content'>
                    <span class='church-badge'>{$church_name}</span>
                    <p>Hi there,</p>
                    <p><strong>{$inviter_name}</strong> has invited you to join <strong>{$church_name}</strong> on CompassionCloud &mdash; a platform that helps churches organize outreach, automate follow-ups, and care for their communities.</p>
                    <p>As a team member, you'll be able to:</p>
                    <ul style='color: #555; padding-left: 20px;'>
                        <li>View and manage family records</li>
                        <li>Track follow-ups and prayer requests</li>
                        <li>Collaborate with your church team</li>
                    </ul>

                    <div style='text-align: center;'>
                        <a href='{$invite_link}' class='button'>Accept Invitation &amp; Create Account</a>
                    </div>

                    <p style='font-size: 13px; color: #888;'>Or copy and paste this link into your browser:</p>
                    <p style='word-break: break-all; color: #4A90E2; background: white; padding: 10px 14px; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 13px;'>{$invite_link}</p>

                    <div class='warning'>
                        <strong>&#9200; This link expires in {$days_label}.</strong> Please create your account before it expires.
                    </div>

                    <div class='footer'>
                        <p>CompassionCloud &mdash; Empowering churches to care for their communities</p>
                        <p>This is an automated invitation. If you weren't expecting this, you can safely ignore it.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>";

        return $this->sendEmail($email, $subject, $body);
    }
    
    /**
     * Send follow-up reminder email
     */
    public function sendFollowUpReminder($email, $family_name, $due_date) {
        $subject = "Follow-up Reminder: " . $family_name;
        $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4A90E2; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background: #f9f9f9; padding: 20px; border-radius: 0 0 5px 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Follow-up Reminder</h1>
                </div>
                <div class='content'>
                    <p>Hi there,</p>
                    <p>This is a reminder that you have a follow-up scheduled for <strong>{$family_name}</strong> on {$due_date}.</p>
                    <p>Please log in to CompassionCloud to update the family's status.</p>
                    <p>Best regards,<br>The CompassionCloud Team</p>
                </div>
            </div>
        </body>
        </html>";
        
        // Queue the email for scheduled sending
        return $this->queueEmail($email, $subject, $body, $due_date);
    }
    
    /**
     * Send super admin invite email
     */
    public function sendSuperAdminInviteEmail($email, $invite_link, $inviter_name, $expires_days) {
        $subject = "You've Been Invited as a Super Admin - CompassionCloud";
        $days_label = $expires_days == 1 ? "1 day" : "{$expires_days} days";
        $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #2F3A4A 0%, #4A90E2 100%); color: white; padding: 30px 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .header h1 { margin: 0 0 6px; font-size: 22px; }
                .header p { margin: 0; opacity: 0.9; font-size: 14px; }
                .content { background: #f9f9f9; padding: 28px 24px; border-radius: 0 0 8px 8px; border: 1px solid #e8e8e8; border-top: none; }
                .badge { display: inline-block; background: #2F3A4A; color: white; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 16px; }
                .button { display: inline-block; padding: 14px 32px; background: #4A90E2; color: white; text-decoration: none; border-radius: 6px; margin: 20px 0; font-weight: 600; font-size: 15px; }
                .button:hover { background: #3a7bc8; }
                .details { background: white; border: 1px solid #e0e0e0; border-radius: 6px; padding: 16px; margin: 16px 0; }
                .details-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
                .details-row:last-child { border-bottom: none; }
                .details-label { color: #888; }
                .details-value { font-weight: 600; color: #2F3A4A; }
                .warning { background: #fff8e1; border-left: 4px solid #ffc107; padding: 12px 16px; margin: 16px 0; border-radius: 0 4px 4px 0; font-size: 14px; }
                .footer { color: #999; font-size: 12px; margin-top: 24px; padding-top: 16px; border-top: 1px solid #e8e8e8; text-align: center; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Super Admin Invitation</h1>
                    <p>You've been invited to manage CompassionCloud</p>
                </div>
                <div class='content'>
                    <span class='badge'>Super Admin Access</span>
                    <p>Hi there,</p>
                    <p><strong>{$inviter_name}</strong> has invited you to join the <strong>CompassionCloud</strong> platform as a <strong>Super Administrator</strong>.</p>
                    <p>As a Super Admin, you'll have access to:</p>
                    <ul style='color: #555; padding-left: 20px;'>
                        <li>Platform-wide dashboard and analytics</li>
                        <li>Church management and oversight</li>
                        <li>Radius map management</li>
                        <li>Help article administration</li>
                        <li>User role management</li>
                    </ul>

                    <div style='text-align: center;'>
                        <a href='{$invite_link}' class='button'>Accept Invitation &amp; Create Account</a>
                    </div>

                    <p style='font-size: 13px; color: #888;'>Or copy and paste this link into your browser:</p>
                    <p style='word-break: break-all; color: #4A90E2; background: white; padding: 10px 14px; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 13px;'>{$invite_link}</p>

                    <div class='warning'>
                        <strong>&#9200; This link expires in {$days_label}.</strong> Please create your account before it expires.
                    </div>

                    <div class='footer'>
                        <p>CompassionCloud &mdash; Empowering churches to care for their communities</p>
                        <p>This is an automated invitation. If you weren't expecting this, you can safely ignore it.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>";

        return $this->sendEmail($email, $subject, $body);
    }

    /**
     * Notify the inviter that their church invite was accepted
     */
    public function sendInviteAcceptedEmail($inviter_email, $inviter_name, $new_member_name, $new_member_email, $church_name) {
        $subject = "{$new_member_name} accepted your invite to {$church_name}!";
        $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 30px 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .header h1 { margin: 0 0 6px; font-size: 22px; }
                .header p { margin: 0; opacity: 0.9; font-size: 14px; }
                .content { background: #f9f9f9; padding: 28px 24px; border-radius: 0 0 8px 8px; border: 1px solid #e8e8e8; border-top: none; }
                .member-card { background: white; border: 2px solid #28a745; border-radius: 10px; padding: 20px; text-align: center; margin: 20px 0; }
                .member-card .avatar { width: 50px; height: 50px; border-radius: 50%; background: #28a745; color: white; display: inline-flex; align-items: center; justify-content: center; font-size: 22px; margin-bottom: 8px; }
                .member-card .name { font-size: 18px; font-weight: 700; color: #2F3A4A; }
                .member-card .email { color: #888; font-size: 13px; margin-top: 2px; }
                .church-badge { display: inline-block; background: #4A90E2; color: white; padding: 4px 14px; border-radius: 12px; font-size: 12px; font-weight: 700; margin-top: 10px; }
                .footer { color: #999; font-size: 12px; margin-top: 24px; padding-top: 16px; border-top: 1px solid #e8e8e8; text-align: center; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>&#127881; Invite Accepted!</h1>
                    <p>Your team just grew</p>
                </div>
                <div class='content'>
                    <p>Hi {$inviter_name},</p>
                    <p>Great news! The invite you sent has been accepted. A new member has joined your team on CompassionCloud.</p>

                    <div class='member-card'>
                        <div class='avatar'>&#10003;</div>
                        <div class='name'>{$new_member_name}</div>
                        <div class='email'>{$new_member_email}</div>
                        <span class='church-badge'>{$church_name}</span>
                    </div>

                    <p>They now have access to the church dashboard and can start collaborating with the team right away.</p>

                    <div class='footer'>
                        <p>CompassionCloud &mdash; Empowering churches to care for their communities</p>
                    </div>
                </div>
            </div>
        </body>
        </html>";

        return $this->sendEmail($inviter_email, $subject, $body);
    }

    /**
     * Notify the inviter that their church invite expired without being used
     */
    public function sendInviteExpiredEmail($inviter_email, $inviter_name, $recipient_email, $church_name, $team_page_url) {
        $display_recipient = !empty($recipient_email) ? $recipient_email : 'the recipient';
        $subject = "Your invite to {$church_name} has expired";
        $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%); color: white; padding: 30px 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .header h1 { margin: 0 0 6px; font-size: 22px; }
                .header p { margin: 0; opacity: 0.9; font-size: 14px; }
                .content { background: #f9f9f9; padding: 28px 24px; border-radius: 0 0 8px 8px; border: 1px solid #e8e8e8; border-top: none; }
                .expired-card { background: #fff8e1; border: 2px solid #ff9800; border-radius: 10px; padding: 20px; text-align: center; margin: 20px 0; }
                .expired-card .icon { font-size: 32px; margin-bottom: 8px; }
                .expired-card .detail { color: #555; font-size: 14px; }
                .expired-card .email-highlight { font-weight: 700; color: #2F3A4A; }
                .button { display: inline-block; padding: 14px 32px; background: #4A90E2; color: white; text-decoration: none; border-radius: 6px; margin: 20px 0; font-weight: 600; font-size: 15px; }
                .tip { background: #e8f5e9; border-left: 4px solid #28a745; padding: 12px 16px; margin: 16px 0; border-radius: 0 4px 4px 0; font-size: 14px; }
                .footer { color: #999; font-size: 12px; margin-top: 24px; padding-top: 16px; border-top: 1px solid #e8e8e8; text-align: center; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>&#9200; Invite Expired</h1>
                    <p>Time to follow up</p>
                </div>
                <div class='content'>
                    <p>Hi {$inviter_name},</p>
                    <p>The invite you sent to join <strong>{$church_name}</strong> on CompassionCloud has expired without being used.</p>

                    <div class='expired-card'>
                        <div class='icon'>&#128232;</div>
                        <div class='detail'>Invite sent to</div>
                        <div class='email-highlight'>{$display_recipient}</div>
                        <div class='detail' style='margin-top:6px;color:#999;'>Expired &mdash; never accepted</div>
                    </div>

                    <p>You may want to:</p>
                    <ul style='color: #555; padding-left: 20px;'>
                        <li>Reach out to them directly to see if they need help</li>
                        <li>Send a new invite with a longer expiration window</li>
                    </ul>

                    <div style='text-align: center;'>
                        <a href='{$team_page_url}' class='button'>Send a New Invite</a>
                    </div>

                    <div class='tip'>
                        <strong>&#128161; Tip:</strong> When sending invites, you can choose expiration from 1 to 90 days. Consider a longer window if the recipient may be busy.
                    </div>

                    <div class='footer'>
                        <p>CompassionCloud &mdash; Empowering churches to care for their communities</p>
                    </div>
                </div>
            </div>
        </body>
        </html>";

        return $this->sendEmail($inviter_email, $subject, $body);
    }

    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($email, $reset_link, $user_type = 'regular', $expiry_minutes = 15) {
        $user_type_label = ($user_type === 'super_admin') ? 'Super Admin' : 'User';
        $subject = "Password Reset Request - CompassionCloud";
        $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4A90E2; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background: #f9f9f9; padding: 20px; border-radius: 0 0 5px 5px; }
                .button { display: inline-block; padding: 12px 24px; background: #4A90E2; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 15px 0; }
                .footer { color: #666; font-size: 12px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Password Reset Request</h1>
                </div>
                <div class='content'>
                    <p>Hi there,</p>
                    <p>We received a request to reset the password for your CompassionCloud {$user_type_label} account.</p>
                    <p>Click the button below to reset your password:</p>
                    <p style='text-align: center;'>
                        <a href='{$reset_link}' class='button'>Reset Password</a>
                    </p>
                    <p>Or copy and paste this link into your browser:</p>
                    <p style='word-break: break-all; color: #666; background: #fff; padding: 10px; border: 1px solid #ddd; border-radius: 3px;'>{$reset_link}</p>
                    
                    <div class='warning'>
                        <strong>⏰ Important:</strong> This password reset link will expire in <strong>{$expiry_minutes} minutes</strong> for security purposes.
                    </div>
                    
                    <p style='color: #666; font-size: 14px; margin-top: 20px;'>If you did not request a password reset, please ignore this email. Your password will remain unchanged.</p>
                    
                    <div class='footer'>
                        <p>Best regards,<br>The CompassionCloud Team</p>
                        <p>This is an automated message, please do not reply to this email.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>";
        
        // Send immediately
        return $this->sendEmail($email, $subject, $body);
    }
}
