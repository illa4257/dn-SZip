<?php
namespace bundle\szip;
use Exception, framework, std;

class SZip 
{
    private $path7z = null;
    private $isF = false;
    private $isRunning = false;
    private $inited = false;
    
    /**
     * @var string
     * Path to the archive.
     * --RU--
     * Путь к архиву.
     */
    public $path = "new.7z";
    
    /**
     * @var string
     * Archive type.
     * --RU--
     * Тип архива.
     */
    public $type = "7z";
    
    /**
     * @var int
     * Number of CPU threads.
     * --RU--
     * Число потоков ЦП.
     */
    public $threadCount = 4;
    
    /**
     * @var int
     * Compression level.
     * 0 - without compression, 9 - ultra compression.
     * Min: 0, max: 9.
     * --RU--
     * Уровень сжатия.
     * 0 - без сжатия, 9 - ультра компрессия.
     * Мин: 0, макс: 9.
     */
    public $compressionLevel = 5;
    
    /**
     * @var string
     * Archive password.
     * --RU--
     * Пароль от архива.
     */
    public $password = "";
    
    /**
     * @return Process
     */
    private function run($path,$args,$szp = true){
        $script = $this->path7z.rand(0,999999).".bat";
        $path = fs::abs($path);
        $data = "@echo off\n".$path[0].":\ncd ".$path."\n";
        if($szp){
            $data .= $this->path7z."7z.exe ";
        }
        $data .= implode(" ",$args)."\ndel ".$script;
        file_put_contents($script,$data);
        $p = new Process(["cmd","/c",$script]);
        return $p->start();
    }
    
    private function check(){
        while(!$this->inited){}
        while($this->isRunning){}
        if($this->isFree()){
            throw new Exception("SZip is destroyed!");
            return true;
        }
        if(!static::OSHaveSupport()){
            throw new Exception("SZip does not support this os.");
            return true;
        }
    }
    
    public function __construct($path = "new.7z", $archiveType = "7z", $path7z = null){
        if($path=="" or $path==null) $path = "new.7z";
        $this->path = $path;
        if($archiveType=="" or $archiveType==null) $archiveType = "7z";
        $this->type = $archiveType;
        if($path7z==null or $path7z=="") $path7z = fs::abs(System::getProperty('java.io.tmpdir').rand(0,999999))."/";
        $this->path7z = $path7z;
        fs::makeDir($this->path7z);
        fs::copy("res://bundle/szip/7z/7z.dll",$this->path7z."7z.dll");
        fs::copy("res://bundle/szip/7z/7z.exe",$this->path7z."7z.exe");
        fs::copy("res://bundle/szip/7z/License.txt",$this->path7z."License.txt");
        fs::copy("res://bundle/szip/7z/readme.txt",$this->path7z."readme.txt");
        $this->inited = true;
    }
    
    /**
     * Is there support for this OS?
     * Supported: Windows
     * --RU--
     * Есть ли поддержка этой ОС?
     * Поддерживается: Windows
     */
    public static function OSHaveSupport(){
        $OS = str::lower(System::getProperty("os.name"));
        $support = str::contains($OS,"win");
        return $support;
    }
    
    /**
     * Remove all 7-zip files from the drive.
     * --RU--
     * Удаление всех файлов 7-zip с накопителя.
     */
    public function free(){
        $this->isF = true;
        fs::clean($this->path7z);
        fs::delete($this->path7z);
    }
    
    /**
	 * @return bool
     * Is destroyed?
     * --RU--
     * Уничтожено?
     */
    public function isFree(){
        return $this->isF;
    }
    
    private function removeSpaces($str){
        return str::sub($str,str::pos($str,str::replace($str," ","")[0]));
    }
    
