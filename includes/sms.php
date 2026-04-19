<?php
/**
 * SMS Sender — Semaphore API Wrapper (Philippines)
 *
 * Usage:
 *   require_once __DIR__ . '/sms.php';
 *   sendSms('09171234567', 'Hi! Your job offer from Yazzies Catering…');
 *
 * Setup:
 *   1. Register at https://semaphore.co.ph and get your API key
 *   2. Update SMS_API_KEY and SMS_SENDER in config/config.php
 *   3. Set SMS_ENABLED = true in config/config.php
 *
 * Pricing: ~₱0.50/SMS in the Philippines
 * Free trial includes 10 test SMS credits
 */

/**
 * Send an SMS via the Semaphore API.
 *
 * @param string $toPhone    Recipient phone number (PH format: 09XXXXXXXXX or +639XXXXXXXXX)
 * @param string $message    SMS message text (max 160 chars per SMS)
 * @return bool              True on success, false on failure
 */
function sendSms(string $toPhone, string $message): bool
{
    if (!SMS_ENABLED) {
        error_log("[SMS] SMS_ENABLED is false. Skipped SMS to: $toPhone");
        return false;
    }

    if (empty(SMS_API_KEY) || SMS_API_KEY === 'your_semaphore_api_key') {
        error_log("[SMS] SMS_API_KEY not configured. Update config/config.php.");
        return false;
    }

    // Normalize phone number to PH format
    $phone = preg_replace('/\D/', '', $toPhone);
    if (substr($phone, 0, 1) === '0') {
        $phone = '63' . substr($phone, 1); // 09XX → 639XX
    } elseif (substr($phone, 0, 2) !== '63') {
        $phone = '63' . $phone;
    }

    $payload = http_build_query([
        'apikey'  => SMS_API_KEY,
        'number'  => $phone,
        'message' => substr($message, 0, 480), // Semaphore allows up to 3 SMS concatenated
        'sendername' => SMS_SENDER,
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://api.semaphore.co/api/v4/messages',
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("[SMS] CURL error sending to $phone: $error");
        return false;
    }

    $result = json_decode($response, true);

    if ($httpCode === 200 || $httpCode === 201) {
        error_log("[SMS] Sent to $phone successfully.");
        return true;
    }

    error_log("[SMS] Failed to send to $phone. HTTP $httpCode. Response: $response");
    return false;
}

/**
 * Send a job offer SMS notification to a staff member.
 *
 * @param string $staffPhone  Staff phone number
 * @param string $staffName   Staff name (for personalization)
 * @param array  $jobDetails  Associative array: role_required, event_date, event_location, client_name
 * @return bool
 */
function sendJobOfferSms(string $staffPhone, string $staffName, array $jobDetails): bool
{
    $eventDate = date('M j, Y', strtotime($jobDetails['event_date']));
    $message   = "Hi {$staffName}! Yazzies Catering has a job offer for you."
               . " Role: {$jobDetails['role_required']}."
               . " Event: $eventDate at {$jobDetails['event_location']}."
               . " Log in to " . BASE_URL . " to Accept or Decline.";

    return sendSms($staffPhone, $message);
}

/**
 * Send a booking reminder SMS to a client.
 *
 * @param string $clientPhone Phone number
 * @param string $clientName  Client name
 * @param array  $booking     Booking details
 * @return bool
 */
function sendEventReminderSms(string $clientPhone, string $clientName, array $booking): bool
{
    $eventDate = date('M j', strtotime($booking['event_date']));
    $balance   = $booking['total_cost'] - $booking['amount_paid'];
    $message   = "Hi {$clientName}! Reminder: Your Yazzies Catering event is on $eventDate."
               . ($balance > 0 ? " Outstanding balance: P" . number_format($balance, 2) . "." : " Your payment is fully settled.")
               . " Thank you!";

    return sendSms($clientPhone, $message);
}
