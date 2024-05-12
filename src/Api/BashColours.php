<?php

namespace Sunnysideup\CronJobs\Api;

class BashColours
{
    public static function bash_to_html(?string $string = ''): string
    {
        $colors = [
            '/\[0;30m(.*?)\[0m/s' => '<div style="color: black; background-color: white;">$1</div>',
            '/\[0;31m(.*?)\[0m/s' => '<div style="color: red">$1</div>',
            '/\[0;32m(.*?)\[0m/s' => '<div style="color: green">$1</div>',
            '/\[0;33m(.*?)\[0m/s' => '<div style="color: brown">$1</div>',
            '/\[0;34m(.*?)\[0m/s' => '<div style="color: blue">$1</div>',
            '/\[0;35m(.*?)\[0m/s' => '<div style="color: purple">$1</div>',
            '/\[0;36m(.*?)\[0m/s' => '<div style="color: cyan">$1</div>',
            '/\[0;37m(.*?)\[0m/s' => '<div style="color: #D3D3D3">$1</div>',
            '/\[1;30m(.*?)\[0m/s' => '<div style="color: #A9A9A9">$1</div>',
            '/\[1;31m(.*?)\[0m/s' => '<div style="color: #ffcccb">$1</div>',
            '/\[1;32m(.*?)\[0m/s' => '<div style="color: #90EE90">$1</div>',
            '/\[1;33m(.*?)\[0m/s' => '<div style="color: yellow">$1</div>',
            '/\[1;34m(.*?)\[0m/s' => '<div style="color: #ADD8E6">$1</div>',
            '/\[1;35m(.*?)\[0m/s' => '<div style="color: #CBC3E3">$1</div>',
            '/\[1;36m(.*?)\[0m/s' => '<div style="color: #E0FFFF; ">$1</div>',
            '/\[1;37m(.*?)\[0m/s' => '<div style="color: white;">$1</div>',
        ];

        $string = preg_replace(array_keys($colors), $colors, $string);
        $string = str_replace("\n", '<br />', $string);
        $string = str_replace("\r", '<br />', $string);

        return str_replace('', '', (string) $string);
    }
}