    /**
     * @return array
     * List of all archive files.
     * --RU--
     * Список всех файлов архива.
     */
    public function list(){
        if($this->check()) return;
        $args = ["l"];
        $args[] = "-mmt".$this->threadCount;
        $args[] = "-y";
        $args[] = "\"".fs::abs($this->path)."\"";
        $logs = $this->run(fs::parent($this->path),$args)->getInput()->readFully();
        $logs = str::replace($logs,"\r","");
        $logs = str::lines($logs);
        $f = true;
        $i = 0;
        $b = false;
        $list = [];
        while($i<arr::count($logs) and $f){
            $line = $logs[$i];
            if($b){
                if(str::sub($line,0,3)=="---"){
                    $f = false;
                }else{
                    $line = str::sub($line,str::pos($line,":")+7);
                    $type = str::sub($line,0,5);
                    $line = str::sub($line,5);
                    $line = $this->removeSpaces($line);
                    $size = str::sub($line,0,str::pos($line," "));
                    $line = str::sub($line,str::pos($line," ")+1);
                    $line = $this->removeSpaces($line);
                    $compressed = str::sub($line,0,str::pos($line," "));
                    if((float) $compressed==0 and $compressed!="0"){
                        $compressed = 0;
                    }else{
                        $line = str::sub($line,str::pos($line," ")+1);
                    }
                    $path = $this->removeSpaces($line);
                    if($compressed==false or $compressed==str::sub($path,0,str::pos($path," "))){
                        $compressed = 0;
                    }
                    $list[] = new SZipFile([
                        "path"=>$path,
                        "size"=>$size,
                        "compressed"=>$compressed,
                        "type"=>$type,
                        "SZip"=>$this
                    ]);
                }
            }elseif(str::sub($line,0,3)=="---"){
                $b = true;
            }
            $i++;
        }
        return $list;
    }
    
    /**
     * Rename file in the archive.
     * Can be used to navigate through the archive.
     * --RU--
     * Переименовать файл в архиве.
     * Можно использовать для перемещения по архиву.
     */
    public function rename($file, $newName){
        if($this->check()) return;
        if(gettype($file)!="string") $file = $file->path;
        $args = ["rn"];
        $args[] = "-mmt".$this->threadCount;
        $args[] = "-y";
        $args[] = "\"".fs::abs($this->path)."\"";
        $args[] = $file;
        $args[] = $newName;
        $this->run(fs::parent($this->path),$args)->getInput()->readFully();
    }
    
    /**
     * @return SZipStatus
     * Unpack files from archive (background).
     * --RU--
     * Распаковать файлы из архива (фоном).
     */
    public function unpackAsync($to = null){
        if($this->check()) return;
        $args = ["x"];
        $args[] = "-mmt".$this->threadCount;
        if($to!=null and $to!=""){
            $args[] = "-o\"".fs::abs($to)."\"";
        }
        if($this->password!=null and $this->password!=""){
            $args[] = "-p".$this->password;
        }
        $args[] = "-y";
        $args[] = "-bsp1";
        $args[] = "\"".fs::abs($this->path)."\"";
        return new SZipStatus($this->run(fs::parent($this->path),$args),["a"=>"Unpacking","zip"=>$this->path,"7zip"=>$this->path7z."7z.exe"]);
        
    }
    
    /**
     * Unpack files from archive.
     * --RU--
     * Распаковать файлы из архива.
     */
    public function unpack($to = null){
        $this->unpackAsync($to)->wait();
    }
    
    /**
     * @return SZipStatus
     * Add file to the archive (background).
     * --RU--
     * Добавить файл в архив (фоном).
     */
    public function addAsync($path){
        if($this->check()) return;
        $args = ["a"];
        $args[] = "-t".$this->type;
        $args[] = "-mmt".$this->threadCount;
        $args[] = "-mx".$this->compressionLevel;
        if($this->password!=null and $this->password!=""){
            $args[] = "-p".$this->password;
        }
        $args[] = "-y";
        $args[] = "-bsp1";
        $args[] = "\"".fs::abs($this->path)."\"";
        $args[] = "\"".fs::abs($path)."\"";
        return new SZipStatus($this->run(fs::parent($this->path),$args),["a"=>"Adding","zip"=>$this->path,"7zip"=>$this->path7z."7z.exe"]);
    }
    
