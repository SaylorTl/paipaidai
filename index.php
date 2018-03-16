<?php

/*step 1 ͨ��code��ȡ��Ȩ��Ϣ*/
// $code = "0581b0589fcc48cba3628b9349302587";
// $authorizeResult = authorize($code);
// var_dump($authorizeResult) ;exit;


/*�����û���Ȩ��Ϣ��ɻ�ȡ��Ȩ���ڵĽӿڵ���*/
// $url = "http://gw.open.ppdai.com/open/openApiPublicQueryService/QueryUserNameByOpenID";
// $url = "http://gw.open.ppdai.com/auth/authservice/sendsmsauthcode";
// $url = "http://gw.open.ppdai.com/auth/pp_loginService/Autopp_login";
//$url = "http://gw.open.ppdai.com/invest/LLoanInfoService/LoanList";
// $url = "http://gw.open.ppdai.com/invest/LLoanInfoService/BatchListingInfos";
//$url = "http://gw.open.ppdai.com/balance/balanceService/QueryBalance";

// $request = '{"OpenID": "be47e5e4b9444047b0f8fe9311a8ea29"}';
// $request = '{"Mobile": "15026671512","DeviceFP": "123456"}';
//$request = '{"Timestamp": "2017-03-14 19:15:22"}';
//$request = '{"PageIndex": 1,"StartDateTime": "2018-03-11 12:00:00.000"}';
// $request = '{"ListingIds": [100001,123456]}';
//$request = '{}';

//$result = send($url, $request,$accessToken);

/*�ӽ���*/
// $data = "test";
// $encrypted = encrypt($data);
// $decrypted = decrypt($encrypted);
// echo "Encrypted: ".$encrypted."<br>";
// echo "Decrypted: ".$decrypted;
include 'openapi_client.php';
include 'src/cache/FileCache.php';


$cache = new Cache_Driver(array("./src/cache/runtime"));
//$accessToken = "76b51c39-b62c-4a9e-9cac-d417b8afc013";//�Ͻ��
$accessToken = "85560409-c35c-476e-a612-9612a51ea1d9";//�ҵ�

function pp_log($str,$bid=null,$creditcode=null){
    $now = date("Y-m-d H:i:s");
    echo "($now):".$creditcode."���".$bid.$str."\n";
}
set_time_limit(0);// ͨ��set_time_limit(0)�����ó��������Ƶ�ִ����ȥ
ini_set('memory_limit','512M'); // �����ڴ�����
$finish = true;
$interval=15;//ÿ��һ��ʱ������
$PageIndex = 1;
$isContinue = true;

do{
    var_dump($finish);
    if($finish){
        $finish = false;
        $msg=date("Y-m-d H:i:s");
        getLoanList();
        echo "��ѯ��".$PageIndex."ҳ\n";
        $PageIndex ++;

    }
    sleep($interval);//�ȴ�ʱ�䣬������һ�β�����
}while(true);
//function run(){
//    $time=15;
//    $url="http://127.0.0.1/paipaidai/";
//    print_r(12312);
//    sleep($time);
//    echo "���һ�Σ�ִ����һ�β�ѯ\n";
//    file_get_contents($url);
//}

/*�°�Ͷ���б�ӿڣ�Ĭ��ÿҳ2000����*/
function getLoanList(){
    global  $accessToken;
    global  $cache;
    global  $finish;
    global $PageIndex;
    global $isContinue;

    //��ʱ������
    $nowRecodeTime = time();
    $lastRecodeTime = $cache->get("lastRecodeTime",$nowRecodeTime) ;
    if($nowRecodeTime - $lastRecodeTime >3600){
        $cache->set("lastRecodeTime",$nowRecodeTime);
        $cache->clean();
    }

    $url = "http://gw.open.ppdai.com/invest/LLoanInfoService/LoanList";
    $date = date("Y-m-d H:i:s",time()-3600);
    $request = '{"PageIndex":'.$PageIndex.',"StartDateTime": "'.$date.'"}';
    $result = json_decode(send($url, $request,$accessToken),true);

    if($result['Result'] !== 1){
        pp_log($result['ResultMessage']);
        $finish = true;
        return;
    }
    $aviLoan = array();
    if(count($result['LoanInfos'])<200){
        $PageIndex = 1;
        $isContinue = false;
    }
    if(empty($result['LoanInfos'])){
        pp_log('��ѯ���Ϊ��','123');
        $finish = true;
        return;
    }
    foreach($result['LoanInfos'] as $key=>$value){
        if($value['Rate']<10 || $value['Months']>12){
            continue;
        }
        if($cache->get("ppid".$value['ListingId'])){
            pp_log("����ѱ�ǣ������ظ���ѯ",$value['ListingId']);
            continue;
        }
        $aviLoan[]=$value['ListingId'];
    }
    $temp = array();
    foreach($aviLoan as $k=>$v){
        $temp[]=$v;
        $cache->set("ppid".$v,1,900);
        if(($k % 9)==0 && $k>0){
            $bidList = getLoanInfo($temp);
            if(1 == $bidList['Result'] ){
                doBid($bidList['LoanInfos']);
            }
            $temp = array();
        }

    }
    $finish = true;

}

