<?
set_time_limit(0);

$ScriptName = "index.php"; //Название скрипта
$ExploreDir = "."; //В какой директории будет просмотр
$FileInfoExt = ".txt"; //Например: Есть файл - Image.jpg создаем файл в той же папке Image.jpg.txt в который вписываем информацию о картинке... на листинге файлов такой файл не отображается. виден только в блоке Info!
$DescrFile = "description"; //Файл с описанием каталога! содержимое отображается в среднем блоке. первая строка этого файла будет отображаться вместо реального названия каталога в листинге
$ShowDotFiles = false; //Флаг отображения файлов начинающихся с "."
$NeedZip = true; //Флаг создания зип-архивов
$Zip = "/usr/bin/zip"; //Путь к бинарникам архиватора
$Tmp = "/tmp"; //Путь к временному каталогу
$ShowThumbnails = true; //Флаг отображения тумбнайлов (true/false или 1/0)
$MaxSide = "2000"; //Максимальный размер стороны картинки для создания тумбнайлов (зависит от объема памяти выделенной для пхп)
$ThumbnailSide = 300; //Сторона тумбнайла. т.е. картинка в листинге смасштабируется по этой сторне по высоте или по ширине (смотря что больше)
$CharsetOnFS = "u"; //k - koi8-r, w - windows-1251, u - UTF-8. Заглушка от старой функции convert-cyr-string. Переписано на iconv!
$DefaultStyle = "gray"; //Какой стиль использовать по умолчанию
$NeedContextFind = true; //Флаг использования контекстного поиска в текстовых файлах
$AlwaysContextFind = false; //Всегда использовать контекстный поиск, или только при заполненом основном поиске
$UseImgIfExists = true; //Использовать картинки, если они доступны
$ImgPath = "files_img"; //Папка с картинками (должна быть в одной папке со скриптом)
$TableWidth = "60%"; //Ширина основной таблицы
$HtmlHeaders = true; //Отрисовывать заголовки html
$DefaultSortMode = "name";
$AllowWrite = true; //Разрешить запись файлов
$ConfigFile = "_config.inc.php";//Если существует - использовать его! иначе все что выше! (не отображается в листинге файлов!)
$Lang = 'english';

/* !!!DON`T EDIT!!! */
$Find = isset($_POST["Find"]) ? $_POST["Find"] : false;
$ContextFind = $NeedContextFind && isset($_POST["ContextFind"]) ? $_POST["ContextFind"] : false;

$error = '';

$Path=isset($_GET["Path"]) ? base64_decode($_GET["Path"]) : "";
$Path=$Path==".."?"":$Path;
$Path=str_replace("/..","",$Path);
$Path=str_replace("../","",$Path);
$Path=str_replace("./","",$Path);
$Path=str_replace("//","/",$Path);

$ImgExtArr = array(".jpg",".jpeg",".gif",".png");
$Version = "2.0.6";

$Version .= "-intl";

if(file_exists($ConfigFile)) require_once($ConfigFile);

if(file_exists("lang/$Lang.inc.php"))
	require_once("lang/$Lang.inc.php");
else {
define('L_NAME','Name');
define('L_DATE','Date');
define('L_SIZE','Size');
define('L_FOLDER','Folder');
define('L_SEARCH','Search');
define('L_CONTAINS','Contains');
define('L_THEME','Theme');
define('L_VERSION','Version');
define('L_COPYRIGHT','Copyrights');
}

if(!file_exists($ExploreDir."/".cyr_convert($Path,"u",$CharsetOnFS))) $Path = "";

$DefaultSortMode = Cookie("get","files-SortMode",$DefaultSortMode);
$SelectedSortMode = isset($_POST["SortMode"]) && $_POST["SortMode"] != "" ? $_POST["SortMode"] : $DefaultSortMode;
Cookie("set","files-SortMode",$SelectedSortMode);

$lsdir = $ExploreDir."/".$Path;

$lsdir = $Find || $ContextFind ? $ExploreDir : $lsdir;

$Title = L_FOLDER.": ".DirDescr($ExploreDir.$Path);

$Title = $Find || $ContextFind ? "Search results" : $Title;

$PostMaxSize = ini_get('post_max_size');
$PostMaxSize = strpos($PostMaxSize,'M') ? str_replace('M','',$PostMaxSize)*1048576 : $PostMaxSize;
$PostMaxSize = strpos($PostMaxSize,'K') ? str_replace('K','',$PostMaxSize)*1024 : $PostMaxSize;

$UploadMaxSize = ini_get('upload_max_filesize');
$UploadMaxSize = strpos($UploadMaxSize,'M') ? str_replace('M','',$UploadMaxSize)*1048576 : $UploadMaxSize;
$UploadMaxSize = strpos($UploadMaxSize,'K') ? str_replace('K','',$UploadMaxSize)*1024 : $UploadMaxSize;

$MaxFileSize = $PostMaxSize > $UploadMaxSize ? $UploadMaxSize : $PostMaxSize;

function readfile_chunked ($filename) {
	$chunksize = 1*(1024*1024); // how many bytes per chunk
	$buffer = '';
	$handle = fopen($filename, 'rb');
	if ($handle === false) {
	return false;
	}
	while (!feof($handle)) {
	$buffer = fread($handle, $chunksize);
	print $buffer;
	}
	return fclose($handle);
}
if (!function_exists('mime_content_type'))
{
	function mime_content_type($f)
	{
		$f = escapeshellarg($f);
		return trim( `file -bi $f` );
	}
}

if($Path != "")
{
	if(isset($_GET["Zip"]) && $_GET["Zip"] == 1 && $NeedZip) die(ZipDir($lsdir));
	if(isset($_GET["Info"]) && $_GET["Info"] == 1) die(FileInfo($Path));
	if(isset($_GET["Img"]) && $_GET["Img"] == 1) die(CreateThumbnail($Path));
	if(isset($_GET["DownLoad"]) && $_GET["DownLoad"] == 1) die(Download($Path));
}

