# LessonLoop classroom server settings. Change them to match this computer.
# Used by start.ps1, stop.ps1 and build.ps1.

$LL = @{
    # Who serves the web part (API, /admin/, /board/):
    #   'OSPanel6' -- Open Server Panel 6 (project .osp\project.ini); start.bat
    #                 then runs only Reverb and the scheduler.
    #   'Own'      -- the nginx + php-cgi below (for a computer without OSPanel 6).
    WebServer   = 'OSPanel6'
    # PHP 8.3 for artisan, Reverb and the scheduler (and php-cgi in 'Own' mode).
    PhpDir      = 'G:\OSPanel\modules\PHP-8.3'
    # 'Own' mode only: nginx for Windows.
    NginxDir    = 'D:\OSPanel\modules\http\Nginx_1.23'
    # Teachers open http://<this computer's IP>:<HttpPort>/admin/ and /board/;
    # the phone app uses http://<IP>:<HttpPort>/api.
    HttpPort    = 8000
    # PHP FastCGI workers: requests are served in parallel by this many.
    PhpWorkers  = 4
    PhpPortBase = 9831
}