/*��ȡͶ������*/
function getLoanInfo($aviLoan){
    global  $accessToken;
    /*�°�ɢ�����������ӿڣ������б�����10��*/
    $url = "http://gw.open.ppdai.com/invest/LLoanInfoService/BatchListingInfos";
    $aviLoanStr = implode(",",$aviLoan);
    $request = '{"ListingIds": ['.$aviLoanStr.']}';
    $result = json_decode(send($url, $request,$accessToken),true);
    if($result['Result']!==1){
        pp_log($result['ResultMessage']);
        return array('Result'=>0);
    }
    return $result;
}

function doBid($bidList){
    global  $accessToken;
    if($bidList){
        /*Ͷ��ӿ�*/
        $url = "http://gw.open.ppdai.com/invest/BidService/Bidding";
        foreach($bidList as $bk=>$bv){
            $amount = getBidAmount($bv);
            if($amount >0){
                pp_log(" ".$bv['CreditCode']."��ʼͶ��",$bv['ListingId']);
                $request = '{"ListingId": '.$bv['ListingId'].',"Amount": '.$amount.',"UseCoupon":"true"}';
                $result = json_decode(send($url, $request,$accessToken),true);
                if($result['Result']!= 0){
                    pp_log($result['Result'].$result['ResultMessage'],$result['ListingId']);
                    continue;
                }
                pp_log(" ".$bv['CreditCode']."�����Ͷ�ʳɹ�",$bv['ListingId']);
            }
        }
    }
}