if(isset($_GET['Delete']))
{
	$file = base64_decode($_GET['Delete']);
	if(isset($_GET['Confirm']) && $_GET['Confirm'])
	{
		unlink($ExploreDir.'/'.$file);
		die(header('Location: ./?Path='.base64_encode($Path)));
	}
	else
	{
		die('<a href="?Path='.base64_encode($Path).'&Delete='.$_GET['Delete'].'&Confirm=1">Delete file <b>'.$file.'</b>?</a>');
	}
}

if($AllowWrite && isset($_POST['writefile']) && isset($_FILES['userfile']))
{
	if(file_exists($ExploreDir.'/'.$Path.'/'.$_FILES['userfile']['name']))
	{
		$error = 'Файл '.$_FILES['userfile']['name'].' существует!';
	}
	else
	{
		move_uploaded_file($_FILES['userfile']['tmp_name'],  $ExploreDir.'/'.$Path.'/'.$_FILES['userfile']['name']);
	}
}

$FindArr = $ContextFind || $Find ? true : false;

if($FindArr)
{
	if($Find && $ContextFind) $FindArr = ContextFind($ContextFind,Find($Find));
	elseif ($Find) $FindArr = Find($Find);
	elseif ($ContextFind) $FindArr = ContextFind($ContextFind);
	
	$Path = "";
}

$lsArr = !$FindArr ? ls($lsdir) : $FindArr;

$Dirs = $lsArr["dirs"];
$Files = $lsArr["files"];

$ParentArr = explode("/",$Path);
array_pop($ParentArr);
$ParentPath = "";
foreach ($ParentArr as $value) $ParentPath .= $value != "" ? "/".$value : "";

$Path = $Find ? "" : $Path."";

$HeadRow = $Title;

//Write file form
$WriteForm = '
<DIV align="center">
Новый файл (Макс. размер '.number_format($MaxFileSize/1024/1024,0,'.',' ').'Мб)
<FORM method="POST" enctype="multipart/form-data" name="ChoseFile" id="ChoseFile">
	<INPUT type="file" name="userfile" id="userfile" size="50">
	<INPUT type="submit" name="writefile" id="writefile" value="Положить">
</FORM>
</DIV>
';
//End write file form

//Описалово стилей
$StyleArr["console"] = "
<style type=\"text/css\">	<!--

	BODY {
		background-color : Black;
		margin-left : 0px;
		margin-bottom : 0px;
		margin-right : 0px;
		margin-top : 0px;
		font-family : monospace;
		font-size : 9pt;
	}
	A:LINK, A:VISITED {
		color : Yellow;
		text-decoration : none;
	}
	A:ACTIVE, A:FOCUS {
		color : Yellow;
		text-decoration : none;
	}
	A:HOVER {
		color : Yellow;
		text-decoration : none;
	}
	TD.DataRow1{
		border-right: 1px solid Gray;
		border-bottom: 1px solid #DCDCDC;
		background-color : Black;
		font-size : 9pt;
		color : White;
	}
	TD.DataRow2{
		border-right: 1px solid Gray;
		border-bottom: 1px solid Silver;
		background-color : Navy;
		color : White;
		font-size : 9pt;
	}
	TD.Head{
		border: medium double #87CEFA;
		background-color: #00008B;
		color: White;
		font-size : 9pt;
	}
	TH.Head{
		background-color: #008B8B;
		color: Lime;
		font-family: sans-serif;
		font-size: 10pt;
		border-bottom: 1px solid Black;
	}
	TD.Descr{
		font-family: sans-serif;
		font-size: 10pt;
		background-color: Silver;
		color: Black;
		border: 1px solid White;
	}
	TD.Footer{
		font-family: sans-serif;
		font-size: 9pt;
		font-style: italic;
		background-color: #00008B;
		color: #87CEFA;
		border-top: 1px solid #00008B;
	}
	TABLE.DirTable{
		border: 1px inset #F5F5F5;
		background-color: #F5F5F5;
		font-family: sans-serif;
		font-size: 10pt;
	}
	INPUT
	{
		border: 1px solid #87CEFA;
		font-size: 9pt;
		background-color : #696969;
		color : White;
	}
	SELECT
	{
		font-size: 9pt;
		background-color : Black;
		border : 1px solid Lime;
		color : White;
	}
	-->
</style>
";

$StyleArr["blue"] = "
<style type=\"text/css\">
	<!--
	BODY {
		background-color : #708090;
		margin-left : 0px;
		margin-bottom : 0px;
		margin-right : 0px;
		margin-top : 0px;
		font-family : sans-serif;
	}
	A:LINK, A:VISITED {
		color : #0000CD;
		text-decoration : none;
	}
	A:ACTIVE, A:FOCUS {
		color : Red;
		text-decoration : none;
	}
	A:HOVER {
		color : Red;
		text-decoration : none;
	}
	TD.DataRow1{
		border-right: 1px solid #4682B4;
		border-bottom: 1px solid #4682B4;
		background-color : #F5F5F5;
	}
	TD.DataRow2{
		border-right: 1px solid #4682B4;
		border-bottom: 1px solid #4682B4;
		background-color : #F0F8FF;
	}
	TD.Head{
		border: thin outset #1E90FF;
		background-color: #4169E1;
		color: White;
	}
	TH.Head{
		background-color: #6495ED;
		color: #F0F8FF;
		font-family: sans-serif;
		font-size: 12pt;
		text-align : left;
		padding-left: 4px;
		border-bottom: 1px solid #00008B;
	}
	TD.Descr{
		font-family: sans-serif;
		font-size: 10pt;
		background-color: #ADD8E6;
		color: Black;
		border: 1px inset #ADD8E6;
	}
	TD.Footer{
		font-family: sans-serif;
		font-size: 9pt;
		font-style: italic;
		background-color: #6495ED;
		color: #F0F8FF;
		border-top: 1px solid #00008B;
	}
	TABLE.DirTable{
		border: 1px inset #F5F5F5;
		background-color: #F5F5F5;
		font-family: sans-serif;
		font-size: 10pt;
	}
	INPUT
	{
		border: 1px solid #C0C0CF;
		font-size: 9pt;
	}
	SELECT
	{
		font-size: 9pt;
	}
