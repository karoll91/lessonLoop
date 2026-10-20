@echo off
rem LessonLoop: see deploy\build.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0build.ps1" %*