/**����Ͷ�ʲ��ԣ�����ñ��Ͷ�ʶ��, otherpersonflagΪ��ʱ���������û���Ͷ��*/
function getBidAmount($LoanInfo){
    global $config;
    $flag="";
    $owing = $LoanInfo['Amount'] + $LoanInfo['OwingAmount'];

    $repayCountRatio = getRepayCountRatio($LoanInfo);
    $owingRatio = getOwingRatio($LoanInfo);
//    //��ǰ�ֱ���5.5 �� 0.85
    if($LoanInfo['CreditCode']=='AA'){
        return 100;
    }
//    if($LoanInfo['HighestDebt']>=13000 && ($owingRatio>1)){
//        pp_log('����ʷ��߸�ծ�ߣ��е�����~'.($LoanInfo['Amount']+ $LoanInfo['OwingAmount']).'/'.$LoanInfo['HighestDebt'],$LoanInfo['ListingId'],$LoanInfo['CreditCode']);
//        return 0;
//    }

    $owinglimit = getCreditLimit($LoanInfo);
    if($owinglimit<=0){
        return 0;
    }

    $creditPerNorm = $owing;	//����һ���Ƚϴ����(ȱʡû������)
    if($LoanInfo['NormalCount']>0) $creditPerNorm = $owing/$LoanInfo['NormalCount'];	//ÿ�����������Ӧ��id

    //Ͷ����������,���������ܱ���,��ֹ����
    if($LoanInfo['Months']>12)  return 0;		//12����ҲͶ

    //Ͷ����������,���ֻͶ6���£���������
//		if(loan.month>=12)  return 0;		//12���²�Ͷ��ֻͶ6����

    //���ʽ���̫��
    if($LoanInfo['Amount']>$config['AmountLimit'] || $LoanInfo['Amount']>=10000)  return 0;

    //��������̫��
    if($LoanInfo['OwingAmount']>$config['OwingAmountLimit'] || $LoanInfo['OwingAmount']>=15000) return 0;

    $bidAmount=0;	//Ԥ��Ͷ�ʵĽ��

    if($owinglimit>0){
        //�ɹ��������/�������� �������һ��ֵ�����ʾǰ��Ļ���Ƚ�����
        //�ò����ǳ���Ҫ�������жϽ���ˢ���õ����
        $r = $LoanInfo['NormalCount']/$LoanInfo['SuccessCount'];
        $bidAmount=1;
        if($owing<=$owinglimit){
            $ro = $owing/$owinglimit;	//Ƿ��Ͷ�ȵı���
            $rhd = 10;	//Ƿ�����ʷ��߸��صı���
            if($LoanInfo['HighestDebt']>0) $rhd = $owing/$LoanInfo['HighestDebt'];
            $trueloanflag = false;
            if($LoanInfo['TotalPrincipal']>0 && $LoanInfo['TotalPrincipal']<5000){
                $trueloanflag = false;	//�ۼƽ������̫С����
            }
            else if($rhd>=1.1){
                $trueloanflag = false;	//Ҫ������Ƿ�������߸�ծһ������
            }else if($LoanInfo['Amount']>=$LoanInfo['HighestPrincipal'] && $rhd>=0.8){
                //��������߽��,ͬʱ������߸�ծ�ı���
                $trueloanflag = false;
            }
            else{	//��������������������ǰ�涼��ɸѡ����
                //if(r>=4 || (rhd>0 && rhd<0.7))	//���ϸ�����
                if($r>=4 && ($rhd>0 && $rhd<0.7))	//�ϸ�����
                {		//ԭ���ϸ��ʱ������rhd<0.6
                    $trueloanflag = true;	//��������Ĵ����Ȼ���
                }
            }

            //�������ܴ���10000Ԫ
            if($trueloanflag){
                $bidAmount = getBidCreit($LoanInfo);

            }
        }
    }
    else if($creditPerNorm<=85 && $owing<=6000 && $LoanInfo['NormalCount']>=40){
        //����Ƿ��С��1�������ö�Ƚϸߵ����
//			bidAmount=BidPolicy.creditBidCount;
    }
    else if($owing<=5000 && $LoanInfo['Months']<=6 && $LoanInfo['SuccessCount'] ==0 && $LoanInfo['CancelCount']==0 && $LoanInfo['FailedCount']==0){
        //��һ�ν������
        if($LoanInfo['Gender']==2 ||
            ($LoanInfo['EducationDegree']!=null && $LoanInfo['EducationDegree']=="����"
                && $LoanInfo['StudyStyle']!=null && $LoanInfo['StudyStyle']=="��ͨ"	)){
            $bidAmount=1;	//������̫С��������ʾ���ݣ�����ʵ��Ͷ�ʣ�������
        }
    }
    else if($creditPerNorm<100 && $owing<20000 && $LoanInfo['NormalCount']>=30){
        //����Ƿ��С��1�������ö�Ƚϸߵ����
        $bidAmount=1;	//������̫С��������ʾ���ݣ�����ʵ��Ͷ�ʣ�������
    }
    return $bidAmount;
}

