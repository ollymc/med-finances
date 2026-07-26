<?php
declare(strict_types=1);
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function money(float $v): string { return number_format($v, 0, ',', ' ') . ' €'; }
function num(array $src, string $key, float $default=0): float { return isset($src[$key]) && is_numeric($src[$key]) ? (float)$src[$key] : $default; }
function pct(float $v): string { return number_format($v*100,1,',',' ').' %'; }
