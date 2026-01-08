<?php
/**
 * REAL EMAIL HELPER (mail_helper.php)
 *
 * This file uses an external cURL-based service to send real emails.
 * It replaces the previous simulation that wrote to 'sent_emails.log'.
 */

/**
 * --------------------------------------------------------------------------
 * Core Email Sending Function (Internal Use)
 * --------------------------------------------------------------------------
 *
 * This function handles the actual cURL request to the external email service.
 *
 * @param string $to_email      The recipient's email address.
 * @param string $subject       The subject line of the email.
 * @param string $message_body  The plain text content of the email.
 * @param string $sender_name   The "From" name to show (e.g., "DDTMS Support").
 * @return bool                 True on success, false on failure.
 */
function _send_email_via_service($to_email, $subject, $message_body, $sender_name = 'DDTMS Support') {
    
    // The external email service URL you provided
    // $emailUrl = 'https://ict-amsos.e-dats.info/sendemail/send.php';
    $emailUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/sendemail/send.php';


    // Build the query parameters for the GET request
    $queryParams = http_build_query([
        'send' => 1,
        'email' => $to_email,
        'Subject' => $subject,
        'message' => $message_body,
        'yourname' => $sender_name
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $emailUrl . '?' . $queryParams);
    
    // WARNING: Disabling SSL verification is a security risk.
    // This is kept from your example, but only use this if you
    // trust the endpoint or are in a development environment.
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        // Log the cURL error
        error_log("cURL Error in mail_helper.php: " . $error);
        return false;
    }

    // You might want to check $response for a specific success message from your service
    // For now, we'll assume any response that isn't `false` is a success.
    return true;
}


/**
 * --------------------------------------------------------------------------
 * Send Account Verification Email
 * --------------------------------------------------------------------------
 *
 * Called by register.php to send the account activation link.
 *
 * @param string $to_email          The new user's email.
 * @param string $first_name        The new user's first name.
 * @param string $verification_link The unique activation URL.
 * @return bool                     True on success, false on failure.
 */
function send_verification_email($to_email, $first_name, $verification_link) {
    
    $subject = "DDTMS Account Verification";
    
    $body = "Hello " . htmlspecialchars($first_name) . ",\n\n";
    $body .= "Thank you for registering for the DENR CARAGA Digital Document Tracking & Management System (DDTMS).\n\n";
    $body .= "To activate your account, please click the link below:\n";
    $body .= $verification_link . "\n\n";
    $body .= "If you did not register for this account, please ignore this email.\n";
    $body .= "This link will expire in 1 hour.\n\n";
    $body .= "Thank you,\nDDTMS Administrator";

    // Use the core sender function
    return _send_email_via_service($to_email, $subject, $body, 'DDTMS Administrator');
}


/**
 * --------------------------------------------------------------------------
 * Send Password Reset Email (FIXED SIGNATURE)
 * --------------------------------------------------------------------------
 *
 * Called by forgot_password.php to send the password reset link.
 *
 * @param string $email         The user's email.
 * @param string $firstName     The user's first name.
 * @param string $resetLink     The full, unique reset URL. (This is the expected parameter)
 * @return bool                 True on success, false on failure.
 */
function send_password_reset_email($email, $firstName, $resetLink) {
    
    $subject = 'DDTMS Password Reset Request';
    
    $emailMessage = "Hello " . htmlspecialchars($firstName) . ",\n\n";
    $emailMessage .= "We received a request to reset your password for your DDTMS account. Please click the link below to set a new password:\n";
    $emailMessage .= $resetLink . "\n\n";
    $emailMessage .= "If you did not request this, please ignore this email. This link is valid for 1 hour.\n\n";
    $emailMessage .= "Thank you,\nThe DDTMS Team";

    // Use the core sender function
    return _send_email_via_service($email, $subject, $emailMessage, 'DDTMS Support');
}

?>