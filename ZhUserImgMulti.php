<?php
//目录下需要有img文件夹，该文件夹内需要有pic1.zhimg.com等4个文件夹，pic1,pic2,pic3,pic4加.zhimg.com
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
$cookie="_zap=7b4ed041-82ff-4da7-a861-beb7d5d30754; _xsrf=17972711-70a1-401a-9d62-ac7e2b31ac4a; d_c0=\"AABmeD_UEQ6PTs_pp43BL9ZK_sZD9BOirsg=|1534494658\"; q_c1=13b99bb546ef434998559910b52a4ad6|1534494700000|1534494700000; capsion_ticket=\"2|1:0|10:1534498835|14:capsion_ticket|44:MWEyYTE2NjNiNjM2NGRhMWE4YjAxNjRlY2IzZmM1Y2U=|4495b59a901310fa01f74d7ab84cf4dce4d715f6e1237ad78f86917ecaf8106a\"; z_c0=\"2|1:0|10:1534498877|4:z_c0|92:Mi4xNFdLYkF3QUFBQUFBQUdaNFA5UVJEaVlBQUFCZ0FsVk5QT1pqWEFDemtnY1VWdmQ3VzhJYTJpcnhnenNuWWV2VWxR|d556b2035373486c278c6a66405dc87b6d0d8a65d6774043b0359f3e2cf64682\"; __utma=51854390.1919606059.1535700680.1535700680.1535700680.1; __utmc=51854390; __utmz=51854390.1535700680.1.1.utmcsr=(direct)|utmccn=(direct)|utmcmd=(none); __utmv=51854390.100--|2=registration_date=20161023=1^3=entry_date=20161023=1; tgw_l7_route=29b95235203ffc15742abb84032d7e75";
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