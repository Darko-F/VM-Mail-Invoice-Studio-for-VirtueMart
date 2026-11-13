<?php
/**
 * Isolated upstream JED rule runner. This is NOT a full Joomla/JED installation.
 * Framework shims supply directory iteration, registry and untranslated report text.
 * Copyright (C) 2026 ToPowerYou. GNU General Public License version 2 or later.
 */
namespace Joomla\CMS\Language {
    class Text { public static function _($s) { return $s; } public static function sprintf($s,...$args) { return $s.' '.json_encode($args); } }
}
namespace Joomla\CMS\Filter {
    class InputFilter {
        public static function getInstance() { return new self; }
        public function clean($value,$type) { if ($type !== 'cmd') throw new \RuntimeException('Unsupported test shim filter'); return preg_replace('/[^A-Z0-9_.-]/i','',$value); }
    }
}
namespace Joomla\Registry {
    class Registry {
        private array $data=[];
        public function __construct($ignored=null) {}
        public function loadObject($data) { $this->data=(array)$data; }
        public function get($key,$default=null) { return $this->data[$key]??$default; }
    }
}
namespace Joomla\Filesystem {
    class Folder {
        public static function folders($path,$filter='.', $recurse=false,$full=false,...$rest) {
            $iter=$recurse ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::SELF_FIRST) : new \DirectoryIterator($path);
            $files=[];foreach($iter as $f) if($f->isDir() && preg_match(chr(1).$filter.chr(1),$f->getFilename())) $files[]=$full?$f->getPathname():$f->getFilename();
            sort($files);return $files;
        }
        public static function files($path,$filter='.', $recurse=false,$full=false,...$rest) {
            $iter=$recurse ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path,\FilesystemIterator::SKIP_DOTS)) : new \DirectoryIterator($path);
            $files=[];foreach($iter as $f) if($f->isFile() && preg_match(chr(1).$filter.chr(1),$f->getFilename())) $files[]=$full?$f->getPathname():$f->getFilename();
            sort($files);return $files;
        }
    }
}
namespace {
    define('_JEXEC',1);
    define('JVERSION','6.0.0'); define('JPATH_ADMINISTRATOR',dirname($argv[1], 3)); define('JPATH_ROOT',dirname(JPATH_ADMINISTRATOR));
    $source=$argv[1]??'';
    $target=$argv[2]??'';
    if(!is_dir($source.'/Rule') || !is_dir($target)) die("Usage: php tests/jed_rules.php JED_SRC TARGET\n");
    spl_autoload_register(function($class)use($source){
        $prefix='Joomla\\Component\\Jedchecker\\Administrator\\';
        if(str_starts_with($class,$prefix)) require $source.'/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    });
    $result=[];
    foreach(['Gpl','Jexec','Encoding','ErrorReporting','Framework','Security','Jamss','XmlLicense','XmlFiles','XmlManifest','XmlUpdateServer'] as $name) {
        $class='Joomla\\Component\\Jedchecker\\Administrator\\Rule\\Rules\\'.$name.'Rule';
        try {
            $rule=new $class($target);$rule->check();$report=$rule->getReport()->getData();
            $report['issues']=array_values(array_filter($report['issues'],fn($i)=>$i['type']!=='passed'));
            $result[$name]=$report;
        } catch(\Throwable $e) { $result[$name]=['runner_error'=>$e->getMessage()]; }
    }
    echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
}
