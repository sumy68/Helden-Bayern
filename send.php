<?php
/**
 * Kontaktformular → IONOS SMTP mit Fallback:
 * - From = Nutzer-E-Mail (falls möglich)
 * - Fallback = eigene Domain-Adresse
 * - SMTP-Passwort sicher aus .env geladen
 *
 * Voraussetzung:
 *   composer require phpmailer/phpmailer vlucas/phpdotenv
 */

declare(strict_types=1);
ini_set('display_errors', '0');
error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

// --- ENV laden ---
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();
$SMTP_PASS = $_ENV['SMTP_PASS'] ?? '';

// --- Konstanten ---
const MAIL_HOST       = 'smtp.ionos.de';
const MAIL_USER       = 'info@ci-dienstleistungen.com';   // dein IONOS-Postfach
const MAIL_FROM_SAFE  = 'info@ci-dienstleistungen.com';   // sicherer Absender (Fallback)
const MAIL_TO         = 'info@ci-dienstleistungen.com';   // Empfängerpostfach
const MAIL_TO_NAME    = 'CI Dienstleistungen';

function redirect(bool $ok): void {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
  $uri    = rtrim(dirname($_SERVER['REQUEST_URI'] ?? '/'), '/\\');
  $to     = $scheme . '://' . $host . ($uri ?: '/') . '/?ok=' . ($ok ? '1' : '0') . '#kontakt';
  header('Location: ' . $to);
  exit;
}

// Nur POST akzeptieren
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  redirect(false);
}

// Honeypot
if (!empty($_POST['website'] ?? '')) {
  redirect(true);
}

// Felder
$name    = trim((string)($_POST['name']    ?? ''));
$address = trim((string)($_POST['address'] ?? ''));
$phone   = trim((string)($_POST['phone']   ?? ''));
$email   = trim((string)($_POST['email']   ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

// Validierung
if ($name === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  redirect(false);
}

// Mailtext
$body = "Neue Kontaktanfrage\n"
      . "====================\n\n"
      . "Name: {$name}\n"
      . "Adresse: {$address}\n"
      . "Telefon: {$phone}\n"
      . "E-Mail: {$email}\n\n"
      . "Nachricht:\n{$message}\n";

function configureIonos(PHPMailer $m, string $SMTP_PASS): void {
  $m->isSMTP();
  $m->Host       = MAIL_HOST;
  $m->SMTPAuth   = true;
  $m->Username   = MAIL_USER;
  $m->Password   = $SMTP_PASS;
  $m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // oder ENCRYPTION_SMTPS + Port 465
  $m->Port       = 587;

  $m->CharSet    = 'UTF-8';
  $m->Sender     = MAIL_FROM_SAFE;
  $m->Timeout    = 15;
}

try {
  // 1) Versuch: From = Nutzer
  $mail = new PHPMailer(true);
  configureIonos($mail, $SMTP_PASS);

  $fromName = $name !== '' ? $name : $email;

  $mail->setFrom($email, $fromName);
  $mail->addAddress(MAIL_TO, MAIL_TO_NAME);
  $mail->addReplyTo($email, $fromName);
  $mail->Subject = 'Neue Kontaktanfrage von der Website';
  $mail->isHTML(false);
  $mail->Body = $body;
  $mail->addCustomHeader('X-Original-From', $email);

  $mail->send();
  redirect(true);

} catch (Exception $e1) {
  // 2) Fallback: From = eigene Domain
  try {
    $mail2 = new PHPMailer(true);
    configureIonos($mail2, $SMTP_PASS);

    $fromName = ($name !== '' ? $name . ' ' : '') . 'via CI Kontaktformular';
    $mail2->setFrom(MAIL_FROM_SAFE, $fromName);
    $mail2->addAddress(MAIL_TO, MAIL_TO_NAME);
    $mail2->addReplyTo($email, $name !== '' ? $name : $email);
    $mail2->Subject = 'Neue Kontaktanfrage (Absender umgeschrieben)';
    $mail2->isHTML(false);
    $mail2->Body = $body;
    $mail2->addCustomHeader('X-Original-From', $email);

    $mail2->send();
    redirect(true);

  } catch (Exception $e2) {
    // Optional: Log aktivieren für Debug
    // @file_put_contents(__DIR__.'/mail_error.log', date('c').
    //   "\nERSTVERSUCH: ".$e1->getMessage()."\nFALLBACK: ".$e2->getMessage()."\n", FILE_APPEND);
    redirect(false);
  }
}
