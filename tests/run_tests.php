<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/DBMigratorTest.php';
require_once __DIR__ . '/Unit/SettingsAndPlanMappingTest.php';
require_once __DIR__ . '/Unit/QuotaAndExpiryTest.php';
require_once __DIR__ . '/Unit/MatchingEngineTest.php';
require_once __DIR__ . '/Unit/ModeAndResetTest.php';
require_once __DIR__ . '/Unit/LoggingTest.php';
require_once __DIR__ . '/Unit/ManualMatchmakerTest.php';
require_once __DIR__ . '/Unit/NotificationAndApprovalTest.php';
require_once __DIR__ . '/Unit/FormWizardAndShortcodesTest.php';
require_once __DIR__ . '/Unit/FreeRegHandlerTest.php';
require_once __DIR__ . '/Unit/HeartbeatAndNotificationsTest.php';
require_once __DIR__ . '/Unit/AuthAndRedirectsTest.php';
require_once __DIR__ . '/Unit/GateDebuggerAndScoringTest.php';
require_once __DIR__ . '/Unit/AdminWorkflowTest.php';
require_once __DIR__ . '/Unit/PortalAndEventsTest.php';
require_once __DIR__ . '/Unit/EmailVerificationTest.php';
require_once __DIR__ . '/Unit/LocationCascadeTest.php';
require_once __DIR__ . '/Integration/EndToEndFlowTest.php';
require_once __DIR__ . '/Integration/FullMemberLifecycleFlowTest.php';

$test_classes = [
    \DBMigratorTest::class,
    \Matchmaker\Tests\Unit\SettingsAndPlanMappingTest::class,
    \Matchmaker\Tests\Unit\QuotaAndExpiryTest::class,
    \Matchmaker\Tests\Unit\MatchingEngineTest::class,
    \Matchmaker\Tests\Unit\ModeAndResetTest::class,
    \Matchmaker\Tests\Unit\LoggingTest::class,
    \Matchmaker\Tests\Unit\ManualMatchmakerTest::class,
    \Matchmaker\Tests\Unit\NotificationAndApprovalTest::class,
    \Matchmaker\Tests\Unit\FormWizardAndShortcodesTest::class,
    \Matchmaker\Tests\Unit\FreeRegHandlerTest::class,
    \Matchmaker\Tests\Unit\HeartbeatAndNotificationsTest::class,
    \Matchmaker\Tests\Unit\AuthAndRedirectsTest::class,
    \Matchmaker\Tests\Unit\GateDebuggerAndScoringTest::class,
    \Matchmaker\Tests\Unit\AdminWorkflowTest::class,
    \Matchmaker\Tests\Unit\PortalAndEventsTest::class,
    \Matchmaker\Tests\Unit\EmailVerificationTest::class,
    \Matchmaker\Tests\Unit\LocationCascadeTest::class,
    \Matchmaker\Tests\Integration\EndToEndFlowTest::class,
    \Matchmaker\Tests\Integration\FullMemberLifecycleFlowTest::class,
];

$total_tests  = 0;
$total_passed = 0;
$total_failed = 0;
$failures     = [];

echo "========================================================\n";
echo " Arab Zawaj Matchmaker — Automated Test Suite\n";
echo "========================================================\n\n";

foreach ($test_classes as $class_name) {
    echo "Suite: {$class_name}\n";
    $reflection = new ReflectionClass($class_name);
    $methods    = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

    foreach ($methods as $method) {
        if (!str_starts_with($method->getName(), 'test_') && !str_starts_with($method->getName(), 'test')) {
            continue;
        }

        $total_tests++;
        $instance = new $class_name();

        // Run setUp if present
        if ($reflection->hasMethod('setUp')) {
            $setupMethod = $reflection->getMethod('setUp');
            $setupMethod->setAccessible(true);
            $setupMethod->invoke($instance);
        }

        try {
            $instance->{$method->getName()}();
            $total_passed++;
            echo "  ✔ {$method->getName()}\n";
        } catch (\Throwable $e) {
            $total_failed++;
            $failures[] = [
                'class'   => $class_name,
                'method'  => $method->getName(),
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ];
            echo "  ✖ {$method->getName()}: {$e->getMessage()}\n";
        }
    }
    echo "\n";
}

echo "========================================================\n";
echo " Test Results Summary\n";
echo "========================================================\n";
echo "Total Tests Run : {$total_tests}\n";
echo "Passed          : {$total_passed}\n";
echo "Failed          : {$total_failed}\n";

if ($total_failed > 0) {
    echo "\nFailures:\n";
    foreach ($failures as $f) {
        echo "- {$f['class']}::{$f['method']}: {$f['message']}\n";
    }
    exit(1);
} else {
    echo "\n🎉 ALL TESTS PASSED SUCCESSFULLY (0 failures, 0 errors)\n";
    exit(0);
}
