<?php
/**
 * gii命令行调用
 * Created by PhpStorm.
 * User: DJ
 * Date: 2019/2/14
 * Time: 10:44
 */

namespace dongqibin\gii\command;

use Phinx\Util\Util;
use think\console\input\Argument as InputArgument;
use think\console\Input;
use think\console\Output;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Command;

class Gii extends Command
{
    public $title = '首页';
    private $_is_cover = true;
    private $_output = null;

    /**
     * {@inheritdoc}
     * php think gii test show show -d"this this desc" -u"this this userName" -c "1"
     */
    protected function configure()
    {
        $this->setName('gii')
            ->setDescription('Create a table with program')
            //模块名
            ->addArgument('module', Argument::REQUIRED, 'What is the module of the program?')

            //表名
            ->addArgument('tableName', Argument::REQUIRED, 'What is the tableName of the program?')

            //标题
            ->addArgument('title', Argument::REQUIRED, 'What is the title of the program?')

            //描述[选填]
            ->addOption('desc', 'd', Option::VALUE_OPTIONAL, 'What is the desc of the program')

            //作者[选填]
            ->addOption('userName', 'u', Option::VALUE_OPTIONAL, 'What is the userName of the program?')

            //是否覆盖[选填]
            ->addOption('is_cover', 'c', Option::VALUE_OPTIONAL, 'is is_cover of the program?')

            ->setHelp(sprintf('%suse gii Creates a new program%s', PHP_EOL, PHP_EOL));
    }

    /**
     * Create the new program.
     *
     * @param Input  $input
     * @param Output $output
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @return void
     */
    protected function execute(Input $input, Output $output)
    {
        $this->_output = $output;
        $options = $input->getOptions();
        $arg = $input->getArguments();

        //验证参数
        if(empty($arg['module'])) {
            $this->_error('请提交模块名');
            return ;
        }
        if(empty($arg['tableName'])) {
            $this->_error('请提交表名');
            return ;
        }
        if(empty($arg['title'])) {
            $this->_error('请提交标题');
            return ;
        }


        $fileName = $arg['tableName'];
        $desc = empty($options['desc']) ? '描述' : $options['desc'];
        $userName = empty($options['userName']) ? 'DJ' : $options['userName'];
        $this->_is_cover = empty($options['is_cover']) ? '0' : $options['is_cover'];

        $module = $arg['module'];
        $title = $arg['title'];

        try {
            //创建控制器
            $this->_createController($module, $fileName, $title, $desc, $userName);

            //创建自有模块
            $this->_createModule($module, $fileName, $desc, $userName);

            //创建核心模块
            $this->_createModuleCommon($fileName, $desc, $userName);

            //创建自有模型
            $this->_createModel($module, $fileName, $desc, $userName);

            //创建公共模型
            $this->_createModelCommon($fileName, $desc, $userName);

            //创建列表视图
            $this->_createViewIndex($module, $fileName, $title);

            //创建表单视图
            $this->_createViewForm($module, $fileName, $title);

            //创建语言包
            $this->_createLang($fileName, $desc, $userName);
        } catch (Exception $e) {
            $this->_error('error : ' . $e->getMessage());
            return ;
        }

        $output->writeln('<info>created</info> the program '. $module .' ok');
    }

    public function _createLang($fileName, $desc='描述', $user='', $date='', $time='') {
        if(!$date) $date = date('Y/m/d');
        if(!$time) $time = date('H:i');

        $tableDesc = $this->_getTableInfo($fileName);
        $field = $tableDesc['fields'];
        $fieldData = [];
        foreach($field as $v) {
            $fieldData[$v] = $v;
        }
        $field = var_export($fieldData, true);

        $data = [
            '{$field}' => $field,
            '{$desc}'  => $desc,
            '{$user}'  => $user,
            '{$date}'  => $date,
            '{$time}'  => $time,
            '{$data}'  => $field,
        ];

        $stubPath = $this->_getStubPath('lang');
        $filePath = APP_PATH . 'lang/' . ucfirst($fileName) . '.php';
		if(is_file($filePath)) return true; //如果语言包存在,则不创建
        $this->_createBase($stubPath, $data, $filePath);
    }

    /**
     * 创建列表视图
     * @param $module
     * @param $fileName
     * @param $title
     */
    private function _createViewIndex($module, $fileName, $title) {
        $fileName = $this->_getLower($fileName);
        $data = [
            '{$title}'      => $title,
            '{$module}'     => $module,
            '{$fileName}'   => $fileName,
        ];
        $tableDesc = $this->_getTableInfo($fileName);
        $tableField = $tableDesc['fields'];
        $fieldStr = '';
        foreach($tableField as $field) {
            $status = '';
            if($field == 'status') $status = ', toolbar:\'#toolStatus\'';
            $fieldStr .= "{field:'{$field}', title:'<?php echo lang('{$field}');?>' $status},\r\n";
        }
        $data['{$fields}'] = $fieldStr;

        $this->_createView('view_index', $data, 'index', $tableField);
    }

    /**
     * 创建表单视图
     * @param $module
     * @param $fileName
     * @param $title
     */
    private function _createViewForm($module, $fileName, $title) {
        $data = [
            '{$title}'      => $title,
            '{$module}'     => $module,
            '{$fileName}'   => $this->_getlower($fileName),
        ];
        $tableDesc = $this->_getTableInfo($fileName);
        $tableField = $tableDesc['fields'];
        $fieldStr = '';
        unset($tableField[0]); //删除ID
        foreach($tableField as $field) {
            $fieldStr .= 'echo $formClass->setCluesLength(30)->text(lang("'. $field .'"),"'. $field .'",true,"required");' . "\r\n";
        }
        $data['{$fields}'] = $fieldStr;
        $this->_createView('view_form', $data, 'form');
    }

