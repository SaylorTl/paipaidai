<?php
set_time_limit(0);//让程序一直执行下去
$interval=3;//每隔一定时间运行
do{
    $msg=date("Y-m-d H:i:s");
    alog("12321");
    sleep($interval);//等待时间，进行下一次操作。
}while(true);

function alog($str){
    $now = date("Y-m-d H:i:s");
    echo "($now):".$str."\n";
}
exit;
include 'openapi_client.php';

/*step 1 通过code获取授权信息*/
// $code = "52c974893a46478bbf8a0d993a169c8c";
// $authorizeResult = authorize($code);
// var_dump($authorizeResult) ;
$accessToken = "XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX";

/*保存用户授权信息后可获取做权限内的接口调用*/
// $url = "http://gw.open.ppdai.com/open/openApiPublicQueryService/QueryUserNameByOpenID";
// $url = "http://gw.open.ppdai.com/auth/authservice/sendsmsauthcode";
// $url = "http://gw.open.ppdai.com/auth/LoginService/AutoLogin";
// $url = "http://gw.open.ppdai.com/invest/LLoanInfoService/LoanList";
// $url = "http://gw.open.ppdai.com/invest/LLoanInfoService/BatchListingInfos";
$url = "http://gw.open.ppdai.com/balance/balanceService/QueryBalance";

// $request = '{"OpenID": "be47e5e4b9444047b0f8fe9311a8ea29"}';
// $request = '{"Mobile": "15026671512","DeviceFP": "123456"}';
$request = '{"Timestamp": "2017-03-14 19:15:22"}';
// $request = '{"PageIndex": 1,"StartDateTime": "2015-11-11 12:00:00.000"}';
// $request = '{"ListingIds": [100001,123456]}';
$request = '{}';

$result = send($url, $request,$accessToken);

/*加解密*/
// $data = "test";
// $encrypted = encrypt($data);
// $decrypted = decrypt($encrypted);
// echo "Encrypted: ".$encrypted."<br>";
// echo "Decrypted: ".$decrypted;



/*新版投标列表接口（默认每页2000条）*/
function getLoanList(){
    $url = "https://openapi.ppdai.com/invest/LLoanInfoService/LoanList";
    $request = '{
       "PageIndex": 1,
    }';
    $result = send($url, $request);
    if($result['Result'] !== 1){
        log($result['ResultMessage']);
    }
    $aviLoan = array();
    foreach($result['LoanInfos'] as $key=>$value){
        if($value['Rate']<10 || $value['Months']>12){
            continue;
        }
        $aviLoan[]=$value['ListingId'];
    }
    return getLoanInfo($aviLoan);
}

/*获取投标详情*/
function getLoanInfo($aviLoan){
    /*新版散标详情批量接口（请求列表不大于10）*/
    $url = "https://openapi.ppdai.com/invest/LLoanInfoService/BatchListingInfos";
    $aviLoanStr = implode(",",$aviLoan);
    $request = '{"ListingIds": ['.$aviLoanStr.']}';
    $result = send($url, $request);
    if($result['Result']!==1){
        log($result['ResultMessage']);
    }
    $bidList= array();
    foreach($result['LoanInfos'] as $k=>$vl){
         $time_off = time()-strtotime($vl['LastSuccessBorrowTime']);
        if($vl['WasteCount'] >=2 || $time_off<604800){
            log($vl['ListingId']."刚借完又借的资金状况忒差了~淘汰");
            continue;
        }
        if(($vl['NormalCount']/$vl['SuccessCount']<0.6&&$vl['SuccessCount']>4)|| $vl['OverdueLessCount']/$vl['NormalCount']>0.2 ||
            (($vl['Amount']+$vl['OwingAmount'])/ $vl['HighestDebt'] && $vl['NormalCount'] >4)>0.9||($vl['Amount']/$vl['HighestPrincipal']&& $vl['NormalCount'] >4)>0.9
        ){
            log($vl['ListingId']."预防借一次大的逃跑的情况~淘汰！");
            continue;
        }

        if($vl['CertificateValidate']!=1 ||  !in_array($vl['StudyStyle'],array('普通','研究生'))
            || !in_array($vl['EducationDegree'],array('专科','本科','硕士','博士'))){
            log($vl['ListingId']."学渣标~淘汰！");
            continue;
        }

        if($vl['OverdueLessCount']>=5 ||$vl['OverdueMoreCount']>=1 ||!in_array($vl['CreditCode'],array('AA','A','B'))){
            log($vl['ListingId']."信用不良标~淘汰！");
            continue;
        }
        if($vl['WasteCount'] >2)
        if($vl['OwingAmount']>=4000 ||$vl['Amount'] >= 12000){
            log($vl['ListingId']."剩余待还金额太多，属于老油条！~淘汰");
            continue;
        }
        if($vl['Age']<=22||$vl['Age']<=40){
            log($vl['ListingId']."年龄不符合还款要求！~淘汰");
            continue;
        }

        if(in_array($vl['EducationDegree'],array('硕士','博士'))){
            $bidList['a'][]=$vl['ListingId'];
            continue;
        }
        if($vl['CreditValidate'] == 1){
            $bidList['b'][]=$vl['ListingId'];
            continue;
        }
        if($vl['EducationDegree']=='本科'){
            $bidList['c'][]=$vl['ListingId'];
            continue;
        }
        if( $vl['Gender'] == 2 ){
            $bidList['d'][]=$vl['ListingId'];
            continue;
        }
        if( $vl['Gender'] == 1 ){
            $bidList['e'][]=$vl['ListingId'];
            continue;
        }
    }
    return $bidList;
}

function doBid(){
    $bidList = getLoanList();
    /*投标接口*/
    $url = "https://openapi.ppdai.com/invest/BidService/Bidding";
    $accessToken="yourAccessToken";
    foreach($bidList as $bk=>$bv){
        switch ($bk){
            case 'a':
                $amount = 150;
                break;
            case 'b':
                $amount = 100;
                break;
            case 'c':
                $amount = 60;
                break;
                break;
            case 'd':
                $amount = 50;
                break;
            case 'e':
                $amount = 30;
                break;
        }
        foreach($bv as $bkl=>$bvl){
            $request = '{"ListingId": '.$bvl.',"Amount": '.$amount.',"UseCoupon":"true"}';
            $result = send($url, $request,$accessToken);
            if($result['Result']== -1){
                log($result['ListingId'].$result['ResultMessage']);
                continue;
            }
            log($result['ListingId']." ".$bk."级标的投资成功");
        }
    }

}
