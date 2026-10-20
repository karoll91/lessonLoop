@echo off
rem LessonLoop: see deploy\start.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0start.ps1" %*
