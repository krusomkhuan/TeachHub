# ชุด "ผู้ช่วยพัฒนา TeachHub PT" สำหรับ ChatGPT (Custom GPT)

ChatGPT ไม่มี "skill" แบบ Claude — ใช้ **Custom GPT** (ต้องใช้ ChatGPT Plus/Team/Edu ขึ้นไป) หรือ **Project** แทน

## ไฟล์ในชุดนี้
| ไฟล์ | ใช้ทำอะไร |
|---|---|
| `gpt_instructions.txt` | วางในช่อง **Instructions** ของ GPT (ไม่เกิน 8,000 ตัวอักษร) |
| `01_architecture.md` | Knowledge: โครงสร้างระบบ สิทธิ์ เมนู โมดูล |
| `02_database_schema.md` | Knowledge: ตาราง/คอลัมน์จริงทั้ง 54 ตาราง (สร้างจากฐานข้อมูลอัตโนมัติ) |
| `03_coding_conventions.md` | Knowledge: กติกาความปลอดภัย รูปแบบโค้ด การอัปเดตเวอร์ชัน การส่งมอบ |
| `04_function_index.md` | Knowledge: ฟังก์ชันกลางทั้งหมดใน includes/ (สร้างอัตโนมัติ) |

ไม่มีรหัสผ่าน ข้อมูลผู้ใช้จริง หรือค่าเชื่อมต่อฐานข้อมูลในชุดนี้ — แชร์ GPT ให้คนอื่นใช้ได้โดยไม่เปิดเผยข้อมูลจริง

## วิธีสร้าง Custom GPT
1. เข้า https://chatgpt.com → เมนูซ้าย **GPTs** → **Create** → แท็บ **Configure**
2. **Name:** ผู้ช่วยพัฒนา TeachHub PT
3. **Description:** ช่วยเขียน/แก้โค้ดระบบ TeachHub PT วิทยาลัยการอาชีพโพนทอง (PHP 8 + MariaDB)
4. **Instructions:** คัดลอกเนื้อหาทั้งหมดของ `gpt_instructions.txt` มาวาง
5. **Knowledge:** อัปโหลด `01_architecture.md`, `02_database_schema.md`, `03_coding_conventions.md`, `04_function_index.md`
6. **Conversation starters** (ตัวอย่าง):
   - เพิ่มเมนูใหม่ในหน้า admin ให้หน่อย
   - ออกแบบตารางฐานข้อมูลสำหรับฟีเจอร์ใหม่
   - ตรวจโค้ดหน้านี้ว่ามีช่องโหว่หรือไม่ (วางโค้ดตาม)
   - สรุปไฟล์ที่ต้องอัปโหลดขึ้นเว็บจริง
7. **Capabilities:** เปิด Code Interpreter (ช่วยอ่านไฟล์ที่อัปโหลด) · ปิด Image generation ได้
8. กด **Create** → เลือก **Only me** (หรือแชร์ภายในทีม)

> ทางเลือก: ใช้ **Project** ใน ChatGPT — สร้าง Project ใหม่ วาง `gpt_instructions.txt` ใน Project instructions แล้วอัปโหลด 4 ไฟล์ .md เป็น Project files

## เคล็ดลับการใช้งาน
- เมื่อให้แก้หน้าที่มีอยู่แล้ว ให้ **วางโค้ดไฟล์นั้นทั้งไฟล์** ในแชตด้วย (GPT รู้โครงสร้างแต่ไม่มีโค้ดทุกไฟล์)
- อัปเดตระบบเวอร์ชันใหม่แล้ว ให้สร้างชุดนี้ใหม่ (ดูด้านล่าง) และอัปโหลด Knowledge ทับไฟล์เดิม

## สร้างชุดนี้ใหม่หลังอัปเดตระบบ
02_database_schema.md และ 04_function_index.md สร้างจากโค้ด/ฐานข้อมูลจริง — รันใน Git Bash ที่โฟลเดอร์ระบบ:
```
/c/xampp/php/php.exe docs/chatgpt/build_gpt_pack.php
```
ไฟล์ 01 และ 03 เขียนด้วยมือ — แก้เมื่อมีโมดูลใหม่หรือกติกาเปลี่ยน
