<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/email_config.php';

/**
 * Send email using PHPMailer
 */
function sendEmail($to_email, $to_name, $subject, $body, $isHTML = true) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port       = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to_email, $to_name);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Content
        $mail->isHTML($isHTML);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        
        if ($isHTML) {
            // Create plain text version by stripping HTML tags
            $mail->AltBody = strip_tags($body);
        }
        
        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully'];
    } catch (Exception $e) {
        error_log("Email send failed: {$mail->ErrorInfo}");
        return ['success' => false, 'message' => "Email could not be sent. Error: {$mail->ErrorInfo}"];
    }
}

/**
 * Send borrow approval email
 */
function sendBorrowApprovalEmail($to_email, $to_name, $book_title, $due_date) {
    $subject = "Borrow Request Approved - " . SITE_NAME;
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4F46E5; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background-color: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; }
            .book-info { background-color: white; padding: 15px; border-left: 4px solid #10b981; margin: 20px 0; }
            .footer { background-color: #f3f4f6; padding: 15px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 5px 5px; }
            .button { display: inline-block; padding: 12px 24px; background-color: #10b981; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .highlight { color: #4F46E5; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>📚 Borrow Request Approved!</h1>
            </div>
            <div class='content'>
                <p>Dear <strong>{$to_name}</strong>,</p>
                
                <p>Great news! Your request to borrow the following book has been <span class='highlight'>APPROVED</span>:</p>
                
                <div class='book-info'>
                    <h3 style='margin: 0 0 10px 0;'>{$book_title}</h3>
                    <p style='margin: 5px 0;'><strong>Due Date:</strong> " . date('F j, Y', strtotime($due_date)) . "</p>
                </div>
                
                <p><strong>📍 Next Step:</strong> Please visit the library front desk to claim your book. Don't forget to bring a valid ID!</p>
                
                <p><strong>⏰ Library Hours:</strong><br>
                Monday - Friday: 8:00 AM - 5:00 PM<br>
                Saturday: 9:00 AM - 3:00 PM</p>
                
                <p style='margin-top: 20px;'><strong>Important Reminders:</strong></p>
                <ul>
                    <li>Please return the book on or before the due date to avoid penalties</li>
                    <li>Late returns are subject to fines</li>
                    <li>Take care of the book - you are responsible for any damage</li>
                </ul>
                
                <p>If you have any questions, feel free to contact us.</p>
                
                <p>Happy reading! 📖</p>
            </div>
            <div class='footer'>
                <p><strong>" . SITE_NAME . "</strong></p>
                <p>This is an automated email. Please do not reply to this message.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($to_email, $to_name, $subject, $body);
}

/**
 * Send borrow rejection email
 */
function sendBorrowRejectionEmail($to_email, $to_name, $book_title) {
    $subject = "Borrow Request Declined - " . SITE_NAME;
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #EF4444; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background-color: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; }
            .book-info { background-color: white; padding: 15px; border-left: 4px solid #EF4444; margin: 20px 0; }
            .footer { background-color: #f3f4f6; padding: 15px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 5px 5px; }
            .button { display: inline-block; padding: 12px 24px; background-color: #4F46E5; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>📚 Borrow Request Update</h1>
            </div>
            <div class='content'>
                <p>Dear <strong>{$to_name}</strong>,</p>
                
                <p>We regret to inform you that your request to borrow the following book has been <strong>declined</strong>:</p>
                
                <div class='book-info'>
                    <h3 style='margin: 0 0 10px 0;'>{$book_title}</h3>
                </div>
                
                <p><strong>Possible Reasons:</strong></p>
                <ul>
                    <li>The book is currently unavailable</li>
                    <li>High demand for this title</li>
                    <li>Outstanding fines or unreturned books on your account</li>
                    <li>Other administrative reasons</li>
                </ul>
                
                <p>If you would like more information about this decision or need assistance, please visit the library front desk or contact us.</p>
                
                <p><strong>📍 Library Contact:</strong><br>
                Visit us during library hours<br>
                Monday - Friday: 8:00 AM - 5:00 PM<br>
                Saturday: 9:00 AM - 3:00 PM</p>
                
                <p>Thank you for your understanding.</p>
            </div>
            <div class='footer'>
                <p><strong>" . SITE_NAME . "</strong></p>
                <p>This is an automated email. Please do not reply to this message.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($to_email, $to_name, $subject, $body);
}

/**
 * Send batch approval email (for cart orders)
 */
function sendBatchApprovalEmail($to_email, $to_name, $approved_count, $rejected_count, $due_date) {
    $subject = "Your Borrow Request Has Been Reviewed - " . SITE_NAME;
    
    $status_message = $rejected_count > 0 
        ? "<p>We've reviewed your borrowing request for multiple books. We're pleased to inform you that <strong>{$approved_count} book(s) have been approved</strong> for borrowing. Unfortunately, <strong>{$rejected_count} book(s) could not be approved</strong> at this time due to limited availability.</p>"
        : "<p>Great news! We've reviewed your borrowing request and <strong>all {$approved_count} book(s) have been approved</strong>!</p>";
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4F46E5; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background-color: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; }
            .stats { background-color: white; padding: 15px; border-left: 4px solid #10b981; margin: 20px 0; }
            .footer { background-color: #f3f4f6; padding: 15px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 5px 5px; }
            .highlight { color: #4F46E5; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>📚 Borrow Request Update</h1>
            </div>
            <div class='content'>
                <p>Dear <strong>{$to_name}</strong>,</p>
                
                {$status_message}
                
                " . ($approved_count > 0 ? "
                <div class='stats'>
                    <p><strong>Due Date for Approved Books:</strong> " . date('F j, Y', strtotime($due_date)) . "</p>
                    <p style='margin: 10px 0 0 0;'><strong>Total Books Approved:</strong> {$approved_count}</p>
                </div>
                
                <p><strong>📍 Next Step:</strong> Please visit the library front desk to claim your book(s). Don't forget to bring a valid ID!</p>
                
                <p><strong>⏰ Library Hours:</strong><br>
                Monday - Friday: 8:00 AM - 5:00 PM<br>
                Saturday: 9:00 AM - 3:00 PM</p>
                
                <p style='margin-top: 20px;'><strong>Important Reminders:</strong></p>
                <ul>
                    <li>Please return all books on or before the due date to avoid penalties</li>
                    <li>Late returns are subject to fines</li>
                    <li>Take care of the books - you are responsible for any damage</li>
                </ul>
                " : "
                <p>Unfortunately, none of the requested books are currently available. You may check back later or contact the library for assistance with alternative titles.</p>
                ") . "
                
                <p>Thank you for using our library services. If you have any questions, please don't hesitate to contact us.</p>
                
                <p>Best regards,<br>
                <strong>" . SITE_NAME . " Team</strong></p>
            </div>
            <div class='footer'>
                <p><strong>" . SITE_NAME . "</strong></p>
                <p>This is an automated email. Please do not reply to this message.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($to_email, $to_name, $subject, $body);
}

/**
 * Send batch rejection email (when entire cart is rejected)
 */
function sendBatchRejectionEmail($to_email, $to_name, $rejected_count) {
    $subject = "Cart Order Declined - " . SITE_NAME;
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #EF4444; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background-color: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; }
            .footer { background-color: #f3f4f6; padding: 15px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 5px 5px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🛒 Cart Order Update</h1>
            </div>
            <div class='content'>
                <p>Dear <strong>{$to_name}</strong>,</p>
                
                <p>We regret to inform you that your entire cart order ({$rejected_count} book(s)) has been <strong>declined</strong>.</p>
                
                <p><strong>Possible Reasons:</strong></p>
                <ul>
                    <li>The requested books are currently unavailable</li>
                    <li>High demand for these titles</li>
                    <li>Outstanding fines or unreturned books on your account</li>
                    <li>Other administrative reasons</li>
                </ul>
                
                <p>For more information about this decision or to discuss alternative options, please visit the library front desk or contact us.</p>
                
                <p><strong>📍 Library Contact:</strong><br>
                Visit us during library hours<br>
                Monday - Friday: 8:00 AM - 5:00 PM<br>
                Saturday: 9:00 AM - 3:00 PM</p>
                
                <p>Thank you for your understanding.</p>
            </div>
            <div class='footer'>
                <p><strong>" . SITE_NAME . "</strong></p>
                <p>This is an automated email. Please do not reply to this message.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($to_email, $to_name, $subject, $body);
}
/**
 * Send email verification OTP
 */
function sendVerificationEmail($to_email, $to_name, $otp_code) {
    $subject = "Verify Your Email Address - " . SITE_NAME;
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4F46E5; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background-color: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; }
            .otp-box { background-color: white; padding: 20px; border: 2px dashed #4F46E5; margin: 20px 0; text-align: center; border-radius: 8px; }
            .otp-code { font-size: 32px; font-weight: bold; color: #4F46E5; letter-spacing: 8px; font-family: monospace; }
            .footer { background-color: #f3f4f6; padding: 15px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 5px 5px; }
            .warning { background-color: #FEF3C7; border-left: 4px solid #F59E0B; padding: 12px; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>📧 Email Verification</h1>
            </div>
            <div class='content'>
                <p>Dear <strong>{$to_name}</strong>,</p>
                
                <p>Thank you for registering with " . SITE_NAME . "! To complete your registration and activate your account, please verify your email address using the OTP code below:</p>
                
                <div class='otp-box'>
                    <p style='margin: 0 0 10px 0; color: #6b7280; font-size: 14px;'>Your Verification Code</p>
                    <div class='otp-code'>{$otp_code}</div>
                    <p style='margin: 10px 0 0 0; color: #6b7280; font-size: 12px;'>This code will expire in 15 minutes</p>
                </div>
                
                <p><strong>How to verify:</strong></p>
                <ol>
                    <li>Go to the verification page</li>
                    <li>Enter the 6-digit code above</li>
                    <li>Click 'Verify Email'</li>
                </ol>
                
                <div class='warning'>
                    <strong>⚠️ Security Note:</strong> If you didn't create an account with us, please ignore this email. Your email address may have been entered by mistake.
                </div>
                
                <p>Once verified, you'll have full access to our library services including:</p>
                <ul>
                    <li>Browse and search our book collection</li>
                    <li>Borrow books online</li>
                    <li>Track your borrowing history</li>
                    <li>Receive notifications about due dates</li>
                </ul>
                
                <p>If you have any questions or need assistance, feel free to contact us.</p>
                
                <p>Best regards,<br>
                <strong>" . SITE_NAME . " Team</strong></p>
            </div>
            <div class='footer'>
                <p><strong>" . SITE_NAME . "</strong></p>
                <p>This is an automated email. Please do not reply to this message.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($to_email, $to_name, $subject, $body);
}

/**
 * Send welcome email after verification
 */
function sendWelcomeEmail($to_email, $to_name) {
    $subject = "Welcome to " . SITE_NAME . "!";
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #10B981; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background-color: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; }
            .footer { background-color: #f3f4f6; padding: 15px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 5px 5px; }
            .feature-box { background-color: white; padding: 15px; margin: 10px 0; border-left: 4px solid #10B981; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎉 Welcome to Our Library!</h1>
            </div>
            <div class='content'>
                <p>Dear <strong>{$to_name}</strong>,</p>
                
                <p>Congratulations! Your email has been successfully verified and your account is now active.</p>
                
                <p><strong>You can now enjoy all our library services:</strong></p>
                
                <div class='feature-box'>
                    <strong>📚 Browse Books</strong><br>
                    Explore our extensive collection of books across various genres and categories.
                </div>
                
                <div class='feature-box'>
                    <strong>📖 Borrow Books</strong><br>
                    Request to borrow books online and pick them up at the library.
                </div>
                
                <div class='feature-box'>
                    <strong>🔔 Get Notifications</strong><br>
                    Receive timely reminders about due dates and borrow request updates.
                </div>
                
                <div class='feature-box'>
                    <strong>📊 Track History</strong><br>
                    View your borrowing history and manage your current loans.
                </div>
                
                <p><strong>📍 Visit Us:</strong><br>
                Library Hours:<br>
                Monday - Friday: 8:00 AM - 5:00 PM<br>
                Saturday: 9:00 AM - 3:00 PM</p>
                
                <p>Thank you for joining our library community. We're excited to have you with us!</p>
                
                <p>Happy reading! 📖</p>
                
                <p>Best regards,<br>
                <strong>" . SITE_NAME . " Team</strong></p>
            </div>
            <div class='footer'>
                <p><strong>" . SITE_NAME . "</strong></p>
                <p>This is an automated email. Please do not reply to this message.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($to_email, $to_name, $subject, $body);
}
?>