    /**
     * 创建公共模型
     * @param $fileName
     * @param string $desc
     * @param string $userName
     * @param string $date
     * @param string $time
     * @param $module
     */
    private function _createModelCommon($fileName, $desc='描述', $userName='', $date='', $time='', $module='common') {
        $data = [
            '{$desc}'       => $desc,
            '{$userName}'   => $userName,
            '{$date}'       => $date,
            '{$time}'       => $time,
            '{$module}'     => $module,
            '{$fileName}'   => $fileName,
        ];
        $this->_create('model_common', $data, 'model');
    }

    /**
     * 创建自有模型
     * @param $module
     * @param $fileName
     * @param string $desc
     * @param string $userName
     * @param string $date
     * @param string $time
     */
    private function _createModel($module, $fileName, $desc='描述', $userName='', $date='', $time='') {
        $data = [
            '{$desc}'       => $desc,
            '{$userName}'   => $userName,
            '{$date}'       => $date,
            '{$time}'       => $time,
            '{$module}'     => $module,
            '{$fileName}'   => $fileName,
        ];
        $this->_create('model', $data, 'model');
    }

    /**
     * 创建公共模块
     * @param $fileName 文件名
     * @param string $desc 描述
     * @param string $userName 作者
     * @param string $date 日期
     * @param string $time 时间
     * @param string $module 模块,默认common
     */
    private function _createModuleCommon($fileName, $desc='描述', $userName='', $date='', $time='', $module='common') {
        $data = [
            '{$desc}'       => $desc,
            '{$userName}'   => $userName,
            '{$date}'       => $date,
            '{$time}'       => $time,
            '{$module}'     => $module,
            '{$fileName}'   => $fileName,
        ];
        $this->_create('module_common', $data, 'module');
    }

    /**
     * 创建自有模块
     * @param $module 模块名
     * @param $fileName 文件名
     * @param string $desc 描述
     * @param string $userName 作者
     * @param string $date 日期
     * @param string $time 时间
     */
    private function _createModule($module, $fileName, $desc='描述', $userName='', $date='', $time='') {
        $data = [
            '{$desc}'       => $desc,
            '{$userName}'   => $userName,
            '{$date}'       => $date,
            '{$time}'       => $time,
            '{$module}'     => $module,
            '{$fileName}'   => $fileName,
        ];
        $this->_create('module', $data, 'module');
    }

    /**
     * 创建控制器
     * @param $module 模块名
     * @param $fileName 文件名
     * @param $title 标题
     * @param string $desc 描述
     * @param string $userName 作者
     * @param string $date 日期
     * @param string $time 时间
     */
    private function _createController($module, $fileName, $title, $desc='描述', $userName='DJ', $date='', $time='') {
        $data = [
            '{$desc}'       => $desc,
            '{$userName}'   => $userName,
            '{$date}'       => $date,
            '{$time}'       => $time,
            '{$module}'     => $module,
            '{$fileName}'   => $fileName,
            '{$title}'      => $title
        ];
        $this->_create('controller', $data, 'controller');
    }

    //创建文件
    private function _create($stubFileName, $data, $type='controller') {
        if(empty($data['{$date}'])) $data['{$date}'] = date('Y/m/d');
        if(empty($data['{$time}'])) $data['{$time}'] = date('H:i');
        if(!empty($data['{$fileName}'])) $data['{$fileNameUp}'] = ucfirst($data['{$fileName}']);

        //获取模板
        $stubPath = $this->_getStubPath($stubFileName);

        //获取目标文件
        $filePath = $this->_getPutFileName($data['{$fileNameUp}'], $data['{$module}'], $type);

        $this->_createBase($stubPath, $data, $filePath);
    }

    //创建文件
    private function _createView($stubFileName, $data, $fileName) {
        //获取模板
        $stubPath = $this->_getStubPath($stubFileName);

        //获取目标文件
        $filePath = $this->_getPutFileNameView($data['{$fileName}'], $fileName, $data['{$module}']);

        $this->_createBase($stubPath, $data, $filePath);
    }

    private function _createBase($stubPath, $data, $filePath) {
        //获取数据
        $content = file_get_contents($stubPath);

        //整理变量
        $content = strtr($content, $data);

        //生成文件
        if(!$this->_is_cover and is_file($filePath)) return true;
        file_put_contents($filePath, $content);
    }

    //获取模板文件
    private function _getStubPath($stubFileName) {
        return VENDOR_PATH . 'dongqibin/gii/src/stubs/' . $stubFileName . '.stub';
    }

    private function _getPutFileNameView($dirName, $fileName, $module='common') {
        $dir = APP_PATH . $module . '/view/';

        //如果是视图,驼峰转下划线
        $dirName = $this->_getLower($dirName);
        $dir .= $dirName .'/';
        if(!realpath($dir)) {
            mkdir($dir, 777, true);
        }
        return $dir . $fileName .'.php';
    }

    private function _getPutFileName($fileName, $module='common', $type='controller') {
        $dir = APP_PATH . $module . '/' . $type .'/';
        if(!realpath($dir)) {
            mkdir($dir, 777, true);
        }
        return $dir . $fileName .'.php';
    }

    //获取下划线
    private function _getLower($str) {
        return strtolower(preg_replace('/([a-z])([A-Z])/', "$1" . '_' . "$2", $str));
    }

    private function _getTableInfo($fileName) {
        return db($fileName)->getTableInfo();
    }

    private function _error($msg) {
        $this->output->writeln($msg);
    }
}