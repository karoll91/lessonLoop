# LessonLoop classroom server settings. Change them to match this computer.
# Used by start.ps1, stop.ps1 and build.ps1.

$LL = @{
    # Who serves the web part (API, /admin/, /board/):
    #   'Own'      -- the nginx + php-cgi below. Answers on ANY address of this
    #                 computer (IP, name), so a changing IP (DHCP) never shows
    #                 OpenServer's «Как вы здесь оказались?» page. OpenServer is
    #                 then used only for MySQL: keep only MySQL-8.4 on and turn
    #                 its Nginx and PHP-8.3 modules off (they would take port 8000).
    #   'OSPanel6' -- Open Server Panel 6 serves it (project .osp\project.ini);
    #                 works only for the addresses listed in server_aliases.
    WebServer   = 'Own'
    # PHP 8.3 for artisan, Reverb, the scheduler and php-cgi.
    PhpDir      = 'G:\OSPanel\modules\PHP-8.3'
    # 'Own' mode: nginx for Windows (OpenServer 6's own binary works).
    NginxDir    = 'G:\OSPanel\modules\Nginx'
    # Teachers open http://<this computer's IP>:<HttpPort>/admin/ and /board/;
    # the phone app uses http://<IP>:<HttpPort>/api.
    HttpPort    = 8000
    # PHP FastCGI workers: requests are served in parallel by this many.
    PhpWorkers  = 4
    PhpPortBase = 9831
}
