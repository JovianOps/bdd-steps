#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * One-shot watch: checks if sshah03 is a member of JovianOps.
 * Run from cron. Exits 0 + prints SUCCESS when they join.
 *
 * Usage: php watch_sshah03.php
 */

$org = 'JovianOps';
$user = 'sshah03';
$url = "https://api.github.com/orgs/$org/members/$user";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  'Accept: application/vnd.github+json',
  'User-Agent: hermes-watch',
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

switch ($code) {
  case 204:
    fwrite(STDOUT, "SUCCESS: $user is a PUBLIC member of $org\n");
    exit(0);

  case 302:
  case 404:
    fwrite(STDERR, "$user is not yet a (visible) member of $org (HTTP $code)\n");
    exit(1);

  default:
    fwrite(STDERR, "UNEXPECTED HTTP $code — verify org name\n");
    exit(2);
}
