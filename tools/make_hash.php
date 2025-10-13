<?php
$pwds = [
  'DriverOne!2025',
  'DriverTwo!2025',
  'DriverThree!2025',
];
foreach ($pwds as $p) {
  echo htmlspecialchars($p) . ' => ' . password_hash($p, PASSWORD_BCRYPT) . "<br>\n";
}