-->
</style>
";

$StyleArr["green"] = "
<style type=\"text/css\">	<!--
	BODY {
		background-color : #608070;
		margin-left : 0px;
		margin-bottom : 0px;
		margin-right : 0px;
		margin-top : 0px;
		font-family : sans-serif;
	}
	A:LINK, A:VISITED {
		color : #006400;
		text-decoration : none;
	}
	A:ACTIVE, A:FOCUS {
		color : Lime;
		text-decoration : none;
	}
	A:HOVER {
		color : #2F4F4F;
		text-decoration : none;
	}
	TD.DataRow1{
		border-right: 1px solid Green;
		border-bottom: 1px solid Green;
		background-color : #F0FFF0;
	}
	TD.DataRow2{
		border-right: 1px solid Green;
		border-bottom: 1px solid Green;
		background-color : #F5FFFA;
	}
	TD.Head{
		border: thin outset #90EE90;
		background-color: #2E8B57;
		color: White;
	}
	TH.Head{
		background-color: #2E8B57;
		color: #F0F8FF;
		font-family: sans-serif;
		font-size: 12pt;
		border-bottom: 1px solid #00FF7F;
	}
	TD.Descr{
		font-family: sans-serif;
		font-size: 10pt;
		background-color: #8FBC8F;
		color: Black;
		border: 1px inset #006400;
	}
	TD.Footer{
		font-family: sans-serif;
		font-size: 9pt;
		font-style: italic;
		background-color: #3CB371;
		color: Black;
		border-top: 1px solid #006400;
	}
	TABLE.DirTable{
		border: 1px inset #F5F5F5;
		background-color: #F5F5F5;
		font-family: sans-serif;
		font-size: 10pt;
	}
	INPUT
	{
		border: 1px solid #C0C0CF;
		font-size: 9pt;
		background-color : #F0FFF0;
	}
	SELECT
	{
		font-size: 9pt;
	}
	-->
</style>
";

$StyleArr["symdos"] = "
<style type=\"text/css\">	<!--
	BODY {
		background-color : #00008B;
		margin-left : 0px;
		margin-bottom : 0px;
		margin-right : 0px;
		margin-top : 0px;
		font-family : sans-serif;
	}
	A:LINK, A:VISITED {
		color : blue;
		text-decoration : none;
	}
	A:ACTIVE, A:FOCUS {
		color : blue;
		text-decoration : none;
	}
	A:HOVER {
		color : blue;
		text-decoration : none;
	}
	TD.DataRow1{
		border-right: 1px solid Blue;
		border-bottom: 1px solid Blue;
		background-color : White;
		color : Blue;
	}
	TD.DataRow2{
		border-right: 1px solid Blue;
		border-bottom: 1px solid Blue;
		background-color : #DCDCDC;
		color : Blue;
	}
	TD.Head{
		border: 1px solid Blue;
		background-color: Blue;
		color: White;
	}
	TH.Head{
		background-color: #1E90FF;
		color: #F0F8FF;
		font-family: sans-serif;
		font-size: 12pt;
		border-bottom: 1px solid #00008B;
	}
	TD.Descr{
		font-family: sans-serif;
		font-size: 10pt;
		background-color: #1E90FF;
		color: Yellow;
		border: 1px inset #006400;
	}
	TD.Footer{
		font-family: sans-serif;
		font-size: 9pt;
		font-style: italic;
		background-color: White;
		color: Blue;
		border-top: 1px solid #006400;
	}
	TABLE.DirTable{
		border: 1px inset #F5F5F5;
		background-color: #F5F5F5;
		font-family: sans-serif;
		font-size: 10pt;
	}
	INPUT
	{
		border: 1px solid Blue;
		font-size: 9pt;
		background-color : White;
		color : #00008B;
	}
	SELECT
	{
		font-size: 9pt;
	}
	-->
</style>
";

$StyleArr["gray"] = "
<style type=\"text/css\">
	<!--
	BODY {
		background-color : #708090;
		margin-left : 0px;
		margin-bottom : 0px;
		margin-right : 0px;
		margin-top : 0px;
		font-family : sans-serif;
	}
	A:LINK, A:VISITED {
		color : #0000CD;
		text-decoration : none;
	}
	A:ACTIVE, A:FOCUS {
		color : #4169E1;
		text-decoration : none;
	}
	A:HOVER {
		color : Red;
		text-decoration : none;
	}
	TD.DataRow1{
		border-right: 1px solid Black;
		border-bottom: 1px solid Black;
		background-color : #F5F5F5;
		color : Black;
	}
	TD.DataRow2{
		border-right: 1px solid Black;
		border-bottom: 1px solid Black;
		background-color : #FDF5E6;
		color : Black;
	}
	TD.Head{
		border: 1px solid Black;
		background-color: #B0C4DE;
		color: Black;
		margin-left : 3px;
		margin-right : 3px;
	}
	TH.Head{
		background-color: #D3D3D3;
		color: Black;
		font-family: sans-serif;
		font-size: 12pt;
		border-bottom: 1px thin;
		margin-left : 3px;
		margin-right : 3px;
	}
	TD.Descr{
		font-family: monospace;
		font-size: 11pt;
		background-color: #DCDCDC;
		color: Black;
		border: 1px solid Black;
	}
	TD.Footer{
		font-family: sans-serif;
		font-size: 9pt;
		font-style: italic;
		background-color: #DCDCDC;
		color: Black;
		border-top: 1px solid Black;
	}
	TABLE.DirTable{
		border: 1px solid #696969;
		background-color: #F0F8FF;
		font-family: sans-serif;
		font-size: 10pt;
		color : #696969;
	}
	INPUT
	{
		border: 1px solid #C0C0CF;
		font-size: 9pt;
	}
	SELECT
	{
		font-size: 9pt;
	}
	-->
</style>
";

