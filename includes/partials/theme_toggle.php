<?php
/**
 * ไฟล์: includes/partials/theme_toggle.php
 * ปุ่มสลับโหมดกลางคืน/โหมดสว่าง (การทำงานอยู่ใน assets/js/app.js)
 * ตัวแปร: $themeToggleClass = คลาสของปุ่มตามตำแหน่งที่วาง
 */
?>
<button type="button" class="<?= e($themeToggleClass ?? 'btn btn-link') ?> theme-toggle" data-theme-toggle aria-pressed="false"
        title="สลับโหมดกลางคืน" aria-label="สลับโหมดกลางคืน">
    <i class="bi bi-moon-stars-fill theme-icon-moon"></i><i class="bi bi-sun-fill theme-icon-sun"></i>
</button>
