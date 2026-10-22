@echo off
rem Makes Open Server Panel 6 see LessonLoop: G:\OSPanel\home\lessonloop.loc
rem becomes a link (junction) to this repository. The code stays where it is.
set "OSP_HOME=G:\OSPanel\home"
set "REPO=%~dp0.."
if exist "%OSP_HOME%\lessonloop.loc" (
    echo Allaqachon bor: %OSP_HOME%\lessonloop.loc
    exit /b 0
)
mklink /J "%OSP_HOME%\lessonloop.loc" "%REPO%"