$StyleArr["far"] = "
<style type=\"text/css\">
	<!--
	BODY {
		background-color : Black;
		margin-left : 0px;
		margin-bottom : 0px;
		margin-right : 0px;
		margin-top : 0px;
		font-family : sans-serif;
	}
	A:LINK, A:VISITED {
		color : #87CEFA;
		text-decoration : none;
	}
	A:ACTIVE, A:FOCUS {
		color : Aqua;
		text-decoration : none;
	}
	A:HOVER {
		color : Aqua;
		text-decoration : none;
	}
	TD.DataRow1{
		border-right: 1px solid #00BFFF;
		border-bottom: 1px solid #00BFFF;
		background-color : #00008B;
		color : Aqua;
	}
	TD.DataRow2{
		border-right: 1px solid #00BFFF;
		border-bottom: 1px solid #00BFFF;
		background-color : #0000CD;
		color : Aqua;
	}
	TD.Head{
		border: 1px solid #00BFFF;
		background-color: #00008B;
		color: Yellow;
	}
	TH.Head{
		background-color: #20B2AA;
		color: Yellow;
		font-family: sans-serif;
		font-size: 12pt;
		border-bottom: 1px solid #00BFFF;
	}
	TD.Descr{
		font-family: monospace;
		font-size: 11pt;
		background-color: #00008B;
		color: Lime;
		border: 1px solid #00BFFF;
	}
	TD.Footer{
		font-family: sans-serif;
		font-size: 9pt;
		font-style: italic;
		background-color: Navy;
		color: Aqua;
		border-top: 1px solid #00BFFF;
	}
	TABLE.DirTable{
		border: 1px solid #F5F5F5;
		background-color: Black;
		font-family: sans-serif;
		font-size: 10pt;
		color : Yellow;
	}
	INPUT
	{
		border: 1px solid #C0C0CF;
		font-size: 9pt;
	}
	SELECT
	{
		font-size: 9pt;
	}
	-->
</style>
";

$DefaultStyle = Cookie("get","files-style",$DefaultStyle);

$SelectedStyle = isset($_POST["style"]) && key_exists($_POST["style"],$StyleArr) ? $_POST["style"] : $DefaultStyle;

Cookie("set","files-style",$SelectedStyle);

$StyleBlock = $StyleArr[$SelectedStyle];

//Sort form
$SortForm = "
<form method='POST'>
	<select name='SortMode' onchange='submit()'>
		<option".($SelectedSortMode=='name'?' selected':'')." value='name'>Name</option>
		<option".($SelectedSortMode=='rname'?' selected':'')." value='rname'>Name (rev)</option>
		<option".($SelectedSortMode=='date'?' selected':'')." value='date'>Date</option>
		<option".($SelectedSortMode=='rdate'?' selected':'')." value='rdate'>Date (rev)</option>
	</select>
</form>
";

//Select style form
$StyleOptions = "";
foreach(array_keys($StyleArr) as $style)
{
	$selected = $style == $SelectedStyle ? " selected" : "";
	$StyleOptions .= "\t\t<option value='".$style."'".$selected.">".$style."</option>\n";
}
$StyleForm = "
<form method='POST'>
	".L_THEME.":
	<select name='style' id='style' onchange='submit()'>
".$StyleOptions."\t</select>
</form>
";

//Find form
$disabledContext = !$AlwaysContextFind ? " disabled " : "" ;
$FindForm = "
<form method='POST'>
	".L_SEARCH.":
	<input type=\"text\" name=\"Find\" id=\"Find\" size=\"12\" value=\"".$Find."\"";
$FindForm .= $NeedContextFind && !$AlwaysContextFind ? " onkeyup=\"EnDisContextFind()\">\n" : ">\n";
$FindForm .= $NeedContextFind ? "	".L_CONTAINS.": <input type=\"text\" name=\"ContextFind\" id=\"ContextFind\" size=\"12\" value=\"".$ContextFind."\"".$disabledContext.">\n" : "";
$FindForm .= "	<input type=\"submit\" value=\"->\">
	<INPUT type=\"button\" value=\"X\" onclick=\"Find.value=''; submit();\">
</form>\n";

//Options table
$OptionsTable = "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">
<tr><td class=\"Descr\" align=\"right\">";
/*$OptionsTable .= "
<form method='POST'>
	Контекстный поиск:
	<input type=\"text\" name=\"ContextFind\" id=\"ContextFind\" value=\"".$ContextFind."\">
	<input type=\"submit\" value=\"->\">
	<INPUT type=\"button\" value=\"X\" onclick=\"ContextFind.value=''; submit();\">
</form>
";
*/
$OptionsTable .= "</td></tr>
</table>\n";

//Directories

$StrRoot = "Root";

$LinkToUp = ""; $tmpPath = "";
$PathArr = explode("/",$Path);
foreach ($ParentArr as $tmpDir)
{
	$tmpPath .= $tmpDir != "" ? "/".$tmpDir : "";
	$strDir = $tmpDir == "" ? $StrRoot : $tmpDir;
	$LinkToUp .= "<a href=\"?Path=".base64_encode($tmpPath)."\">".trim(DirDescr($strDir))."</a>/";
}
unset($tmpDir);
$LinkToUp .= DirDescr($ExploreDir."/".$Path);
$LinkToUp = $Path == "" ? $StrRoot : $LinkToUp;
$LinkToUp = $Find || $ContextFind ? "<a href=\"?Path=\">".$StrRoot."</a>": $LinkToUp;
$LinkToUp = $UseImgIfExists ? CreateIcon($Path,$LinkToUp) : $LinkToUp;

//Dirs
foreach ($Dirs as $dir)
{
	$DirName = $Find ? $dir : DirDescr($ExploreDir."/".$Path."/".$dir);
	$DirName = $UseImgIfExists ? CreateIcon($dir,$DirName) : $DirName;
	$DirLink = "<a href=\"?Path=".base64_encode($Path."/".$dir)."\">".$DirName."</a>";
	$TD["name"] = "<strong>".$DirLink."</strong>";
	$TD["date"] = FileDate($ExploreDir."/".$Path."/".$dir);
	$TD["size"] = "<strong>&#060;FOLDER&#062;</strong>";
	$TD["link"] = FileLink($lsdir."/".$file);
	$TD["info"] = "[<a href=\"?Zip=1&Path=".base64_encode($Path."/".$dir)."\">ZIP</a>]";
	if($AllowWrite) $TD["write"] = "";
	
	$Table[]=$TD;
}