/**flagΪtrue��ʾ�Ƿ����������Ͷ���Ӱ��*/
function  getCreditLimit($loaninfo){
    global $config;
    //����Ҫ��֤���֤�͵绰
    if($loaninfo['PhoneValidate']==0){	//99%���ϵĶ����ֻ���֤
        pp_log('���ֻ���',$loaninfo['ListingId']);
        return 0;
    }

    $time_off = time()-strtotime($loaninfo['LastSuccessBorrowTime']);
    if( $time_off<604800){
        pp_log("�ս����ֽ���ʽ�״��߯����~��̭",$loaninfo['ListingId'],$loaninfo['CreditCode']);
        return 0;
    }
    //�г��ڻ����¼��
    if($loaninfo['OverdueMoreCount']>0){
        pp_log('�г��ڻ����¼',$loaninfo['ListingId'],$loaninfo['CreditCode']);
        return 0;
    }
    //����3�ε�ֱ�ӹ����������и��ϸ��Ҫ��
    if($loaninfo['OverdueLessCount']>=3){
        pp_log('����(1-15)�����������3',$loaninfo['ListingId'],$loaninfo['CreditCode']);
        return 0;
    }

    //ѧ�������
    if($loaninfo['CertificateValidate']==0){
        pp_log('δ���ѧ����֤',$loaninfo['ListingId'],$loaninfo['CreditCode']);
        return 0;
    };	//ѧ����֤��ռ��1/3
    $owing = $loaninfo['Amount'] + $loaninfo['OwingAmount'];	//������ɹ���Ĵ���
    $strictflag=false;	//����ܺõı�
    if($loaninfo['Months']==6 && ($loaninfo['CreditCode'] == 'D'||$loaninfo['CreditCode'] == 'C')){
        if($loaninfo['NormalCount']>45 && $owing<5500) $strictflag=true;
        else if($loaninfo['NormalCount']>=70 && $owing<6500) $strictflag=true;
        else if($loaninfo['NormalCount']>=100 && $owing<7500) $strictflag=true;
    }
    //����������(��openAPI�Զ�Ͷ���к����ر��ע)
    if($loaninfo['OverdueLessCount']>=1){
        $overdueflag = true;
        //���ϸ�������,35��������45��
        //�ϸ������£�45��������60
        if($loaninfo['NormalCount']>$config['OverduelessNormalCountBase']
            && ($loaninfo['NormalCount']> ($loaninfo['OverdueLessCount']*$config['OverduelessNormalCountPerOne']))){
            $overdueflag = false;
        }
        if($overdueflag){
            //��������һ�ε�
            pp_log('������̭',$loaninfo['ListingId'],$loaninfo['CreditCode']);
            return 0;
        }
    }

    if($config['NoWasteCountFlag']){
        //������������ͳ�������
        if($loaninfo['FailedCount']>0 || (!$strictflag && $loaninfo['FailedCount']==1)){
            pp_log('������������ͳ�������',$loaninfo['ListingId'],$loaninfo['CreditCode']);
            return 0;//ʧ��
        }
        if($loaninfo['CancelCount']>0 || (!$strictflag && $loaninfo['CancelCount']==1)){
            pp_log('������������ͳ�������',$loaninfo['ListingId'],$loaninfo['CreditCode']);
            return 0;	//����
        }
        if($loaninfo['WasteCount']>0 || (!$strictflag && $loaninfo['WasteCount']==1)){
            pp_log('������������ͳ�������',$loaninfo['ListingId'],$loaninfo['CreditCode']);
            return 0;		//����
        }
    }

    //ϵͳ��ֹ18�����µ��˽�ͬʱ34�꼰����ռ��80%���ϣ�40�꼰����92%��38�꼰����ռ��90%
    //48�꼰����ռ��98%
    //ϵͳ������ֲ����Ա�ķֲ������ϵ����
    //***�����Լ��ĺ�����ͳ��30�����Ͻ��С�������Ƚϴ�(ԭ���ǲ��ܴ���32�꣩
    if($loaninfo['Age']<20 || $loaninfo['Age']>=38){
        pp_log('���䲻����Ҫ��',$loaninfo['ListingId'],$loaninfo['CreditCode']);
        return 0;
    }

    if($loaninfo['Age']>=30 && $owing<=5000){
        pp_log('30������С���������Ƚϴ�',$loaninfo['ListingId'],$loaninfo['CreditCode']);
        return 0;
    }
    //��ǰ���ó�25������6000
    if($loaninfo['NormalCount']<35 && $owing<=6000 && $loaninfo['Months']==6 && ($loaninfo['CreditCode'] == 'C')){
        //�����¼���ٵ�С�����
        $loaninfo['Flag']="Little";
        pp_log('�����¼����',$loaninfo['ListingId'],$loaninfo['CreditCode']);
        return 0;	//20������³��ֵı�ܶ�
    }

    //�ɹ��������/�������� �������һ��ֵ�����ʾǰ��Ļ���Ƚ�����
    //�ò����ǳ���Ҫ�������ж�ͨ��ȫ�����ǰ�������ˢ���õ����
    //�������ȫ�Ϣ����ǰ������ᵼ���쳣
//    if($loaninfo['SuccessCount'] >0){
//        $r = ($loaninfo['NormalCount'])/$loaninfo['SuccessCount'];
//        if($r<3){
//            pp_log('С��������ˢ����',$loaninfo['ListingId']);
//            return 0;
//        }
//    }

    //������ܵ�
    $owinglimit = 0;
    if ($loaninfo['CertificateValidate'] == 1 && $loaninfo['StudyStyle'] != null) {
        if ($loaninfo['Gender'] == 2) $owinglimit += 1000; // Ů
        // CertificateValidateѧλ��֤, ��EducateValidateѧ����֤��
        if ($loaninfo['StudyStyle']=="��ͨ" ||$loaninfo['StudyStyle']=="��ͨȫ����") {
            if (strpos($loaninfo['EducationDegree'],"����")!=false) {
                $owinglimit += 3000;
            } else {
                $owinglimit += 2000;
            }
        } else if ("�о���"==$loaninfo['StudyStyle']) {
            $owinglimit += 5000;
        } else{
            $owinglimit += 1000;
        }

        if ($loaninfo['VideoValidate'] == 1 || $loaninfo['NciicIdentityCheck'] == 1) {
            // ��Ƶ��֤���߻�����֤
            $owinglimit += 1000;
        }
        if ($loaninfo['CreditValidate'] == 1) {
            $owinglimit += 2000; // ����������֤
        }

        //�����Ѿ�����Ĵ���������������
        if ($loaninfo['NormalCount'] >= 25) {
            $rebitlimit = 200 * ($loaninfo['NormalCount'] - 20);
            $owinglimit += $rebitlimit;
        }
        //�����ۼƻ���Ķ����������
        if ($loaninfo['TotalPrincipal'] >= 0) {
            $rebitlimit = ($loaninfo['TotalPrincipal'] - $loaninfo['OwingPrincipal'])/5;
            if($rebitlimit>0) $owinglimit += $rebitlimit;
        }
        // ����̫��, Ŀǰ6���µĶ�ȱ�12���µĶ�ȸ�
        if ($owinglimit > $config['MaxOwingLimit6'])
            $owinglimit = $config['MaxOwingLimit6'];
        if ($loaninfo['Months'] >= 12) {
            if ($owinglimit > $config['MaxOwingLimit12'])
                $owinglimit = $config['MaxOwingLimit12'];
        }
    }
    return $owinglimit;
}

