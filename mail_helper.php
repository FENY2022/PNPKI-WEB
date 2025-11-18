<?php

/**
 * --- SIMULATED MAIL HELPER ---
 * In a real application, this file would use a library like PHPMailer
 * to send actual emails.
 * * For this project, it just saves the email as an HTML file in a 'sent_emails'
 * directory so you can open it in your browser and click the link.
 */

// Function for sending VERIFICATION emails
function send_verification_email($to_email, $first_name, $verification_link) {
    
    $subject = "Verify Your Account - DDTMS DENR CARAGA";
    $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { width: 90%; max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
                .header { font-size: 24px; color: #1e3a8a; }
                .button { background-color: #2563eb; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>DDTMS | DENR CARAGA</div>
                <hr style='border:0; border-top:1px solid #eee;'>
                <p>Hello " . htmlspecialchars($first_name) . ",</p>
                <p>Thank you for registering for the Digital Document Tracking & Management System (DDTMS) for DENR CARAGA.</p>
                <p>Please click the button below to verify your email address and activate your account:</p>
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='" . htmlspecialchars($verification_link) . "' class='button'>Verify Account</a>
                </p>
                <p>If the button doesn't work, you can copy and paste this link into your browser:</p>
                <p>" . htmlspecialchars($verification_link) . "</p>
                <p><br>Thank you,<br>The DDTMS Team</p>
            </div>
        </body>
        </html>
    ";

    return save_email_to_file($to_email, $subject, $body, "verify");
}

/**
 * --- NEW FUNCTION ---
 * Function for sending PASSWORD RESET emails
 */
function send_password_reset_email($to_email, $first_name, $reset_link) {
    
    $subject = "Password Reset Request - DDTMS DENR CARAGA";
    $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { width: 90%; max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
                .header { font-size: 24px; color: #1e3a8a; }
                .button { background-color: #dc2626; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>DDTMS | DENR CARAGA</div>
                <hr style='border:0; border-top:1px solid #eee;'>
                <p>Hello " . htmlspecialchars($first_name) . ",</p>
                <p>A request was made to reset the password for your account. If you did not make this request, you can safely ignore this email.</p>
                <p>To reset your password, please click the button below. This link is valid for <strong>1 hour</strong>.</p>
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='" . htmlspecialchars($reset_link) . "' class='button'>Reset Your Password</a>
                </p>
                <p>If the button doesn't work, you can copy and paste this link into your browser:</p>
                <p>" . htmlspecialchars($reset_link) . "</p>
                <p><br>Thank you,<br>The DDTMS Team</p>
            </div>
        </body>
        </html>
    ";

    return save_email_to_file($to_email, $subject, $body, "pass_reset");
}


// --- PRIVATE SIMULATION FUNCTION ---
// (This is the part that saves the file instead of sending)
function save_email_to_file($to, $subject, $body, $type = 'email') {
    $dir = 'sent_emails'; // Make sure this directory exists and is writable
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            error_log("Failed to create 'sent_emails' directory.");
            return false;
        }
    }
    
    // Create a unique filename
    $filename = $dir . '/' . time() . '_' . str_replace(['@', '.'], ['_', '_'], $to) . '_' . $type . '.html';
    
    // Put all content into a wrapper to make it easy to see
    $file_content = "
        <html>
            <head><title>" . htmlspecialchars($subject) . "</title></head>
            <body style='font-family: Arial, sans-serif; background-color: #f0f0f0; padding: 20px;'>
                <div style='max-width: 800px; margin: auto; background-color: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1);'>
                    <h3 style='border-bottom: 1px solid #ccc; padding-bottom: 10px;'>Email Simulation</h3>
                    <p><strong>To:</strong> " . htmlspecialchars($to) . "</p>
                    <p><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>
                    <hr>
                    " . $body . "
                </div>
            </body>
        </html>
    ";

    if (file_put_contents($filename, $file_content)) {
        return true;
    } else {
        error_log("Failed to write email file to: " . $filename);
        return false;
    }
}
?>