//Files
foreach ($Files as $file)
{
	$filename = $file;
	
	if ($ShowThumbnails)
	{
		foreach ($ImgExtArr as $ImgExt)
		{
			if(strtolower(substr($file,strlen($file)-strlen($ImgExt),strlen($ImgExt))) == strtolower($ImgExt))
			{
				$filename = CreateImgTag(str_replace(" ","%20","?Img=1&Path=".base64_encode(cyr_convert($Path."/".$file,$CharsetOnFS,"u"))),$file);
			}
		}
	}
	
	$filename = $UseImgIfExists ? CreateIcon($file,$filename) : $filename;
	$DownLink = "<a href=\"?DownLoad=1&Path=".base64_encode($Path."/".$file)."\">".$filename."</a>";
	$Info = "[<a href=\"javascript:OpenInfo('".base64_encode($Path."/".$file)."')\">Info</a>]";
	$Write = is_writable($ExploreDir."/".$Path."/".$file) ? "[<a href=\"?Path=".base64_encode($Path)."&Delete=".base64_encode($Path."/".$file)."\">Delete</a>]" : "";
	
	$TD["name"] = $DownLink;
	$TD["date"] = FileDate($lsdir."/".$file);
	$TD["size"] = SizeFile($lsdir."/".$file);
	$TD["link"] = FileLink($lsdir."/".$file);
	$TD["info"] = $Info;
	if($AllowWrite) $TD["write"] = $Write;
	
	$Table[]=$TD;
}

//Options
if(!isset($Table)) $Table[] = array("name"=>"","date"=>"","size"=>"","info"=>"");

$DescrRow = "";
$DescrArr = DirDescr($lsdir,false);
foreach($DescrArr as $value) $DescrRow .= $value;
$DescrRow = $DescrRow == "" ? "&nbsp;" : "\n<PRE>\n".$DescrRow."</PRE>\n";

$FootRow = L_VERSION.": <strong>".$Version."</strong> ".L_COPYRIGHT.": <strong><a href=\"http://3x.ru\" target=\"_blank\">3x</a></strong> this script was created by <strong><a href=\"mailto:lopatich@onmail.ru\">Kobra</a></strong> and <strong><a href=\"mailto:kerzzz@onmail.ru\">Kerzzz</a> </strong> intl by <a href=\"http://github.com/tpruvot/php-FileManager\">T.Pruvot</a>";

function ls($Path)
{
	global $DescrFile, $FileInfoExt, $ScriptName, $CharsetOnFS, $ShowDotFiles, $ExploreDir, $ConfigFile, $ImgPath, $SelectedSortMode;
	
	$Path = cyr_convert($Path,"u",$CharsetOnFS);
	$ret["dirs"] = array();
	$ret["files"] = array();
	$handle = opendir($Path) or die("Cannot open dir ".$Path."!");
	
	while (false !== ($file = readdir($handle)))
	{
		if($file == "." || $file == ".." || $file == $DescrFile || $file == $ScriptName) continue;
		if(($Path == $ExploreDir || $Path == $ExploreDir."/") && $file == $ConfigFile) continue;
		if(($Path == $ExploreDir || $Path == $ExploreDir."/") && $file == $ConfigFile.'.example') continue;
		if(!$ShowDotFiles && $file[0] == ".") continue;
		if(!is_dir($Path."/".$file) && substr($file,-1*strlen($FileInfoExt)) == $FileInfoExt && file_exists($Path."/".str_replace(substr($file,strlen($file)-strlen($FileInfoExt),strlen($FileInfoExt)),"",$file))) continue;
		if(is_dir($file) && ($Path == $ExploreDir || $Path == $ExploreDir."/") && $file == $ImgPath) continue;
		if(is_dir($Path."/".$file)) $ret["dirs"][] = cyr_convert($file,$CharsetOnFS,"u");
		if(is_file($Path."/".$file)) $ret["files"][] = cyr_convert($file,$CharsetOnFS,"u");
	}
	
	closedir($handle);

	switch ($SelectedSortMode)
	{
		case 'rdate':
			usort($ret["dirs"],'byrdate');
			usort($ret["files"],'byrdate');
		break;
		case 'date':
			usort($ret["dirs"],'bydate');
			usort($ret["files"],'bydate');
		break;
		case 'name':
			sort($ret["dirs"]);
			sort($ret["files"]);
		break;
		case 'rname':
			rsort($ret["dirs"]);
			rsort($ret["files"]);
		break;
	}
	return $ret;
}

function byrdate($a, $b)
{
	global $ExploreDir, $Path, $CharsetOnFS;
	if(!file_exists($ExploreDir.'/'.$Path.'/'.cyr_convert($a,"u",$CharsetOnFS))) return 0;
	if(!file_exists($ExploreDir.'/'.$Path.'/'.cyr_convert($b,"u",$CharsetOnFS))) return 0;
	$stat_a=stat($ExploreDir.'/'.$Path.'/'.cyr_convert($a,"u",$CharsetOnFS));
	$stat_b=stat($ExploreDir.'/'.$Path.'/'.cyr_convert($b,"u",$CharsetOnFS));
	
	if($stat_a['ctime'] == $stat_b['ctime']) return 0;

	return ($stat_a['ctime'] > $stat_b['ctime']) ? -1 : 1;
}

function bydate($a, $b)
{
	global $ExploreDir, $Path, $CharsetOnFS;
	if(!file_exists($ExploreDir.'/'.$Path.'/'.cyr_convert($a,"u",$CharsetOnFS))) return 0;
	if(!file_exists($ExploreDir.'/'.$Path.'/'.cyr_convert($b,"u",$CharsetOnFS))) return 0;
	$stat_a=stat($ExploreDir.'/'.$Path.'/'.cyr_convert($a,"u",$CharsetOnFS));
	$stat_b=stat($ExploreDir.'/'.$Path.'/'.cyr_convert($b,"u",$CharsetOnFS));
	
	if($stat_a['ctime'] == $stat_b['ctime']) return 0;

	return ($stat_a['ctime'] < $stat_b['ctime']) ? -1 : 1;
}

