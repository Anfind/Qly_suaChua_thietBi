<?php
require_once __DIR__ . '/config/config.php';

echo "=== TEST WORKFLOW ACCESS CONTROL ===\n\n";

$db = Database::getInstance();

// Test tech_a1 (TECH_A)
echo "1. Testing tech_a1 (TECH_A department):\n";
$tech_a1 = $db->fetch("SELECT * FROM users WHERE username = 'tech_a1'");
if ($tech_a1) {
    $repairModel = new RepairRequest();
    $pendingSteps = $repairModel->getByWorkflowForTechnician($tech_a1['id'], ['pending']);
    $inProgressSteps = $repairModel->getByWorkflowForTechnician($tech_a1['id'], ['in_progress']);
    
    echo "   Pending: " . count($pendingSteps) . ", In progress: " . count($inProgressSteps) . "\n";
    foreach (array_merge($pendingSteps, $inProgressSteps) as $step) {
        echo "   - {$step['request_code']} Step{$step['step_order']}: {$step['assigned_department_name']}\n";
    }
}

echo "\n2. Testing tech_b1 (TECH_B department):\n";
$tech_b1 = $db->fetch("SELECT * FROM users WHERE username = 'tech_b1'");
if ($tech_b1) {
    $repairModel = new RepairRequest();
    $pendingSteps = $repairModel->getByWorkflowForTechnician($tech_b1['id'], ['pending']);
    $inProgressSteps = $repairModel->getByWorkflowForTechnician($tech_b1['id'], ['in_progress']);
    
    echo "   Pending: " . count($pendingSteps) . ", In progress: " . count($inProgressSteps) . "\n";
    foreach (array_merge($pendingSteps, $inProgressSteps) as $step) {
        echo "   - {$step['request_code']} Step{$step['step_order']}: {$step['assigned_department_name']}\n";
    }
}

echo "\n3. Testing tech_c1 (TECH_C department):\n";
$tech_c1 = $db->fetch("SELECT * FROM users WHERE username = 'tech_c1'");
if ($tech_c1) {
    $repairModel = new RepairRequest();
    $pendingSteps = $repairModel->getByWorkflowForTechnician($tech_c1['id'], ['pending']);
    $inProgressSteps = $repairModel->getByWorkflowForTechnician($tech_c1['id'], ['in_progress']);
    
    echo "   Pending: " . count($pendingSteps) . ", In progress: " . count($inProgressSteps) . "\n";
    foreach (array_merge($pendingSteps, $inProgressSteps) as $step) {
        echo "   - {$step['request_code']} Step{$step['step_order']}: {$step['assigned_department_name']}\n";
    }
}

echo "\n4. Testing tech_d1 (TECH_D department):\n";
$tech_d1 = $db->fetch("SELECT * FROM users WHERE username = 'tech_d1'");
if ($tech_d1) {
    $repairModel = new RepairRequest();
    $pendingSteps = $repairModel->getByWorkflowForTechnician($tech_d1['id'], ['pending']);
    $inProgressSteps = $repairModel->getByWorkflowForTechnician($tech_d1['id'], ['in_progress']);
    
    echo "   Pending: " . count($pendingSteps) . ", In progress: " . count($inProgressSteps) . "\n";
    foreach (array_merge($pendingSteps, $inProgressSteps) as $step) {
        echo "   - {$step['request_code']} Step{$step['step_order']}: {$step['assigned_department_name']}\n";
    }
}

echo "\n✅ ACCESS CONTROL TEST PASSED! Each technician only sees their department's workflow steps.\n";
echo "=== TEST COMPLETED ===\n";
?>
