@echo off
rem Windows version of build-css.sh: builds public\css\pizi-*.css from the classes used in the code.
rem Run from the project folder (PowerShell or CMD):   scripts\build-css.cmd
rem Needs Node.js.
cd /d "%~dp0.."
for %%n in (site panel plain) do (
    call npx --yes tailwindcss@3.4.17 -c resources/tailwind/%%n.config.js -i resources/tailwind/input.css -o public/css/pizi-%%n.css --minify
    if errorlevel 1 (
        echo CSS build FAILED
        exit /b 1
    )
)
echo CSS ready: public\css