function Find($key)
{
	global $ExploreDir, $Path;
	$ret["dirs"] = array();
	$ret["files"] = array();

	$Arr = _AllFiles($ExploreDir.'/'.$Path);
	foreach ($Arr as $dir => $files)
	{
		$lastdir = explode("/",substr($dir,0,-1));
		$last = array_pop($lastdir);
		if(strpos("x".strtolower($last),strtolower($key)) && $dir != $ExploreDir."/") $ret["dirs"][] = substr(str_replace($ExploreDir."/","",$dir),0,-1);
		
		foreach ($files as $file)
		{
			if(strpos("x".strtolower($file),strtolower($key)))
			{
			    $ret["files"][] = str_replace($ExploreDir."/","",$dir).$file;
			}
		}
	}
	
	return $ret;
}


function ContextFind($key,$FileArr = false)
{
	global $ExploreDir, $Path;
	$ret["dirs"] = array();
	$ret["files"] = array();
	
	function grep($file,$key)
	{
		if(is_dir($file))
		{
			exec("grep -rli \"".$key."\" ".$file,$out);
			$out = empty($out) ? false : $out;
			return $out;
		}
		else
		{
			$mimetype = mime_content_type($file);
			$pathinfo = pathinfo($file);
			$mimetype = isset($pathinfo["extension"]) && strtolower($pathinfo["extension"]) == "txt" ? "text/plain" : $mimetype;
			if(!preg_match("/^text/",$mimetype)) return false;
			$cmd = "grep -li \"".$key."\" ".$file;
			return exec($cmd);
		}
	}
	
	if($FileArr)
	{
		foreach ($FileArr["dirs"] as $dir)
		{
			$files = grep($ExploreDir."/".$dir,$key);
			if($files) foreach ($files as $file) $ret["files"][] = str_replace($ExploreDir."/","",$file);
		}
		foreach ($FileArr["files"] as $file)
		{
			if(grep($ExploreDir."/".$file,$key)) $ret["files"][] = str_replace($ExploreDir."/","",$file);
		}
	}
	else
	{
		$Arr = _AllFiles($ExploreDir.'/'.$Path);
		foreach ($Arr as $dir => $files)
		{
			foreach ($files as $file)
			{
				if(grep($dir.$file,$key)) $ret["files"][] = str_replace($ExploreDir."/","",$dir).$file;
			}
		}
	}
	
	return $ret;
}

function _AllFiles($Dir=false)
{
	global $ExploreDir;
	
	$Dir = !$Dir ? $ExploreDir : $Dir;
	
	$FileArr = ls($Dir);
	$ret[$Dir."/"] = $FileArr["files"];
	
	foreach ($FileArr["dirs"] as $Path)
	{
		$ret = array_merge($ret,_AllFiles($Dir."/".$Path));
	}
	
	$ret = !isset($ret) ? array() : $ret ;
	return $ret;
}

function FileDate($filename)
{
	global $CharsetOnFS;
	$ret = stat(cyr_convert($filename,"u",$CharsetOnFS));
	$ret = date("d.m.Y H:m",$ret["mtime"]);
	return $ret;
}

function SizeFile($filename)
{
	global $CharsetOnFS;
	$ret = intval(filesize(cyr_convert($filename,"u",$CharsetOnFS)));
	
	if (is_link($filename)) $ret=0;

	$ret = $ret/1024;
	$ret = number_format($ret,2,","," ")." Kb";
	
	return $ret;
}

function FileLink($filename)
{
	$ret = "";
	if (is_link($filename)) $ret = "→".readlink($filename);
	return $ret;
}

function ZipDir($dirname)
{
	global $Zip, $Tmp, $CharsetOnFS;
	
	$dirname = cyr_convert($dirname,"u",$CharsetOnFS);
	
	$arcname=tempnam($Tmp,"ZIP");
	
	$justdirname = array_pop(explode("/",$dirname));
	
	exec("cd \"".$dirname."/..\" && ".$Zip." -r \"".$arcname."\" \"".$justdirname."\"");
	
	header('Content-type: application/zip');
	header('Content-Disposition: attachment; filename="'.cyr_convert($justdirname,$CharsetOnFS,"u").'.zip"');
	readfile_chunked($arcname.".zip");
	
	unlink($arcname);
	unlink($arcname.".zip");
}

function DirDescr($dirname,$short = true)
{
	global $DescrFile, $ExploreDir, $Find, $CharsetOnFS;
	$dirname = cyr_convert($dirname,"u",$CharsetOnFS);
	$dirarray = explode("/",cyr_convert($dirname,$CharsetOnFS,"u"));
	if($short) $ret = array_pop($dirarray);
	else $ret[] = array_pop($dirarray);
	
	$descr = false;
	if(file_exists($dirname."/".$DescrFile))
	{
		$descr = file($dirname."/".$DescrFile);
	}
	
	if($descr && $short) $ret = $descr[0];
	elseif($descr) $ret = $descr;
	
	$ret = $short && $dirname == $ExploreDir ? "Root" : $ret;
	
	return $ret;
}

