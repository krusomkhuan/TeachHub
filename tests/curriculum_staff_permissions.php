<?php
/** Run: C:/xampp/php/php.exe tests/curriculum_staff_permissions.php (no database). */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin_perm.php';
require_once __DIR__ . '/../includes/submissions.php';
require_once __DIR__ . '/../includes/advisor.php';
require_once __DIR__ . '/../includes/hr.php';
require_once __DIR__ . '/../includes/plan_eval.php';

$staff = ['id' => 20, 'role' => 'teacher', 'status' => 'active', 'department_id' => 0, 'position' => CURRICULUM_STAFF_POSITION];
$check = function (bool $expected, bool $actual, string $message): void {
    if ($actual !== $expected) {
        throw new RuntimeException($message);
    }
    echo "PASS: $message\n";
};

$check(true, is_curriculum_staff($staff), 'Teacher with the position is curriculum staff');
$check(true, is_curriculum_staff(array_replace($staff, ['position' => ' เจ้าหน้าที่งานพัฒนาหลักสูตรและการจัดการเรียนรู้ '])), 'Position whitespace is normalized');
$check(false, is_curriculum_staff(array_replace($staff, ['status' => 'suspended'])), 'Suspended account loses the right');
$check(false, is_curriculum_staff(array_replace($staff, ['position' => 'ครู'])), 'Ordinary teacher is not curriculum staff');
$check(false, is_curriculum_staff(array_replace($staff, ['role' => 'executive'])), 'Executive with the position is not curriculum staff');
$check(false, is_curriculum_staff(array_replace($staff, ['role' => 'admin'])), 'Admin keeps using admin permissions');
$check(true, is_privileged_position(CURRICULUM_STAFF_POSITION), 'Position cannot be self-assigned on the profile page');

// ไม่ได้สิทธิ์ admin ใด ๆ และไม่ได้ประเมินแผนเอง
foreach (array_keys(admin_permission_list()) as $perm) {
    $check(false, admin_can($staff, $perm), "Staff has no admin permission: $perm");
}
$check(false, is_plan_evaluator($staff), 'Staff is not a plan evaluator');
$check(false, is_designated_reviewer($staff, 'lesson_plan'), 'Staff does not review lesson plans');
$check(false, can_review_submission($staff, ['id' => 1, 'teacher_id' => 30, 'department_id' => 2, '_type' => 'lesson_plan']), 'Staff cannot review a submission');
$check(false, can_view_submission($staff, ['id' => 1, 'teacher_id' => 30, 'department_id' => 2, '_type' => 'research']), 'Staff cannot open research files');

// หัวหน้างานเป็นสิทธิ์คนละชุด
$head = array_replace($staff, ['position' => PLAN_EVAL_HEAD_POSITION, 'department_id' => 2]);
$check(false, is_curriculum_staff($head), 'Head is not curriculum staff');
$check(true, is_curriculum_head($head), 'Head keeps the head role');
$check(false, is_curriculum_head($staff), 'Staff is not head');
$check(true, is_designated_reviewer($head, 'lesson_plan'), 'Head reviews lesson plans');
$check(true, is_plan_evaluator($head), 'Head evaluates plans');
$check(true, in_array(CURRICULUM_STAFF_POSITION, staff_role_positions(), true) && in_array(PLAN_EVAL_HEAD_POSITION, staff_role_positions(), true), 'Both roles are selectable');
foreach (array_keys(admin_permission_list()) as $perm) {
    $check(false, admin_can($head, $perm), "Teacher-role head has no admin permission: $perm");
}
$check(false, is_unit_staff_position(PLAN_EVAL_HEAD_POSITION), 'Head still needs a department');
$check(true, is_unit_staff_position(CURRICULUM_STAFF_POSITION), 'Staff may have no department');
$check(true, plan_eval_report_scope($staff) === 0, 'Staff sees the college-wide plan evaluation report');

// เจ้าหน้าที่ดูแลระบบถูกตรวจสิทธิ์หน้าใหม่ด้วย
$limited = ['id' => 21, 'role' => 'admin', 'status' => 'active', 'admin_permissions' => '["plan_eval"]'];
$check(true, admin_can_path($limited, 'reports/plan_eval_reports.php'), 'Limited admin with plan_eval opens the report list');
$check(false, admin_can_path($limited, 'reports/supervision_summary.php'), 'Limited admin without reports cannot open the supervision summary');
$check(false, admin_can_path($limited, 'admin/rounds.php'), 'Limited admin without rounds cannot open rounds');
