<?php
/** Run: C:/xampp/php/php.exe tests/submission_review_permissions.php (no database). */
require_once __DIR__ . '/../includes/admin_perm.php';
require_once __DIR__ . '/../includes/submissions.php';
require_once __DIR__ . '/../includes/plan_eval.php';

$head = ['id' => 10, 'role' => 'supervisor', 'department_id' => 2, 'position' => ''];
$own = ['teacher_id' => 10, 'department_id' => 2, '_type' => 'lesson_plan'];
$cases = [];
foreach (['teaching_project', 'lesson_plan'] as $documentType) {
    $cases[] = [true, $head, $own + ['document_type' => $documentType], 'Head reviews own ' . $documentType];
}
$cases[] = [true, $head, array_replace($own, ['teacher_id' => 11]), 'Head reviews department colleague'];
$cases[] = [false, $head, array_replace($own, ['teacher_id' => 11, 'department_id' => 3]), 'Head cannot review another department'];
$cases[] = [false, $head, array_replace($own, ['_type' => 'pa']), 'Head cannot review own PA'];
$cases[] = [false, $head, array_replace($own, ['_type' => 'research']), 'Head cannot review own research'];
$cases[] = [false, array_replace($head, ['role' => 'teacher']), $own, 'Teacher cannot review own plan'];
$cases[] = [false, array_replace($head, ['department_id' => 0]), $own, 'Head needs a department'];
$designated = array_replace($head, ['position' => reviewer_positions()['lesson_plan'][0]]);
$cases[] = [true, $designated, $own, 'Head with designated position reviews own plan'];
$cases[] = [false, array_replace($designated, ['role' => 'teacher']), $own, 'Designated teacher cannot review own plan'];
$cases[] = [true, array_replace($head, ['role' => 'admin']), $own, 'Full admin reviews own plan'];
$cases[] = [false, array_replace($head, ['role' => 'admin', 'admin_permissions' => '[]']), $own, 'Limited admin still needs review permission'];

foreach ($cases as [$expected, $user, $sub, $message]) {
    if (can_review_submission($user, $sub) !== $expected) {
        throw new RuntimeException($message);
    }
    echo "PASS: $message\n";
}

$plan = $own + ['doc_type' => 'plan'];
$evalCases = [
    [true, $head, $plan, 'Head evaluates own plan'],
    [true, $designated, $plan, 'Head with curriculum position evaluates own plan'],
    [true, $head, array_replace($plan, ['teacher_id' => 11]), 'Head evaluates colleague plan'],
    [false, $head, array_replace($plan, ['teacher_id' => 11, 'department_id' => 3]), 'Head cannot evaluate another department'],
    [false, $head, array_replace($plan, ['doc_type' => 'project']), 'Teaching project cannot be evaluated as a plan'],
    [false, array_replace($head, ['department_id' => 0]), $plan, 'Evaluation requires department'],
    [false, array_replace($head, ['role' => 'teacher']), $plan, 'Teacher cannot evaluate own plan'],
    [false, array_replace($designated, ['role' => 'teacher']), $plan, 'Curriculum teacher cannot evaluate own plan'],
    [false, array_replace($head, ['role' => 'admin']), $plan, 'Admin own-plan restriction preserved'],
];
foreach ($evalCases as [$expected, $user, $item, $message]) {
    if (can_evaluate_plan($user, $item) !== $expected) {
        throw new RuntimeException($message);
    }
    echo "PASS: $message\n";
}
