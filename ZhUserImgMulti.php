<?php
//这是一个从知乎爬取用户头像的爬虫
//目录下需要有img文件夹，该文件夹内需要有pic1.zhimg.com等4个文件夹，pic1,pic2,pic3,pic4加.zhimg.com
$cookie="";//必须输入登录后的cookie

set_time_limit(0); //设置php文件执行时间

function select($dbh,$value)  //从数据库取出url
{
    $sth = $dbh->prepare("select url from zhurl where id= ?");
    $sth->bindValue(1, $value);
    if($sth->execute()){
        return $sth->fetchAll(PDO::FETCH_ASSOC);;
    }
    else
        return FALSE;      //返回零以区分查询失败还是查询结果为空
}


function insert($dbh,$url) //向数据库插入url,已存在相同url则不插入
{

    
    $sth = $dbh->prepare("insert into zhurl (url) select (?) from dual where not exists(select url from zhurl where url= ?)");
        $sth->bindValue(1, $url);
        $sth->bindValue(2, $url);
    if($sth->execute())
        return 1;
    else
        return 0;
}


function getContentCh($url) //获取句柄
{
    global $cookie;
    $chContent = curl_init($url); //初始化会话
    curl_setopt($chContent, CURLOPT_HEADER, 0);
    $aHeader=array("Referer:https://www.zhihu.com/");
    curl_setopt($chContent, CURLOPT_HTTPHEADER, $aHeader);
    curl_setopt($chContent, CURLOPT_COOKIE, $cookie); //设置请求COOKIE
    curl_setopt($chContent, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); //当前请求头中 User-Agent: 项的内容,定制页面输出以便适应用户代理的性能。
    curl_setopt($chContent, CURLOPT_RETURNTRANSFER, 1); //将curl_exec()获取的信息以字符串的形式返回，而不是直接输出。
    curl_setopt($chContent, CURLOPT_FOLLOWLOCATION, 1); //允许重定向
    return $chContent;
}


function getImg($url,$file)  
{
    if(file_exists("./img/".$file))
        return;
    global $cookie;
    $ch = curl_init($url); //初始化会话
    curl_setopt($ch, CURLOPT_HEADER, 0);
    $fp = fopen("./img/".$file, "w");  //直接保存为文件
    curl_setopt($ch, CURLOPT_FILE, $fp);
    $aHeader=array("Referer:https://www.zhihu.com/");
    curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);
    curl_setopt($ch, CURLOPT_COOKIE, $cookie); //设置请求COOKIE
    curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); //当前请求头中 User-Agent: 项的内容,定制页面输出以便适应用户代理的性能。
    //curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //将curl_exec()获取的信息以字符串的形式返回，而不是直接输出。
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); //允许重定向
    $web_content = curl_exec($ch);
    curl_close($ch);

}

$url = 'https://www.zhihu.com/people/li-wei-76-42-1/following';
$id=1;
$num=0;
$max=5;

//连接数据库
try{
    $dsn='mysql:host=localhost;dbname=spider';
    $username='root';
    $passward='';
    $dbh=new PDO($dsn,$username,$passward);
    $sql="set names utf8";
    $dbh->exec($sql);
}catch (PDOException $e){
    die("数据库连接失败".$e->getMessage());
}


$mh = curl_multi_init(); //返回一个新cURL批处理句柄
$ch=getContentCh($url);
curl_multi_add_handle($mh, $ch); //向curl批处理会话中添加单独的curl句柄
$num++;

 do {
          //依次运行当前每个 cURL 句柄的子连接
    while (($cme = curl_multi_exec($mh, $active)) == CURLM_CALL_MULTI_PERFORM);

    if ($cme != CURLM_OK) {break;}
          //获取当前解析的cURL的相关传输信息
    while ($done = curl_multi_info_read($mh))
    {
        echo "  id=$id  ";

        $num--;  //统计连接数量减少一个
        $info = curl_getinfo($done['handle']);
        $web_content = curl_multi_getcontent($done['handle']);
        $error = curl_error($done['handle']);

        //查找头像
        $reg_tag_a = '/\"https:\/\/(pic[0-9]*\.zhimg\.com\/[^"]*?xll\.jpg)/';
        preg_match_all($reg_tag_a, $web_content, $match_result);
        foreach($match_result[1] as $url)
        {
            getImg($url,$url);
        }

        //查找用户
        $reg_tag_a = '/href=\"\/\/(www\.zhihu\.com\/people\/[^\"]*?)\"/';
        preg_match_all($reg_tag_a, $web_content, $match_result);
        foreach($match_result[1] as $url)
        {
            insert($dbh,$url);
        }

        //保证同时有$max个请求在处理
        if ($num < $max)  
           {
            $url="https://".select($dbh,$id)[0]['url']."/following";
            $ch = getContentCh($url);
            curl_multi_add_handle($mh, $ch);
            $id++;
            $num++;
        }

        curl_multi_remove_handle($mh, $done['handle']);

    }

    if ($active)
      curl_multi_select($mh, 10); //等待下一个网页执行完成
  } while (TRUE);

  curl_multi_close($mh);