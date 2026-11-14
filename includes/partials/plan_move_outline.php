<?php
/**
 * ไฟล์: includes/partials/plan_move_outline.php
 * ปุ่มย้ายเอกสารที่ครูส่งผิดหมวด ระหว่างแผนการจัดการเรียนรู้กับโครงการสอน (ผู้เรียกต้องตรวจ can_move_lesson_doc แล้ว)
 * ต้องมีตัวแปร: $movePlanId (lesson_plans.id), $moveTo ('outline' | 'plan' = หมวดปลายทาง)
 */
$moveReason = $moveTo === 'plan' ? plan_move_plan_block_reason((int)$movePlanId) : plan_move_outline_block_reason((int)$movePlanId);
$moveConfirm = $moveTo === 'plan'
    ? 'ย้ายเอกสารนี้ไปหมวดแผนการจัดการเรียนรู้? สถานะจะกลับเป็น "รอตรวจ" และครูต้องส่งโครงการสอนของวิชานี้ใหม่'
    : 'ย้ายเอกสารนี้ไปหมวดโครงการสอน? สถานะจะกลับเป็น "รอตรวจ" ร่างผลประเมิน (ถ้ามี) จะถูกลบ และครูต้องส่งแผนการจัดการเรียนรู้ใหม่หากยังไม่มีวิชาอื่น';
?>
<div class="alert alert-light border d-flex flex-wrap align-items-center gap-2 small">
    <span><i class="bi bi-arrow-left-right me-1 text-accent"></i><?= $moveTo === 'plan' ? 'เอกสารนี้เป็นแผนการจัดการเรียนรู้ที่อยู่ผิดหมวดใช่ไหม?' : 'ครูส่งโครงการสอนมาผิดหมวดใช่ไหม?' ?></span>
    <?php if ($moveReason !== ''): ?>
        <span class="text-muted"><?= e($moveReason) ?></span>
    <?php else: ?>
        <form method="post" action="<?= e(url('review/plan_evaluations.php')) ?>" class="ms-auto"
              data-confirm="<?= e($moveConfirm) ?>" data-confirm-icon="warning">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $moveTo === 'plan' ? 'move_to_plan' : 'move_to_outline' ?>">
            <input type="hidden" name="plan_id" value="<?= (int)$movePlanId ?>">
            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-right me-1"></i> <?= $moveTo === 'plan' ? 'ย้ายไปหมวดแผนการจัดการเรียนรู้' : 'ย้ายไปหมวดโครงการสอน' ?></button>
        </form>
    <?php endif; ?>
</div>
