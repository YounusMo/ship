<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class langController extends Controller
{
    public const ALLOWED_LANGS = ['en', 'ar', 'zh'];
    public const DEFAULT_LANG  = 'en';

    public static function normalize($lang){
        return in_array($lang, self::ALLOWED_LANGS, true) ? $lang : self::DEFAULT_LANG;
    }

    public function get_lang($lang = 'en'){
        if(Auth::check() && auth()->user()->lang){
            $lang = auth()->user()->lang;
        }
        $lang = self::normalize($lang);
        return file_get_contents(__DIR__.'/langs/'.$lang.'.json');
    }

    public function branch($branch){
        $lang = self::normalize(auth()->user()->lang);

        $get = DB::table('branches')->where('id',$branch)->first();

        if(!$get){
            return '-';
        }

        switch($lang){
            case 'ar':
                return $get->name;
            break;
            case 'zh':
                return $get->name_zh;
            break;
            case 'en':
                return $get->name_en;
            break;
        }
    }

    public static function write($arg,$lang = 'en'){
        $lang = null;

        if (Auth::guard('web')->check() && Auth::guard('web')->user()->lang) {
            $lang = Auth::guard('web')->user()->lang;
        } elseif (Auth::guard('client')->check() && Auth::guard('client')->user()->lang) {
            $lang = Auth::guard('client')->user()->lang;
        }else{
            $lang = self::DEFAULT_LANG;
        }

        $lang = self::normalize($lang);

        $langData = json_decode(file_get_contents(__DIR__.'/langs/'.$lang.'.json'),true);

        if(isset($langData[trim($arg)])){
            return $langData[trim($arg)];
        }else{
            $langData[$arg] = $arg;
            file_put_contents(__DIR__.'/langs/'.$lang.'.json',json_encode($langData,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
            return $langData[trim($arg)];
        }
    }
}
