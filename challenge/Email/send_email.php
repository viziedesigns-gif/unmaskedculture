<?php
/**
 * Email Queue Processor
 * This script should be run via cron job every 5-15 minutes
 * Example cron schedule: every 10 minutes, invoking this file with PHP CLI.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/email_service.php';
ensureEmailQueueSchema($link);

// Set execution time limit for long-running script
set_time_limit(300); // 5 minutes

// Get pending emails that are ready to send
$sql = "SELECT * FROM email_queue 
        WHERE status = 'pending' 
        AND (scheduled_for IS NULL OR scheduled_for <= NOW())
        ORDER BY created_at ASC
        LIMIT 50"; // Process 50 at a time

$result = mysqli_query($link, $sql);
$processed = 0;
$failed = 0;

if ($result && mysqli_num_rows($result) > 0) {
    $emailService = new EmailService($link);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $email_id = $row['id'];
        $to = $row['to_email'];
        $subject = $row['subject'];
        $body = $row['body'];

        // Task reminders become invalid as soon as a task is completed,
        // cancelled, or replaced. Never send stale care reminders.
        if (($row['related_type'] ?? '') === 'follow_up_task' && !empty($row['related_id'])) {
            $task_status_stmt = mysqli_prepare($link, "SELECT status FROM follow_up_tasks WHERE id = ? LIMIT 1");
            $task_status = null;
            if ($task_status_stmt) {
                mysqli_stmt_bind_param($task_status_stmt, 'i', $row['related_id']);
                mysqli_stmt_execute($task_status_stmt);
                mysqli_stmt_bind_result($task_status_stmt, $task_status);
                mysqli_stmt_fetch($task_status_stmt);
                mysqli_stmt_close($task_status_stmt);
            }
            if ($task_status !== 'pending') {
                $cancel_stmt = mysqli_prepare($link, "UPDATE email_queue SET status = 'cancelled' WHERE id = ?");
                if ($cancel_stmt) {
                    mysqli_stmt_bind_param($cancel_stmt, 'i', $email_id);
                    mysqli_stmt_execute($cancel_stmt);
                    mysqli_stmt_close($cancel_stmt);
                }
                continue;
            }
        }
        
        // Try to send email
        $result_send = $emailService->sendEmail($to, $subject, $body);
        
        if ($result_send['success']) {
            // Update status to sent
            $update_sql = "UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = ?";
            if ($update_stmt = mysqli_prepare($link, $update_sql)) {
                mysqli_stmt_bind_param($update_stmt, "i", $email_id);
                mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);
            }
            $processed++;
        } else {
            // Increment attempts
            $attempts = $row['attempts'] + 1;
            $status = ($attempts >= 3) ? 'failed' : 'pending';
            $error_msg = substr($result_send['message'], 0, 255); // Limit error message length
            
            $update_sql = "UPDATE email_queue SET attempts = ?, status = ?, error_message = ? WHERE id = ?";
            if ($update_stmt = mysqli_prepare($link, $update_sql)) {
                mysqli_stmt_bind_param($update_stmt, "issi", $attempts, $status, $error_msg, $email_id);
                mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);
            }
            $failed++;
        }
    }
}

// Log results (optional - can be removed in production)
if (php_sapi_name() !== 'cli') {
    // If run via web, show results
    echo "Email Queue Processor\n";
    echo "Processed: {$processed}\n";
    echo "Failed: {$failed}\n";
} else {
    // If run via CLI/cron, log to file
    $log_message = date('Y-m-d H:i:s') . " - Processed: {$processed}, Failed: {$failed}\n";
    error_log($log_message, 3, __DIR__ . '/../email_queue.log');
}

mysqli_close($link);
?>

