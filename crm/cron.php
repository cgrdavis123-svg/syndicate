<?php
// Optional cPanel cron job, so recurring invoices go out on time and website leads come in even on
// days you don't open the CRM, and new email is copied into the inbox. In cPanel → Cron Jobs, add (every 15 minutes keeps the inbox current):
//   php /home/USERNAME/crm.stusyn.com/cron.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/recurring.php';
require __DIR__ . '/app/mail.php';
require __DIR__ . '/app/analytics.php';
require __DIR__ . '/app/shop.php';
require __DIR__ . '/app/quotes.php';
require __DIR__ . '/app/booking.php';
require __DIR__ . '/app/care.php';
$sync = sync_website(true);
$rec = run_recurring();
$mail = mail_sync(true, 120);
an_cleanup();
$reports = an_monthly_reports();
try { $store = trim(shop_cleanup() . ' ' . quotes_cleanup() . ' ' . bookings_cleanup()); } catch (Throwable $e) { $store = 'Store sync failed: ' . $e->getMessage(); }
try { $care = care_cron(); } catch (Throwable $e) { $care = 'Care checks failed: ' . $e->getMessage(); }
require_once __DIR__ . '/app/ads.php';
try { $ads = ads_cron(); } catch (Throwable $e) { $ads = 'Ads jobs failed: ' . $e->getMessage(); }
require_once __DIR__ . '/app/digest.php';
try { $dig = pipeline_digest_send(); if ($dig) $ads = trim($ads . ' · ' . $dig, ' ·'); } catch (Throwable $e) { $ads = trim($ads . ' · Weekly summary failed: ' . $e->getMessage(), ' ·'); }
echo date('Y-m-d H:i') . ' · ' . $sync['message'] . ' · ' . (recurring_message($rec) ?: 'No recurring invoices due.') . ' · ' . $mail['new'] . ' new email(s)' . ($mail['errors'] ? ' · Mail errors: ' . implode('; ', $mail['errors']) : '') . ($reports ? " · $reports website report(s) emailed" : '') . ($store ? " · $store" : '') . ($care ? " · $care" : '') . ($ads ? " · $ads" : '') . "\n";