/**ֻͶѧ���꣬����ѧ���Ĳ�ͬ���Ҳ��ͬ**/
function getBidCreit($LoanInfo){
    global $config;
    $bidAmount=$config['CreditBidAmount'];
    if ($LoanInfo['CertificateValidate'] == 1 && $LoanInfo['StudyStyle'] != null) {
        if ($LoanInfo['Gender'] == 2) $bidAmount += 10; // Ů
        // CertificateValidateѧλ��֤, ��EducateValidateѧ����֤��
        if ($LoanInfo['StudyStyle']=="��ͨ" ||$LoanInfo['StudyStyle']=="��ͨȫ����") {
            if (strpos($LoanInfo['EducationDegree'],"����")!=false) {
                $bidAmount += 30;
            } else {
                $bidAmount += 20;
            }
        } else if ("�о���"==$LoanInfo['StudyStyle']) {
            $bidAmount += 50;
        } else{
            $bidAmount += 10;
        }

        if ($LoanInfo['VideoValidate'] == 1 || $LoanInfo['NciicIdentityCheck'] == 1) {
            // ��Ƶ��֤���߻�����֤
            $bidAmount += 10;
        }
        if ($LoanInfo['CreditValidate'] == 1) {
            $bidAmount += 20; // ����������֤
        }
    }
    return $bidAmount;
}

/**Ƿ�������Ƿ�����*/
function getOwingRatio($loaninfo){
    $owingRatio = 0;
    if($loaninfo['HighestDebt']>0) $owingRatio=  ($loaninfo['Amount']+ $loaninfo['OwingAmount'])/$loaninfo['HighestDebt'];
    return $owingRatio;
}

/**�ؿ��������*/
function  getRepayCountRatio($loaninfo){
    $repayRatio = 0;
    if($loaninfo['SuccessCount']>0) $repayRatio= $loaninfo['NormalCount']/$loaninfo['SuccessCount'];
    return $repayRatio;
}