function FileInfo($File)
{
	global $ExploreDir, $FileInfoExt, $StyleBlock, $CharsetOnFS;
	$File = cyr_convert($File,"u",$CharsetOnFS);
	if(!file_exists($ExploreDir.$File)) die();
	
	$StatArr = stat($ExploreDir.$File);
	
	$InfoArr[] = "Файл:          ".cyr_convert($File,$CharsetOnFS,"u")."\n";
	$InfoArr[] = "\n";
	$InfoArr[] = "Размер:        ".number_format($StatArr["size"]/1024,2,","," ")." Kb\n";
	$InfoArr[] = "Доступ:        ".date("d/m/Y H:m:s",$StatArr["atime"])."\n";
	$InfoArr[] = "Модифицирован: ".date("d/m/Y H:m:s",$StatArr["mtime"])."\n";
	$InfoArr[] = "Изменен:       ".date("d/m/Y H:m:s",$StatArr["ctime"])."\n";
	$InfoArr[] = "\n";
	$InfoArr[] = "MIME тип:      ".mime_content_type($ExploreDir.$File)."\n";
	$InfoArr[] = "\n";
	
	$FileArr = file_exists($ExploreDir.$File.$FileInfoExt) ? array_merge($InfoArr,file($ExploreDir.$File.$FileInfoExt)) : $InfoArr;
	
	$FilePathArr = explode("/",cyr_convert($File,$CharsetOnFS,"u"));
	
	echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">

<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>Информация о '.str_replace($FileInfoExt,"",array_pop($FilePathArr)).'</title>
</head>
'. $StyleBlock .'
<body>
';
	echo "<pre>\n";
	foreach ($FileArr as $line)
	{
		echo $line;
	}
	echo "</pre>\n";
	echo '</body>
</html>
';
}

function CreateImgTag($img,$alt="",$align="",$name="")
{
	global $CharsetOnFS;
	$img = cyr_convert($img,"u",$CharsetOnFS);
	if ($alt=="") $alt=cyr_convert($img,$CharsetOnFS,"u");
	if ($align!="") $align=" align='$align'";
	if ($name!="") $name=" name=$name id=$name";
	
	$Referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "http://" ;
	list($method) = explode("://",$Referer);
	$imgfile = $method."://".$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME'].$img;
	
	//$FileSize = GetImageSize($imgfile);
	//$width = $FileSize[0]; $height = $FileSize[1];
	
	//return "<IMG border=0 src='$img' alt='$alt' title='$alt' height='$height' width='$width'".$align.$name.">";
	return "<IMG border=0 src='$img' alt='$alt' title='$alt'".$align.$name.">";
}

function CreateImgTagWoHTTP($img,$alt="",$align="left",$name="")
{
	global $CharsetOnFS;
	$img = cyr_convert($img,"u",$CharsetOnFS);
	if ($alt=="") $alt=cyr_convert($img,$CharsetOnFS,"u");
	if ($align!="") $align=" align='$align'";
	if ($name!="") $name=" name=$name id=$name";
	
	$FileSize = GetImageSize($img);
	$width = $FileSize[0]; $height = $FileSize[1];
	
	return "<IMG border=0 src='$img' alt='$alt' title='$alt' height='$height' width='$width'".$align.$name.">";
}

function CreateIcon($file,$str)
{
	global $ExploreDir,$Path,$ImgPath,$CharsetOnFS;
	
	$ret = $str;
	$fullfile = cyr_convert($ExploreDir.$Path."/".$file,"u",$CharsetOnFS);
	$imgpref = $ImgPath."/";
	
	if($file == $Path)
	{
		$ret = file_exists($imgpref."curfolder.gif") ? CreateImgTagWoHTTP($imgpref."curfolder.gif",$file)." ".$ret : $ret;
	}
	elseif(is_dir($fullfile))
	{
		$ret = file_exists($imgpref."folder.gif") ? CreateImgTagWoHTTP($imgpref."folder.gif",$file)." ".$ret : $ret;
	}
	elseif(is_file($fullfile))
	{
		$pathinfo = pathinfo($fullfile);
		$ext = isset($pathinfo["extension"]) ? strtolower($pathinfo["extension"]) : "unknown";
		$ext = !file_exists($imgpref.$ext.".gif") ? "unknown" : $ext;
		$ret = file_exists($imgpref.$ext.".gif") ? CreateImgTagWoHTTP($imgpref.$ext.".gif",$file)." ".$ret : $ret;
	}
	
	return $ret;
}

