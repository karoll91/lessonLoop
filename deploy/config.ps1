# LessonLoop classroom server settings. Change them to match this computer.
# Used by start.ps1, stop.ps1 and build.ps1.

$LL = @{
    # PHP 8.3 (NTS build with php-cgi.exe). OpenServer 5 only has PHP <= 8.1.
    PhpDir      = 'D:\php83'
    # nginx that comes with OpenServer; any nginx for Windows works.
    NginxDir    = 'D:\OSPanel\modules\http\Nginx_1.23'
    # Teachers open http://<this computer's IP>:<HttpPort>/admin/ and /board/;
    # the phone app uses http://<IP>:<HttpPort>/api.
    HttpPort    = 8000
    # PHP FastCGI workers: requests are served in parallel by this many.
    PhpWorkers  = 4
    PhpPortBase = 9831
}
