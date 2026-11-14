<?php
/**
 * ไฟล์: includes/partials/theme_init.php
 * ใส่ใน <head> ก่อนโหลด CSS: ใช้โหมดกลางคืนที่ผู้ใช้เลือกไว้ทันที หน้าจะได้ไม่กะพริบเป็นสีขาวก่อน
 */
?>
<script>(function () { try { if (localStorage.getItem('th-theme') === 'dark') { document.documentElement.setAttribute('data-bs-theme', 'dark'); } } catch (e) {} })();</script>
