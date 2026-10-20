@echo off
rem LessonLoop: see deploy\stop.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0stop.ps1" %*
