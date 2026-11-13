<?php
/** Isolated upstream 2.4.4 XML licence rule. Copyright (C) 2026 ToPowerYou. GNU GPL v2 or later. */
namespace Joomla\CMS\Object { class CMSObject {} }
namespace Joomla\CMS\Language { class Text { public static function _($s) { return $s; } } }
namespace {
    define('_JEXEC',1);
    define('JPATH_COMPONENT_ADMINISTRATOR',$argv[1]);
    require JPATH_COMPONENT_ADMINISTRATOR.'/libraries/rules/xmllicense.php';
    $reflect=new \ReflectionClass('JedcheckerRulesXMLlicense');
    $rule=$reflect->newInstanceWithoutConstructor();
    $report=new class {
        public array $errors=[];
        public function addError(...$args) { $this->errors[]=$args; }
        public function addCompat(...$args) { $this->errors[]=$args; }
    };
    $property=$reflect->getProperty('report');$property->setAccessible(true);$property->setValue($rule,$report);
    $method=$reflect->getMethod('find');$method->setAccessible(true);
    foreach(array_slice($argv,2) as $file) {
        if (!$method->invoke($rule,$file)) throw new \RuntimeException('Licence check failed: '.$file);
        echo 'PASS upstream JED 2.4.4 XML licence: '.basename($file)."\n";
    }
}