function CreateThumbnail($Img)
{
	global $ExploreDir, $ThumbnailSide, $CharsetOnFS, $MaxSide;
	
	$Img = cyr_convert($ExploreDir."/".$Img,"u",$CharsetOnFS);
	if($Img == $ExploreDir."/" || !file_exists($Img)) die();
	list($width, $height) = @getimagesize($Img);
	
	$imgname = pathinfo($Img);
	
	$create = $width > $MaxSide || $height > $MaxSide ? false : true;
	
	header("Content-type: image/png");
	$thumb = false;
	/*
	if($create)	$thumb = strpos("x".strtolower($Img),"jpg") || strpos("x".strtolower($Img),"jpeg")
			? @imagecreatefromjpeg($Img)
			: @imagecreatefromgif($Img);
	*/
	
	switch (strtolower($imgname["extension"]))
	{
		case 'jpg';
		case 'jpeg';
			$thumb = @imagecreatefromjpeg($Img);
			break;
		case 'gif':
			$thumb = @imagecreatefromgif($Img);
			break;
		default:
			$thumb = @imagecreatefrompng($Img);
			break;
	}
	
	if (!$thumb)
	{ /* See if it failed */
		$ErrorString = $imgname["basename"];
		$thumb = imagecreate ($ThumbnailSide, 30); /* Create a blank image */
		$bgc = imagecolorallocate ($thumb, 255, 255, 255);
		$tc = imagecolorallocate ($thumb, 0, 0, 0);
		$bgc = imagecolortransparent ($thumb, $bgc);
		imagefilledrectangle ($thumb, 0, 0, 150, 30, $bgc);
		/* Output an errmsg */
		imagestring ($thumb, 3, 5, 5, $ErrorString, $tc);
	}
	else
	{
		$Img = $thumb;
		if($width > $ThumbnailSide || $height > $ThumbnailSide)
		{
			$r = $height/$width;
			$newheight = ($height > $width) ? $ThumbnailSide : $ThumbnailSide*$r;
			$newwidth = $newheight/$r;
			$thumb = ImageCreateTrueColor($newwidth,$newheight);
			
			ImageCopyResized($thumb, $Img, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
		}
	}
	imagepng($thumb);
	imagedestroy($thumb);
}

function Download($FileName)
{
	global $ExploreDir, $CharsetOnFS;
	
	$FileName = cyr_convert($ExploreDir."/".$FileName,"u",$CharsetOnFS);
	$PathInfo = pathinfo($FileName);
	$Ext = isset($PathInfo["extension"]) ? $PathInfo["extension"] : "";
	$BaseName = $PathInfo["basename"];
	
	if($FileName == $ExploreDir."/" || !file_exists($FileName)) die();

	$mimetype = mime_content_type($FileName);
	$pathinfo = pathinfo($FileName);
	$mimetype = isset($pathinfo["extension"]) && strtolower($pathinfo["extension"]) == "txt" ? "text/plain" : $mimetype;
	list($mimegen) = explode("/",$mimetype);
	
	header('Content-type: '.$mimetype);
	if($mimegen != "text" && $mimegen != "image")
		header('Content-Disposition: attachment; filename="'.cyr_convert($BaseName,$CharsetOnFS,"u").'"');
	readfile_chunked($FileName);
}

function nbsp($str)
{
	$str = trim($str);
	return $str == "" ? "&nbsp;" : $str;
}

function Cookie($method,$cookie,$value)
{
	 switch ($method)
	 {
	 	case "get":
	 		$ret = isset($_COOKIE[$cookie]) ? $_COOKIE[$cookie] : $value;
	 		return $ret;
	 		break;
	 	case "set":
	 		setcookie($cookie,$value,time()+2592000);
	 		break;
	 }
}

function cyr_convert($str,$from,$to)
{
	//$ret = convert_cyr_string($str,$from,$to);
	
	$fromcharset = charset_cyr($from);
	$tocharset = charset_cyr($to);
	
	$ret = iconv($fromcharset, $tocharset, $str);
	
	return $ret;
}

function charset_cyr($cyrcharacter)
{
	switch ($cyrcharacter)
	{
		case 'w':
			return 'cp1251';
			break;
		case 'k':
			return 'koi8-r';
			break;
		case 'u':
			return 'utf-8';
			break;
	}
}

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">

<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title><?=$Title?></title>
<SCRIPT language="JavaScript">
function OpenInfo(File)
{
	window.open("?Info=1&Path="+File,"ResolWindow","toolbar=no,status=no,menubar=no,scrollbars=no,resizable=0,width=400,height=200");
}
<?if($NeedContextFind && !$AlwaysContextFind){?>
function EnDisContextFind()
{
	if (document.getElementById('Find').value != '')
		document.getElementById('ContextFind').disabled=false;
	else
		document.getElementById('ContextFind').disabled=true;
}
<?}?>
</SCRIPT>
</head>

<?=$StyleBlock?>

<?
$Tmp = "<body";
$Tmp .= $NeedContextFind && !$AlwaysContextFind ? " onload=\"EnDisContextFind()\">\n" : ">\n";
echo $Tmp;
?>
<table align="center" width="<?=$TableWidth?>" height="100%" cellspacing="0" cellpadding="0" border="0">
<tr>
	<th height="1" class="Head"><?=$HeadRow?>
		<table width="100%" cellspacing="0" cellpadding="0" border="0" style="font-size:10pt;">
<?if($error != ''){?>
                        <tr align='center'><td colspan="2">
                        	<h3><font color='red'><?=$error?></font></h3>
			</td></tr>
<?}?>
			<tr align="center"><td><?=$FindForm?></td><td><?=$StyleForm?></td></tr>
<?if($AllowWrite && is_writable($ExploreDir.'/'.$Path)){?>
			<tr><td colspan="2">
<?=$WriteForm?>
			</td></tr>
<?}?>
		</table>
	</th>
</tr>
<tr>
	<td width="50%" valign="top">
	<!--  -->
	<table width="100%" height="100%" border="0" cellspacing="0" cellpadding="0" class="DirTable">
		<tr>
			<td height="1" align="left" class="Head"><?=L_NAME?></td>
			<td height="1" align="center" class="Head" width="22%"><?=L_DATE?></td>
			<td height="1" align="center" class="Head" width="15%"><?=L_SIZE?></td>
			<td height="1" class="Head" width="10%" nobr><?=$SortForm?></td>
<?if($AllowWrite){?>
			<td height="1" class="Head" width="10%">&nbsp;</td>
<?}?>
		</tr>
		<tr>
			<td height="1" class="DataRow2" colspan="<?=$AllowWrite?5:4;?>"><strong><?=$LinkToUp?></strong></td>
		</tr>
<?$StyleClass="DataRow2";foreach($Table as $TD){$StyleClass=$StyleClass=="DataRow1"?"DataRow2":"DataRow1";?>
		<tr>
			<td height="1" align="left" class="<?=$StyleClass?>"><?=nbsp($TD["name"])?> <?=nbsp($TD["link"])?></td>
			<td height="1" align="right" class="<?=$StyleClass?>"><pre><?=nbsp($TD["date"])?></pre></td>
			<td height="1" align="right" class="<?=$StyleClass?>"><pre><?=nbsp($TD["size"])?></pre></td>
			<td height="1" align="center" class="<?=$StyleClass?>"><pre><?=nbsp($TD["info"])?></pre></td>
<?if($AllowWrite && isset($TD["write"])){?>
                        <td height="1" align="center" class="<?=$StyleClass?>"><pre><?=nbsp($TD["write"])?></pre></td>
<?}?>
		</tr>
<?}?>
		<tr>
			<td class="DataRow1">&nbsp;</td>
			<td class="DataRow1">&nbsp;</td>
			<td class="DataRow1">&nbsp;</td>
			<td class="DataRow1">&nbsp;</td>
<?if($AllowWrite){?>
                        <td class="DataRow1">&nbsp;</td>
<?}?>
		</tr>
	</table>
</tr>
<tr>
	<td valign="top" height="10%"class="Descr"><?=$DescrRow?></td>
</tr>
<tr>
	<td height="1" class="Footer"><?=$FootRow?></td>
</tr>
</table>

</body>
</html>
