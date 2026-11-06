<?php
require_once __DIR__ . '/../src/PureOTP.php';

echo "=== 2FA STRICT TIMING VERIFICATION TEST ===\n\n";

$secret = PureOTP::generateSecret();
echo "Generated Secret: $secret\n";

// 1. Current Code (Within active circle timer)
$currentCode = PureOTP::getCode($secret, time());
echo "Current Code (active circle timer): $currentCode\n";

$isValidCurrent = PureOTP::verifyCode($secret, $currentCode, 0);
assert($isValidCurrent === true, "Current code should be valid!");
echo "  ✓ Active circle timer code accepted (discrepancy = 0)\n";

// 2. Expired Code from previous cycle (35 seconds ago)
$expiredCode = PureOTP::getCode($secret, time() - 35);
echo "Expired Code (previous circle timer, 35s ago): $expiredCode\n";

$isValidExpired = PureOTP::verifyCode($secret, $expiredCode, 0);
assert($isValidExpired === false, "Expired code should be rejected strictly!");
echo "  ✓ Expired circle timer code rejected (discrepancy = 0)\n";

// 3. Future Code (next cycle, 35 seconds ahead)
$futureCode = PureOTP::getCode($secret, time() + 35);
echo "Future Code (next circle timer, 35s in future): $futureCode\n";

$isValidFuture = PureOTP::verifyCode($secret, $futureCode, 0);
assert($isValidFuture === false, "Future code should be rejected strictly!");
echo "  ✓ Future circle timer code rejected (discrepancy = 0)\n";

echo "\nALL 2FA STRICT TIMING TESTS PASSED! ✓\n";

