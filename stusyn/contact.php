<?php
// Receives the contact form (sent by assets/js/site.js) and returns JSON.
require __DIR__ . '/app/bootstrap.php';
header('Content-Type: application/json; charset=UTF-8');
header('X-Robots-Tag: noindex');

function reply(int $code, array $data): void { http_response_code($code); echo json_encode($data); exit; }

if (!is_post()) reply(405, ['ok' => false, 'error' => 'Use the contact form to send a message.']);
if (post('website') !== '') reply(200, ['ok' => true]); // honeypot

$topics = ['idea' => 'Bring an idea', 'consult' => 'Consultation request', 'tiktok' => 'TikTok Shop', 'web' => 'Marketing — Website', 'ads' => 'Marketing — Paid ads', 'marketing' => 'Marketing', 'careers' => 'Careers', 'other' => 'Something else'];
$topic = $topics[post('topic')] ?? 'Something else';
$name = mb_substr(post('name'), 0, 120);
$email = post('email');
$company = mb_substr(post('company'), 0, 160);
$message = mb_substr(post('message'), 0, 5000);

if (mb_strlen($name) < 2) reply(422, ['ok' => false, 'error' => 'Enter your name.']);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) reply(422, ['ok' => false, 'error' => 'Enter a valid email address.']);
if (mb_strlen($message) < 5) reply(422, ['ok' => false, 'error' => 'Add a short message.']);
if (!captcha_ok()) reply(422, ['ok' => false, 'error' => CAPTCHA_FAIL]);
if (too_many('contact', 5, 60)) reply(429, ['ok' => false, 'error' => "You've sent several messages recently. Please try again later or email " . config('contact_to') . '.']);

db()->prepare('INSERT INTO messages (topic, name, email, company, message, created_at) VALUES (?,?,?,?,?,?)')
    ->execute([$topic, $name, $email, $company, $message, now()]);
record_attempt('contact');

send_mail(config('contact_to'), "[$topic] " . ($company ?: $name),
    "New message from the stusyn.com contact form.\n\nTopic: $topic\nName: $name\nEmail: $email\n" . ($company ? "Company: $company\n" : '') .
    "\n$message\n\nAll messages: " . abs_url('admin/messages.php') . "\n", $email);

reply(200, ['ok' => true]);