    /**
     * Add file to the archive.
     * --RU--
     * Добавить файл в архив.
     */
    public function add($path){
        $this->addAsync($path)->wait();
    }
    
    /**
     * Function for creating sfx-archive.
     * In $sfxModule, enter the path to your sfx-module.
     * --RU--
     * Функция для создания sfx-архива.
     * В $sfxModule, надо ввести путь к вашему sfx-модулю.
     */
    public function makeSFX($sfxModule, $configFile, $to){
        $args = ["copy","/b","\"".fs::abs($sfxModule)."\" + \"".fs::abs($configFile)."\" + \"".fs::abs($this->path)."\"",fs::name($to)];
        $this->run($to,$args,false)->getInput()->readFully();
    }
}

class SZipStatus {
    /**
     * @var Process
     */
    private $Process;
    
    /**
     * Progress in the percents.
     * --RU--
     * Прогресс в процентах.
     */
    public $progress = 0;
    
    /**
     * Name of file.
     * --RU--
     * Имя файла.
     */
    public $filename = "";
    
    /**
     * Process is ended?
     * --RU--
     * Завершён ли процесс?
     */
    public $ended = false;
    
    private function removeSpaces($str){
        return str::sub($str,str::pos($str,str::replace($str," ","")[0]));
    }
    
    public function __construct(Process $p, $settings){
        $this->Process = $p;
        (new Thread(function () use ($p,$settings){
            $f = true;
            while($f){
                $msg = $p->getInput()->read(1024);
                if($msg==false){
                    $f = false;
                    $this->progress = 100;
                    $this->ended = true;
                }else{
                    if(str::contains($msg,"%")){
                        $pos = str::pos($msg,"%");
                        $name = str::sub($msg,$pos+1);
                        $name = $this->removeSpaces($name);
                        $name = str::sub($name,1);
                        $name = $this->removeSpaces($name);
                        $n2 = str::sub($name,0,2);
                        if($n2=="U " or $n2=="+ "){
                            $name = str::sub($name,1);
                            $name = $this->removeSpaces($name);
                        }
                        $this->filename = $name;
                        $msg = str::sub($msg,$pos-3,$pos);
                        $msg = str::replace($msg," ","");
                        $this->progress = (int) $msg;
                    }elseif(str::contains($msg,"Enter password")){
                        $this->progress = 100;
                        $this->ended = true;
                        $f = false;
                        try {
                            $p->destroy(true);
                        }catch(Exception $err){
                            
                        }
                    }
                }
            }
        }))->start();
    }
    
    /**
     * Wait for the process to complete.
     * --RU--
     * Ждать, пока процесс завершится
     */
    public function wait(){
        while(!$this->ended){}
    }
    
}

class SZipFile {
    /**
     * @var string
     * The path to the file in the archive.
     * --RU--
     * Путь к файлу в архиве.
     */
    public $path;
    
    /**
     * @var string
     * Type in the archive.
     * --RU--
     * Тип в архиве.
     */
    public $type;
    
    /**
     * @var int
     * File size if it weren't compressed.
     * --RU--
     * Размер файла, если он не был бы сжат.
     */
    public $size;
    
    /**
     * @var int
     * Compressed file size in archive.
     * --RU--
     * Размер сжатого файла в архиве.
     */
    public $compressed;
    
    /**
     * @var bool
     * Is directory?
     * --RU--
     * Это директория?
     */
    public $isDir = false;
    
    /**
     * @var SZip
     */
    public $SZip;
    
    public function __construct($data){
        $this->path = $data["path"];
        $this->type = $data["type"];
        if($this->type[0]=="D"){
            $this->isDir = true;
        }
        $this->size = $data["size"];
        $this->compressed = $data["compressed"];
        $this->SZip = $data["SZip"];
    }
    
    /**
     * Rename the file.
     * --RU--
     * Переименовать файл.
     */
    public function rename($newName){
        $this->SZip->rename($this,$newName);
    }
}